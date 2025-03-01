<?php

namespace Gnarhard\StripeStorefront\Commands;

use Gnarhard\StripeStorefront\Facades\StripeStorefront;
use Gnarhard\StripeStorefront\Models\Price;
use Gnarhard\StripeStorefront\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

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

    public $stripe;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->stripe = StripeStorefront::getClient();
        $stripe_products = $this->stripe->products->all(['active' => true])->data;
        $stripe_prices = $this->stripe->prices->all(['active' => true])->data;

        // Delete all products, prices, and product images.
        $this->delete_all();

        foreach ($stripe_products as $stripeProduct) {
            if ($stripeProduct->active === false) {
                $this->warn('Skipped '.$stripeProduct->name.'.');

                continue;
            }

            $product = $this->save_product($stripeProduct);

            $this->save_price($product, $stripe_prices, $stripeProduct->default_price);
            $this->save_media($product, $stripeProduct->images ?? []);

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
        ]);
    }

    private function save_price(Product $product, $stripe_prices, string $default_price): void
    {
        $stripe_price = collect($stripe_prices)->firstWhere('id', $default_price);

        $payment_link = $this->stripe->paymentLinks->create([
            'line_items' => [
                [
                    'price' => $stripe_price->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        Price::updateOrCreate([
            'stripe_id' => $stripe_price->id,
            'product_id' => $product->id,
            'unit_amount' => $stripe_price->unit_amount, // nullable for name your own price products
            'type' => $stripe_price->type,
            'payment_link' => $payment_link->url,
            'payment_link_id' => $payment_link->id,
        ]);
    }

    private function save_media(Product $product, array $images): void
    {
        foreach ($images as $image) {
            $product->addMediaFromUrl($image)->toMediaCollection('products', config('stripe-storefront.products-storage-disk'));
        }
    }

    public function delete_all(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Product::truncate();
        Price::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        DB::statement('ALTER TABLE products AUTO_INCREMENT = 1;');
        DB::statement('ALTER TABLE prices AUTO_INCREMENT = 1;');
        Storage::disk(config('stripe-storefront.products-storage-disk'))->deleteDirectory('./');
    }
}
