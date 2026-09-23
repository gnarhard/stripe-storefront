<?php

namespace Gnarhard\StripeStorefront\Tests;

use Gnarhard\StripeStorefront\StripeStorefrontServiceProvider;
use Gnarhard\StripeStorefront\Tests\Support\FakeStripeHttpClient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Stripe\ApiRequestor;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Gnarhard\\StripeStorefront\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            StripeStorefrontServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        config()->set('stripe-storefront.stripe.test_secret', 'sk_test_fake');
        config()->set('stripe-storefront.stripe.live_secret', 'sk_live_fake');
    }

    protected function defineDatabaseMigrations(): void
    {
        foreach (['products', 'prices', 'orders', 'customers'] as $table) {
            (include __DIR__."/../database/migrations/create_{$table}_table.php.stub")->up();
        }
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

    /**
     * Route all Stripe SDK traffic through a fake HTTP client.
     *
     * @param  array<string, array{0: int, 1: array<string, mixed>}>  $responses
     */
    protected function fakeStripe(array $responses = []): FakeStripeHttpClient
    {
        $client = new FakeStripeHttpClient($responses);

        ApiRequestor::setHttpClient($client);

        return $client;
    }
}
