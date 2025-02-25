<?php

namespace Gnarhard\StripeStorefront\Services;

use Stripe\Coupon;
use Stripe\Price;
use Stripe\Product;
use Stripe\StripeClient;

class TestStripeService
{
    protected StripeClient $client;

    public function __construct(string $testApiKey)
    {
        $this->client = new StripeClient($testApiKey);
    }

    /**
     * Archive all test products.
     */
    public function archiveAllProducts(): void
    {
        $products = $this->client->products->all(['limit' => 100]);
        foreach ($products->data as $product) {
            // Archive instead of delete by updating the product
            $this->client->products->update($product->id, ['active' => false]);
        }
    }

    /**
     * Delete all test coupons.
     */
    public function deleteAllCoupons(): void
    {
        $coupons = $this->client->coupons->all(['limit' => 100]);
        foreach ($coupons->data as $coupon) {
            $this->client->coupons->delete($coupon->id, []);
        }
    }

    /**
     * Create a product in the test environment.
     */
    public function createProduct(array $data): Product
    {
        unset($data['active']);
        $data = $this->prepare($data);

        return $this->client->products->create($data);
    }

    /**
     * Create a price in the test environment.
     */
    public function createPrice(array $data): Price
    {
        $data = $this->prepare($data);
        $price = $this->client->prices->create($data);
        // set as default price for product
        $this->client->products->update($data['product'], ['default_price' => $price->id]);

        return $price;
    }

    /**
     * Create a coupon in the test environment.
     */
    public function createCoupon(array $data): Coupon
    {
        $data = $this->prepare($data);

        return $this->client->coupons->create($data);
    }

    /**
     * Prepare data for Stripe API.
     */
    public function prepare(array $data): array
    {
        // get array of key/value pairs from metadata
        // add a new key/value pair to the metadata array
        if (isset($data['metadata'])) {
            $data['metadata'] = collect($data['metadata'])->toArray();
        }

        // unset any fields that have null values or empty strings
        return array_filter($data, fn ($value) => $value !== null && $value !== '');
    }
}
