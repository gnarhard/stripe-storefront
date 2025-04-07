<?php

use Gnarhard\StripeStorefront\Events\WebhookHandled;
use Gnarhard\StripeStorefront\Events\WebhookReceived;
use Gnarhard\StripeStorefront\Http\Controllers\WebhookController;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

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
