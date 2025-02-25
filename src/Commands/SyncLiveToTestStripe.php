<?php

namespace Gnarhard\StripeStorefront\Commands;

use Gnarhard\StripeStorefront\Services\LiveStripeService;
use Gnarhard\StripeStorefront\Services\StripeSyncService;
use Gnarhard\StripeStorefront\Services\TestStripeService;
use Illuminate\Console\Command;

class SyncLiveToTestStripe extends Command
{
    protected $signature = 'products:sync-live-to-test {--dry-run}';

    protected $description = 'Sync all live Stripe products, prices, and coupons to the test environment.';

    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');

        // You can either load these from your package config or use env()
        $liveApiKey = config('stripe-storefront.stripe.live_secret');
        $testApiKey = config('stripe-storefront.stripe.test_secret');

        if (! $liveApiKey || ! $testApiKey) {
            $this->error('Stripe API keys are missing. Please set STRIPE_LIVE_SECRET and STRIPE_TEST_SECRET.');

            return;
        }

        $liveService = new LiveStripeService($liveApiKey);
        $testService = new TestStripeService($testApiKey);

        // Create a logger callback that writes to the console
        $logger = function ($message) {
            $this->line($message);
        };

        $syncService = new StripeSyncService($liveService, $testService, $logger);
        $syncService->sync($dryRun);
    }
}
