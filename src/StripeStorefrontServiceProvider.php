<?php

namespace Gnarhard\StripeStorefront;

use Gnarhard\StripeStorefront\Commands\SyncLiveToTestStripe;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Gnarhard\StripeStorefront\Commands\AddToDatabase;
use Gnarhard\StripeStorefront\Events\OrderCreated;
use Gnarhard\StripeStorefront\Listeners\SendNewOrderEmail;
use Gnarhard\StripeStorefront\Listeners\SendOrderConfirmationEmail;
use Illuminate\Support\Facades\Event;

class StripeStorefrontServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('stripe-storefront')
            ->hasConfigFile()
            ->hasRoutes('web')
            ->hasCommands(AddToDatabase::class, SyncLiveToTestStripe::class);

        $this->app->bind(
            StripeStorefront::class,
            fn($app) => new StripeStorefront(app()->environment('production') ? config('stripe-storefront.stripe.live_secret') : config('stripe-storefront.stripe.test_secret')),
        );

        Event::listen(OrderCreated::class, SendNewOrderEmail::class);
        Event::listen(OrderCreated::class, SendOrderConfirmationEmail::class);
    }
}
