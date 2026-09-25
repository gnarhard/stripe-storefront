<?php

use Gnarhard\StripeStorefront\Events\WebhookHandled;
use Gnarhard\StripeStorefront\Events\WebhookReceived;
use Gnarhard\StripeStorefront\Http\Controllers\WebhookController;
use Gnarhard\StripeStorefront\Models\Price;
use Gnarhard\StripeStorefront\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Stripe\WebhookSignature;

it('returns 403 when Stripe-Signature is missing', function () {
    config(['stripe-storefront.stripe.webhook.secret' => 'test']);
    $payload = ['type' => 'test.event'];

    $this->postJson('/stripe/webhook', $payload)
        ->assertStatus(403); // 403 Forbidden is returned for AccessDeniedHttpException
});

it('returns 403 when Stripe-Signature is invalid', function () {
    config(['stripe-storefront.stripe.webhook.secret' => 'test']);
    $payload = ['type' => 'test.event'];

    $this->postJson('/stripe/webhook', $payload, [
        'Stripe-Signature' => 'invalid-signature',
    ])->assertStatus(403); // 403 Forbidden is returned for AccessDeniedHttpException
});

it('returns missing method response when no handler exists', function () {
    config(['stripe-storefront.stripe.webhook.secret' => null]);

    Event::fake();

    $payload = [
        'type' => 'non.existent', // This translates to method "handleNonExistent" which does not exist.
    ];

    $response = $this->postJson('/stripe/webhook', $payload);

    // Ensure the event for receiving the webhook is dispatched.
    Event::assertDispatched(WebhookReceived::class);
    // Since there is no matching handler, WebhookHandled should not be dispatched.
    Event::assertNotDispatched(WebhookHandled::class);

    // The missingMethod() returns a new Response without content (defaults to HTTP 200).
    $response->assertStatus(200);
    $this->assertEmpty($response->getContent());
});

it('handles event with existing handler method and dispatches WebhookHandled event', function () {
    config(['stripe-storefront.stripe.webhook.secret' => null]);
    Event::fake();

    // Create an inline test controller that extends the base controller
    // and implements a handler for the "test.event" type.
    class TestWebhookController extends WebhookController
    {
        public function handleTestEvent($payload)
        {
            return new Response('Test Event Handled', 200);
        }
    }

    // Register a separate route for our test controller.
    Route::post('/stripe/test-webhook', [TestWebhookController::class, 'handle']);

    $payload = [
        'type' => 'test.event', // This will map to "handleTestEvent"
    ];

    $response = $this->postJson('/stripe/test-webhook', $payload);

    // Both events should be dispatched when a matching handler exists.
    Event::assertDispatched(WebhookReceived::class);
    Event::assertDispatched(WebhookHandled::class);

    $response->assertStatus(200);
    $response->assertSee('Test Event Handled');
});

it('accepts webhooks with a valid Stripe signature', function () {
    config(['stripe-storefront.stripe.webhook.secret' => 'whsec_test']);
    Event::fake();

    $payload = json_encode(['type' => 'checkout.session.completed']);

    $this->call('POST', '/stripe/webhook', server: [
        'HTTP_STRIPE_SIGNATURE' => WebhookSignature::generateSignatureHeader($payload, 'whsec_test'),
        'CONTENT_TYPE' => 'application/json',
    ], content: $payload)->assertOk();

    Event::assertDispatched(WebhookReceived::class);
});

it('rejects webhooks signed outside the tolerance window', function () {
    config(['stripe-storefront.stripe.webhook.secret' => 'whsec_test']);

    $payload = json_encode(['type' => 'checkout.session.completed']);

    $this->call('POST', '/stripe/webhook', server: [
        'HTTP_STRIPE_SIGNATURE' => WebhookSignature::generateSignatureHeader($payload, 'whsec_test', time() - 3600),
        'CONTENT_TYPE' => 'application/json',
    ], content: $payload)->assertForbidden();
});

function storeProduct(string $slug): Product
{
    $product = Product::create(['stripe_id' => "prod_{$slug}", 'name' => $slug, 'slug' => $slug, 'metadata' => ['category' => 'merch']]);
    Price::create(['stripe_id' => "price_{$slug}", 'product_id' => $product->id, 'unit_amount' => 2500, 'type' => 'one_time']);

    return $product;
}

it('removes a product from the store when Stripe archives or deletes it', function (string $type, array $object) {
    config(['stripe-storefront.stripe.webhook.secret' => null]);
    storeProduct('plantable');
    $kept = storeProduct('poster');
    Cache::put('featured_merch', 'stale');
    Cache::put('unfeatured_merch', 'stale');

    $this->postJson('/stripe/webhook', ['type' => $type, 'data' => ['object' => $object]])->assertOk();

    expect(Product::pluck('id')->all())->toBe([$kept->id])
        ->and(Price::pluck('stripe_id')->all())->toBe(['price_poster'])
        ->and(Cache::has('featured_merch'))->toBeFalse()
        ->and(Cache::has('unfeatured_merch'))->toBeFalse();
})->with([
    'product archived' => ['product.updated', ['id' => 'prod_plantable', 'active' => false]],
    'product deleted' => ['product.deleted', ['id' => 'prod_plantable']],
    'price archived' => ['price.updated', ['id' => 'price_plantable', 'active' => false]],
    'price deleted' => ['price.deleted', ['id' => 'price_plantable']],
]);

it('keeps the store as is when Stripe updates an active product or price', function (string $type, string $id) {
    config(['stripe-storefront.stripe.webhook.secret' => null]);
    storeProduct('plantable');
    Cache::put('featured_merch', 'cached');

    $this->postJson('/stripe/webhook', ['type' => $type, 'data' => ['object' => ['id' => $id, 'active' => true]]])->assertOk();

    expect(Product::count())->toBe(1)
        ->and(Price::count())->toBe(1)
        ->and(Cache::get('featured_merch'))->toBe('cached');
})->with([
    'product' => ['product.updated', 'prod_plantable'],
    'price' => ['price.updated', 'price_plantable'],
]);

it('ignores removal events for products the store does not list', function (string $type, string $id) {
    config(['stripe-storefront.stripe.webhook.secret' => null]);
    storeProduct('plantable');
    Cache::put('featured_merch', 'cached');

    $this->postJson('/stripe/webhook', ['type' => $type, 'data' => ['object' => ['id' => $id, 'active' => false]]])->assertOk();

    expect(Product::count())->toBe(1)
        ->and(Cache::get('featured_merch'))->toBe('cached');
})->with([
    'product' => ['product.deleted', 'prod_unlisted'],
    'price' => ['price.deleted', 'price_unlisted'],
]);
