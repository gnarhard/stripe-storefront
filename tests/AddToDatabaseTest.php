<?php

use Gnarhard\StripeStorefront\Models\Price;
use Gnarhard\StripeStorefront\Models\Product;
use Stripe\Exception\InvalidRequestException;

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
        ->expectsOutput('Skipped Draft Product because it has no default price.')
        ->expectsOutput('Synced Karma Poster.')
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

it('follows Stripe pagination for products and prices', function () {
    $stripe = $this->fakeStripe([
        'GET /v1/products' => [200, ['object' => 'list', 'url' => '/v1/products', 'has_more' => false, 'data' => [
            ['id' => 'prod_poster', 'object' => 'product', 'name' => 'Karma Poster', 'active' => true,
                'default_price' => 'price_on_page_two', 'metadata' => [], 'images' => []],
        ]]],
        'GET /v1/prices' => [200, ['object' => 'list', 'url' => '/v1/prices', 'has_more' => true, 'data' => [
            ['id' => 'price_on_page_one', 'object' => 'price', 'unit_amount' => 100, 'type' => 'one_time'],
        ]]],
        'POST /v1/payment_links' => [200, ['id' => 'plink', 'object' => 'payment_link', 'url' => 'https://buy.stripe.com/x']],
    ]);

    $stripe->respondToNextPage('GET /v1/prices', [200, ['object' => 'list', 'url' => '/v1/prices', 'has_more' => false, 'data' => [
        ['id' => 'price_on_page_two', 'object' => 'price', 'unit_amount' => 1500, 'type' => 'one_time'],
    ]]]);

    $this->artisan('products:add-to-db')->assertSuccessful();

    expect(Product::sole()->price->stripe_id)->toBe('price_on_page_two')
        ->and($stripe->lastRequestTo('GET', '/v1/prices')['params'])->toMatchArray(['starting_after' => 'price_on_page_one']);
});

it('skips products whose default price is not active instead of emptying the store', function () {
    $this->fakeStripe(array_merge(stripeCatalogFixtures(), [
        'GET /v1/prices' => [200, ['object' => 'list', 'data' => []]],
    ]));

    $this->artisan('products:add-to-db')
        ->expectsOutput('Skipped Karma Poster because its default price is not active.')
        ->assertSuccessful();

    expect(Product::count())->toBe(0);
});

it('keeps the existing catalog when Stripe fails mid-sync', function () {
    Product::create(['stripe_id' => 'prod_old', 'name' => 'Old', 'slug' => 'old']);

    $fixtures = stripeCatalogFixtures();
    unset($fixtures['POST /v1/payment_links']);
    $this->fakeStripe($fixtures);

    expect(fn () => $this->artisan('products:add-to-db')->run())->toThrow(InvalidRequestException::class);

    expect(Product::pluck('slug')->all())->toBe(['old']);
});
