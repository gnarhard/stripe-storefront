<?php

namespace Gnarhard\StripeStorefront\Services;

use Stripe\StripeClient;

class LiveStripeService
{
    protected StripeClient $client;

    public function __construct(string $liveApiKey)
    {
        $this->client = new StripeClient($liveApiKey);
    }

    /**
     * Retrieve active live products.
     */
    public function getProducts(): array
    {
        $products = $this->client->products->all([
            'limit' => 100,
            'active' => true,
        ]);

        return $products->data;
    }

    /**
     * Retrieve prices for a given product.
     */
    public function getProductPrices(string $productId): array
    {
        $prices = $this->client->prices->all([
            'product' => $productId,
            'limit' => 100,
        ]);

        return $prices->data;
    }

    /**
     * Retrieve live coupons.
     */
    public function getCoupons(): array
    {
        $coupons = $this->client->coupons->all([
            'limit' => 100,
        ]);

        return $coupons->data;
    }
}
