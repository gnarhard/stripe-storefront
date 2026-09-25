<?php

namespace Gnarhard\StripeStorefront\Http\Controllers;

use Gnarhard\StripeStorefront\Events\WebhookHandled;
use Gnarhard\StripeStorefront\Events\WebhookReceived;
use Gnarhard\StripeStorefront\Http\Middleware\VerifyWebhookSignature;
use Gnarhard\StripeStorefront\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Stripe\Stripe;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    /**
     * Create a new WebhookController instance.
     *
     * @return void
     */
    public function __construct()
    {
        if (config('stripe-storefront.stripe.webhook.secret')) {
            $this->middleware(VerifyWebhookSignature::class);
        }
    }

    /**
     * Handle a Stripe webhook call.
     *
     * @return Response
     */
    public function handle(Request $request)
    {
        $payload = json_decode($request->getContent(), true);
        $method = 'handle'.Str::studly(str_replace('.', '_', $payload['type']));

        WebhookReceived::dispatch($payload);

        if (method_exists($this, $method)) {
            $this->setMaxNetworkRetries();

            $response = $this->{$method}($payload);

            WebhookHandled::dispatch($payload);

            return $response;
        }

        return $this->missingMethod($payload);
    }

    protected function handleProductUpdated(array $payload): Response
    {
        if (! $payload['data']['object']['active']) {
            $this->removeFromStore(Product::firstWhere('stripe_id', $payload['data']['object']['id']));
        }

        return $this->successMethod();
    }

    protected function handleProductDeleted(array $payload): Response
    {
        $this->removeFromStore(Product::firstWhere('stripe_id', $payload['data']['object']['id']));

        return $this->successMethod();
    }

    protected function handlePriceUpdated(array $payload): Response
    {
        if (! $payload['data']['object']['active']) {
            $this->removeFromStore(Product::whereRelation('price', 'stripe_id', $payload['data']['object']['id'])->first());
        }

        return $this->successMethod();
    }

    protected function handlePriceDeleted(array $payload): Response
    {
        $this->removeFromStore(Product::whereRelation('price', 'stripe_id', $payload['data']['object']['id'])->first());

        return $this->successMethod();
    }

    /**
     * Stripe refuses to check out an archived product or price, so drop it rather than wait for the next
     * products:add-to-db. Only removal is handled here: a full resync truncates the catalog, which is unsafe
     * to run for each of the several events one dashboard edit sends.
     */
    private function removeFromStore(?Product $product): void
    {
        if (! $product) {
            return;
        }

        $product->price()->delete();
        $product->delete();

        $category = $product->metadata['category'] ?? null;
        Cache::forget('featured_'.$category);
        Cache::forget('unfeatured_'.$category);
    }

    /**
     * Handle successful calls on the controller.
     *
     * @param  array  $parameters
     * @return Response
     */
    protected function successMethod($parameters = [])
    {
        return new Response('Webhook Handled', 200);
    }

    /**
     * Handle calls to missing methods on the controller.
     *
     * @param  array  $parameters
     * @return Response
     */
    protected function missingMethod($parameters = [])
    {
        return new Response;
    }

    /**
     * Set the number of automatic retries due to an object lock timeout from Stripe.
     *
     * @param  int  $retries
     * @return void
     */
    protected function setMaxNetworkRetries($retries = 3)
    {
        Stripe::setMaxNetworkRetries($retries);
    }
}
