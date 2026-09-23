<?php

use Gnarhard\StripeStorefront\Services\LiveStripeService;
use Gnarhard\StripeStorefront\Services\StripeSyncService;
use Gnarhard\StripeStorefront\Services\TestStripeService;

function liveStripeFixtures(): array
{
    return [
        'GET /v1/products' => [200, ['object' => 'list', 'data' => [[
            'id' => 'prod_live', 'object' => 'product', 'name' => 'Karma Poster', 'description' => 'A poster',
            'active' => true, 'metadata' => ['category' => 'merch'], 'images' => ['https://example.com/p.webp'],
        ]]]],
        'GET /v1/prices' => [200, ['object' => 'list', 'data' => [[
            'id' => 'price_live', 'object' => 'price', 'unit_amount' => 1500, 'currency' => 'usd', 'recurring' => null,
        ]]]],
        'GET /v1/coupons' => [200, ['object' => 'list', 'data' => [[
            'id' => 'FRIENDS', 'object' => 'coupon', 'percent_off' => 20, 'amount_off' => null, 'currency' => null,
            'duration' => 'once', 'max_redemptions' => null, 'redeem_by' => null, 'metadata' => [],
        ]]]],
        'POST /v1/products' => [200, ['id' => 'prod_test', 'object' => 'product', 'name' => 'Karma Poster']],
        'POST /v1/prices' => [200, ['id' => 'price_test', 'object' => 'price', 'unit_amount' => 1500, 'currency' => 'usd']],
        'POST /v1/products/prod_test' => [200, ['id' => 'prod_test', 'object' => 'product']],
        'POST /v1/coupons' => [200, ['id' => 'FRIENDS', 'object' => 'coupon']],
        'POST /v1/products/prod_live' => [200, ['id' => 'prod_live', 'object' => 'product', 'active' => false]],
        'DELETE /v1/coupons/FRIENDS' => [200, ['id' => 'FRIENDS', 'object' => 'coupon', 'deleted' => true]],
    ];
}

it('strips null and empty values before sending data to Stripe', function () {
    $prepared = (new TestStripeService('sk_test_fake'))->prepare([
        'name' => 'Poster',
        'description' => '',
        'url' => null,
        'metadata' => collect(['category' => 'merch']),
    ]);

    expect($prepared)->toBe(['name' => 'Poster', 'metadata' => ['category' => 'merch']]);
});

it('does not write to Stripe during a dry run', function () {
    $stripe = $this->fakeStripe(liveStripeFixtures());
    $log = [];

    (new StripeSyncService(
        new LiveStripeService('sk_live_fake'),
        new TestStripeService('sk_test_fake'),
        function (string $message) use (&$log) {
            $log[] = $message;
        },
    ))->sync(dryRun: true);

    expect(collect($stripe->requests)->where('method', '!=', 'GET'))->toBeEmpty()
        ->and($log)->toContain('Would clone product: Karma Poster')
        ->and($log)->toContain('Would clone coupon: FRIENDS (20% off)');
});

it('clones live products, prices and coupons into the test account', function () {
    $stripe = $this->fakeStripe(liveStripeFixtures());

    (new StripeSyncService(
        new LiveStripeService('sk_live_fake'),
        new TestStripeService('sk_test_fake'),
        fn () => null,
    ))->sync();

    $product = $stripe->lastRequestTo('POST', '/v1/products')['params'];
    $price = $stripe->lastRequestTo('POST', '/v1/prices')['params'];
    $coupon = $stripe->lastRequestTo('POST', '/v1/coupons')['params'];

    expect($product)->toMatchArray(['name' => 'Karma Poster', 'description' => 'A poster', 'metadata' => ['category' => 'merch']])
        ->and($product)->not->toHaveKey('active')
        ->and($price)->toBe(['product' => 'prod_test', 'unit_amount' => 1500, 'currency' => 'usd'])
        ->and($stripe->lastRequestTo('POST', '/v1/products/prod_test')['params'])->toBe(['default_price' => 'price_test'])
        ->and($coupon)->toMatchArray(['id' => 'FRIENDS', 'percent_off' => 20, 'duration' => 'once'])
        ->and($stripe->lastRequestTo('POST', '/v1/products/prod_live')['params'])->toBe(['active' => 'false'])
        ->and($stripe->lastRequestTo('DELETE', '/v1/coupons/FRIENDS'))->not->toBeNull();
});
