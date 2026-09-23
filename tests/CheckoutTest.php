<?php

use Gnarhard\StripeStorefront\Events\OrderCreated;
use Gnarhard\StripeStorefront\Events\OrderFailed;
use Gnarhard\StripeStorefront\Http\Controllers\ProductController;
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

it('records the customer and order from the Stripe session on thank you', function () {
    app()->detectEnvironment(fn () => 'local');
    Event::fake([OrderCreated::class]);

    $this->fakeStripe([
        'GET /v1/checkout/sessions/cs_test_123' => [200, checkoutSessionResponse()],
    ]);

    $this->get(route('store.thank-you', ['product' => 'tablature-collection']).'&session_id=cs_test_123')
        ->assertOk()
        ->assertSee('Thank you Jane Doe for Tablature Collection (2500)');

    $customer = Customer::where('email', 'jane@example.com')->sole();

    expect($customer->name)->toBe('Jane Doe')
        ->and($customer->phone)->toBe('+15555550100')
        ->and($customer->address['postal_code'])->toBe('80202')
        ->and(Order::where('stripe_session_id', 'cs_test_123')->sole()->total)->toEqual(2500);

    Event::assertDispatched(OrderCreated::class, fn (OrderCreated $event) => $event->customer->is($customer));
});

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
    config(['stripe-storefront.downloads-storage-disk' => 'r2']);
    Storage::fake('r2');
    Storage::disk('r2')->put('downloads/tabs.zip', 'zip');

    $this->get(route('store.download', ['product' => 'tablature-collection']))
        ->assertRedirectContains('downloads/tabs.zip');
});

it('aborts downloads when the file is missing', function () {
    config(['stripe-storefront.downloads-storage-disk' => 'r2']);
    Storage::fake('r2');

    $this->get(route('store.download', ['product' => 'tablature-collection']))
        ->assertNotFound();
});
