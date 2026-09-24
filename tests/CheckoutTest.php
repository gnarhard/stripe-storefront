<?php

use Gnarhard\StripeStorefront\Events\OrderCreated;
use Gnarhard\StripeStorefront\Events\OrderFailed;
use Gnarhard\StripeStorefront\Http\Controllers\ProductController;
use Gnarhard\StripeStorefront\Mail\OrderConfirmation;
use Gnarhard\StripeStorefront\Models\Customer;
use Gnarhard\StripeStorefront\Models\Order;
use Gnarhard\StripeStorefront\Models\Price;
use Gnarhard\StripeStorefront\Models\Product;
use Gnarhard\StripeStorefront\StripeStorefront;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

beforeEach(function () {
    View::addLocation(__DIR__.'/fixtures/views');

    $this->product = Product::create([
        'stripe_id' => 'prod_123',
        'name' => 'Tablature Collection',
        'slug' => 'tablature-collection',
        'metadata' => ['category' => 'merch', 'filename' => 'tabs.zip'],
        'image_urls' => [],
    ]);

    Price::create([
        'stripe_id' => 'price_123',
        'product_id' => $this->product->id,
        'unit_amount' => 2500,
        'type' => 'one_time',
        'payment_link_id' => 'plink_123',
        'payment_link' => 'https://buy.stripe.com/test',
    ]);
});

function checkoutSessionResponse(array $overrides = []): array
{
    return array_merge([
        'id' => 'cs_test_123',
        'object' => 'checkout.session',
        'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
        'amount_total' => 2500,
        'payment_status' => 'paid',
        'line_items' => lineItemsFor('prod_123'),
        'customer_details' => [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+15555550100',
            'address' => [
                'line1' => '1 Main St',
                'line2' => null,
                'city' => 'Denver',
                'state' => 'CO',
                'postal_code' => '80202',
                'country' => 'US',
            ],
        ],
    ], $overrides);
}

/**
 * The expanded line_items list of a session that bought one unit of the given Stripe product.
 */
function lineItemsFor(string $stripeProductId): array
{
    return [
        'object' => 'list',
        'data' => [[
            'id' => 'li_123',
            'object' => 'item',
            'price' => ['id' => 'price_123', 'object' => 'price', 'product' => $stripeProductId],
            'quantity' => 1,
        ]],
        'has_more' => false,
        'url' => '/v1/checkout/sessions/cs_test_123/line_items',
    ];
}

function fakeDownloads(string ...$filenames): void
{
    config(['stripe-storefront.downloads-storage-disk' => 'r2']);
    Storage::fake('r2');

    foreach ($filenames as $filename) {
        Storage::disk('r2')->put("downloads/{$filename}", 'zip');
    }
}

it('binds the storefront client to the test key outside production', function () {
    expect(app(StripeStorefront::class)->apiKey)->toBe('sk_test_fake');
});

it('binds the storefront client to the live key in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(app(StripeStorefront::class)->apiKey)->toBe('sk_live_fake');
});

it('creates a Stripe Checkout session and redirects to it', function () {
    $stripe = $this->fakeStripe([
        'POST /v1/checkout/sessions' => [200, checkoutSessionResponse()],
    ]);

    $this->get(route('store.checkout', ['product' => 'tablature-collection']))
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_123');

    $params = $stripe->lastRequestTo('POST', '/v1/checkout/sessions')['params'];

    expect($params['mode'])->toBe('payment')
        ->and($params['line_items'])->toBe([['price' => 'price_123', 'quantity' => 1]])
        ->and($params['allow_promotion_codes'])->toBe('true')
        ->and($params)->not->toHaveKey('discounts')
        ->and($params['success_url'])->toContain('session_id={CHECKOUT_SESSION_ID}')
        ->and($params['cancel_url'])->toBe(route('store.product.show', ['category' => 'merch', 'product' => 'tablature-collection']));
});

it('applies a discount code instead of allowing promotion codes', function () {
    $stripe = $this->fakeStripe([
        'POST /v1/checkout/sessions' => [200, checkoutSessionResponse()],
    ]);

    $this->get(route('store.checkout', ['product' => 'tablature-collection', 'discount_code_id' => 'FRIENDS']))
        ->assertRedirect();

    $params = $stripe->lastRequestTo('POST', '/v1/checkout/sessions')['params'];

    expect($params['discounts'])->toBe([['coupon' => 'FRIENDS']])
        ->and($params)->not->toHaveKey('allow_promotion_codes');
});

it('rejects checkout for an unknown product', function () {
    $this->fakeStripe();

    $this->get(route('store.checkout', ['product' => 'missing']))
        ->assertSessionHasErrors('product');
});

it('records the customer and order from the Stripe session on thank you', function (string $paymentStatus) {
    app()->detectEnvironment(fn () => 'local');
    Event::fake([OrderCreated::class]);

    $stripe = $this->fakeStripe([
        'GET /v1/checkout/sessions/cs_test_123' => [200, checkoutSessionResponse(['payment_status' => $paymentStatus])],
    ]);

    $this->get(route('store.thank-you', ['product' => 'tablature-collection']).'&session_id=cs_test_123')
        ->assertOk()
        ->assertSee('Thank you Jane Doe for Tablature Collection (2500)');

    $customer = Customer::where('email', 'jane@example.com')->sole();

    expect($customer->name)->toBe('Jane Doe')
        ->and($customer->phone)->toBe('+15555550100')
        ->and($customer->address['postal_code'])->toBe('80202')
        ->and(Order::where('stripe_session_id', 'cs_test_123')->sole()->total)->toEqual(2500)
        ->and($stripe->lastRequestTo('GET', '/v1/checkout/sessions/cs_test_123')['params'])->toBe(['expand' => ['line_items']]);

    Event::assertDispatched(OrderCreated::class, fn (OrderCreated $event) => $event->customer->is($customer));
})->with([
    'paid' => 'paid',
    'fully discounted' => 'no_payment_required',
]);

it('shows the order failed page when the session did not pay for the product', function (array $session) {
    app()->detectEnvironment(fn () => 'local');
    Event::fake([OrderFailed::class, OrderCreated::class]);

    $this->fakeStripe([
        'GET /v1/checkout/sessions/cs_test_123' => [200, checkoutSessionResponse($session)],
    ]);

    $this->get(route('store.thank-you', ['product' => 'tablature-collection']).'&session_id=cs_test_123')
        ->assertOk()
        ->assertSee('Order failed');

    Event::assertDispatched(OrderFailed::class, fn (OrderFailed $event) => $event->exception !== null);
    Event::assertNotDispatched(OrderCreated::class);
    expect(Order::count())->toBe(0);
})->with([
    'unpaid' => [['payment_status' => 'unpaid']],
    'another product' => [['line_items' => lineItemsFor('prod_other')]],
    'no line items' => [['line_items' => null]],
]);

it('shows the order failed page when the Stripe session cannot be retrieved', function () {
    app()->detectEnvironment(fn () => 'local');
    Event::fake([OrderFailed::class, OrderCreated::class]);

    $this->fakeStripe();

    $this->get(route('store.thank-you', ['product' => 'tablature-collection']).'&session_id=cs_missing')
        ->assertOk()
        ->assertSee('Order failed');

    Event::assertDispatched(OrderFailed::class, fn (OrderFailed $event) => $event->exception !== null);
    Event::assertNotDispatched(OrderCreated::class);
    expect(Order::count())->toBe(0);
});

it('shows the order failed page without a session id', function () {
    Event::fake([OrderFailed::class]);

    $this->get(route('store.thank-you', ['product' => 'tablature-collection']))
        ->assertSee('Order failed');

    Event::assertDispatched(OrderFailed::class);
});

it('checks whether a promo code is a valid Stripe coupon', function (int $status, array $body, bool $expected) {
    $this->fakeStripe(['GET /v1/coupons/FRIENDS' => [$status, $body]]);

    expect(app(ProductController::class)->promo_code_exists('FRIENDS'))->toBe($expected);
})->with([
    'valid coupon' => [200, ['id' => 'FRIENDS', 'object' => 'coupon', 'valid' => true], true],
    'expired coupon' => [200, ['id' => 'FRIENDS', 'object' => 'coupon', 'valid' => false], false],
    'missing coupon' => [404, ['error' => ['type' => 'invalid_request_error', 'message' => 'No such coupon']], false],
]);

it('redirects downloads to a temporary url', function () {
    fakeDownloads('tabs.zip');

    $this->get($this->product->downloadUrl())
        ->assertRedirectContains('downloads/tabs.zip');
});

it('aborts downloads when the file is missing', function () {
    fakeDownloads();

    $this->get($this->product->downloadUrl())
        ->assertNotFound();
});

it('refuses download links that were not signed for the product', function () {
    fakeDownloads('tabs.zip', 'other.zip');
    Product::create([
        'stripe_id' => 'prod_other',
        'name' => 'Other',
        'slug' => 'other',
        'metadata' => ['category' => 'merch', 'filename' => 'other.zip'],
    ]);

    $this->get(route('store.download', ['product' => 'tablature-collection']))
        ->assertForbidden();

    $this->get(str_replace('product=tablature-collection', 'product=other', $this->product->downloadUrl()))
        ->assertForbidden();
});

it('downloads the signed product even when the request body names another one', function () {
    fakeDownloads('tabs.zip', 'other.zip');
    Product::create([
        'stripe_id' => 'prod_other',
        'name' => 'Other',
        'slug' => 'other',
        'metadata' => ['category' => 'merch', 'filename' => 'other.zip'],
    ]);

    $this->call('GET', $this->product->downloadUrl(), server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['product' => 'other']))
        ->assertRedirectContains('downloads/tabs.zip');
});

it('accepts download links opened on another host', function () {
    fakeDownloads('tabs.zip');

    $this->get(str_replace(url('/'), 'https://www.example.test', $this->product->downloadUrl()))
        ->assertRedirectContains('downloads/tabs.zip');
});

it('emails an absolute download link that keeps working', function () {
    fakeDownloads('tabs.zip');

    $url = (new OrderConfirmation($this->product))->content()->with['downloadUrl'];
    $this->travel(10)->years();

    expect($url)->toStartWith(url('/store/download').'?product=tablature-collection&signature=');
    $this->get($url)->assertRedirectContains('downloads/tabs.zip');
});
