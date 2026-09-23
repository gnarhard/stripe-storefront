<?php

use Gnarhard\StripeStorefront\Models\Price;
use Gnarhard\StripeStorefront\Models\Product;

function stripeCatalogFixtures(): array
{
    return [
        'GET /v1/products' => [200, ['object' => 'list', 'data' => [
            [
                'id' => 'prod_poster', 'object' => 'product', 'name' => 'Karma Poster', 'description' => 'A poster',
                'active' => true, 'default_price' => 'price_poster', 'metadata' => ['category' => 'merch'],
                'images' => ['https://example.com/poster.webp'],
            ],
            [
                'id' => 'prod_draft', 'object' => 'product', 'name' => 'Draft Product', 'active' => true,
                'default_price' => null, 'metadata' => [], 'images' => [],
            ],
        ]]],
        'GET /v1/prices' => [200, ['object' => 'list', 'data' => [
            ['id' => 'price_poster', 'object' => 'price', 'unit_amount' => 1500, 'type' => 'one_time'],
        ]]],
        'POST /v1/payment_links' => [200, ['id' => 'plink_poster', 'object' => 'payment_link', 'url' => 'https://buy.stripe.com/poster']],
    ];
}

it('replaces the local catalog with active Stripe products', function () {
    $stale = Product::create(['stripe_id' => 'prod_old', 'name' => 'Old', 'slug' => 'old']);
    Price::create([
        'stripe_id' => 'price_old', 'product_id' => $stale->id, 'unit_amount' => 100, 'type' => 'one_time',
        'payment_link_id' => 'plink_old', 'payment_link' => 'https://buy.stripe.com/old',
    ]);

    $stripe = $this->fakeStripe(stripeCatalogFixtures());

    $this->artisan('products:add-to-db')
        ->expectsOutput('Synced Karma Poster.')
        ->expectsOutput('Skipped Draft Product because it has no default price.')
        ->assertSuccessful();

    $product = Product::sole();

    expect($product->only(['stripe_id', 'slug', 'name', 'description']))->toBe([
        'stripe_id' => 'prod_poster',
        'slug' => 'karma-poster',
        'name' => 'Karma Poster',
        'description' => 'A poster',
    ])
        ->and($product->metadata)->toBe(['category' => 'merch'])
        ->and($product->image_urls)->toBe(['https://example.com/poster.webp'])
        ->and($product->price->only(['stripe_id', 'unit_amount', 'type', 'payment_link', 'payment_link_id']))->toBe([
            'stripe_id' => 'price_poster',
            'unit_amount' => 1500,
            'type' => 'one_time',
            'payment_link' => 'https://buy.stripe.com/poster',
            'payment_link_id' => 'plink_poster',
        ])
        ->and($stripe->lastRequestTo('POST', '/v1/payment_links')['params'])
        ->toBe(['line_items' => [['price' => 'price_poster', 'quantity' => 1]]]);
});
