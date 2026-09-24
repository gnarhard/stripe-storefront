<?php

namespace Gnarhard\StripeStorefront\Commands;

use Gnarhard\StripeStorefront\Facades\StripeStorefront;
use Gnarhard\StripeStorefront\Models\Price;
use Gnarhard\StripeStorefront\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AddToDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:add-to-db';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronizes Stripe products to the database using the Stripe API.';

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $stripe = StripeStorefront::getClient();
        // Fetch every page before touching the database so a failed request
        // never leaves the store with a partially deleted catalog.
        $stripe_products = iterator_to_array($stripe->products->all(['active' => true, 'limit' => 100])->autoPagingIterator(), false);
        $stripe_prices = collect(iterator_to_array($stripe->prices->all(['active' => true, 'limit' => 100])->autoPagingIterator(), false))
            ->keyBy('id');

        $syncable = [];

        foreach ($stripe_products as $stripeProduct) {
            if ($stripeProduct->active === false) {
                $this->warn('Skipped '.$stripeProduct->name.'.');

                continue;
            }

            if (! $stripeProduct->default_price) {
                $this->warn('Skipped '.$stripeProduct->name.' because it has no default price.');

                continue;
            }

            $stripe_price = $stripe_prices->get($stripeProduct->default_price);

            if (! $stripe_price) {
                $this->warn('Skipped '.$stripeProduct->name.' because its default price is not active.');

                continue;
            }

            $syncable[] = [$stripeProduct, $stripe_price];
        }

        $this->delete_all();

        foreach ($syncable as [$stripeProduct, $stripe_price]) {
            $product = $this->save_product($stripeProduct);

            $this->save_price($product, $stripe_price);

            $this->info('Synced '.$product->name.'.');
        }

        // Bust all cache
        Cache::clear();
    }

    private function save_product($stripeProduct): Product
    {
        return Product::create([
            'stripe_id' => $stripeProduct->id,
            'slug' => Str::slug($stripeProduct->name ?? 'Untitled product'),
            'name' => $stripeProduct->name ?? 'Untitled product',
            'description' => $stripeProduct->description ?? '',
            'metadata' => $stripeProduct->metadata ?? [],
            'image_urls' => $stripeProduct->images ?? [],
        ]);
    }

    private function save_price(Product $product, \Stripe\Price $stripe_price): void
    {
        Price::updateOrCreate([
            'stripe_id' => $stripe_price->id,
            'product_id' => $product->id,
            'unit_amount' => $stripe_price->unit_amount, // nullable for name your own price products
            'type' => $stripe_price->type,
        ]);
    }

    public function delete_all(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            Price::truncate();
            Product::truncate();
        });
    }
}
