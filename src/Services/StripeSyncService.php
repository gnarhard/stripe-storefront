<?php

namespace Gnarhard\StripeStorefront\Services;

class StripeSyncService
{
    private LiveStripeService $liveService;

    private TestStripeService $testService;

    /** @var callable */
    private $logger;

    public function __construct(
        LiveStripeService $liveService,
        TestStripeService $testService,
        callable $logger
    ) {
        $this->liveService = $liveService;
        $this->testService = $testService;
        $this->logger = $logger;
    }

    /**
     * Sync live Stripe data to test environment.
     */
    public function sync(bool $dryRun = false): void
    {
        $log = $this->logger;

        if ($dryRun) {
            $log('*** DRY RUN MODE: No changes will be made ***');
        } else {
            $log('Archiving all existing test prices, products, and coupons...');
            // Must archive and not delete because Stripe does not allow deleting objects.
            $this->testService->archiveAllProducts();
            $this->testService->deleteAllCoupons();
        }

        $liveProducts = $this->liveService->getProducts();
        $liveCoupons = $this->liveService->getCoupons();
        $log('Found '.count($liveProducts).' products and '.count($liveCoupons).' coupons.');

        // Sync products and their prices.
        foreach ($liveProducts as $liveProduct) {
            if ($dryRun) {
                $log('Would clone product: '.$liveProduct->name);
            } else {
                $testProduct = $this->testService->createProduct([
                    'name' => $liveProduct->name,
                    'description' => $liveProduct->description,
                    'active' => $liveProduct->active,
                    'metadata' => $liveProduct->metadata,
                    'images' => $liveProduct->images,
                    'shippable' => $liveProduct->shippable ?? null,
                    'url' => $liveProduct->url ?? null,
                    'tax_code' => $liveProduct->tax_code ?? null,
                    // 'marketing_features' => $liveProduct->marketing_features ?? [], // creates invalid object :(
                ]);
                $log('Cloned product: '.$testProduct->name);

                // Sync prices for this product.
                $prices = $this->liveService->getProductPrices($liveProduct->id);
                foreach ($prices as $price) {
                    if ($dryRun) {
                        $log('  → Would clone price: '.$price->unit_amount.' '.$price->currency);
                    } else {
                        $newPrice = $this->testService->createPrice([
                            'product' => $testProduct->id,
                            'unit_amount' => $price->unit_amount,
                            'currency' => $price->currency,
                            'recurring' => $price->recurring ? [
                                'interval' => $price->recurring->interval,
                                'interval_count' => $price->recurring->interval_count,
                            ] : null,
                        ]);
                        $log('  → Cloned price: '.$newPrice->unit_amount.' '.$newPrice->currency);
                    }
                }
            }
        }

        // Sync coupons.
        foreach ($liveCoupons as $liveCoupon) {
            if ($dryRun) {
                $couponInfo = $liveCoupon->percent_off
                    ? ($liveCoupon->percent_off.'% off')
                    : ('$'.$liveCoupon->amount_off.' off');
                $log('Would clone coupon: '.$liveCoupon->id." ({$couponInfo})");
            } else {
                $testCoupon = $this->testService->createCoupon([
                    'id' => $liveCoupon->id, // Keep the same coupon ID
                    'amount_off' => $liveCoupon->amount_off,
                    'percent_off' => $liveCoupon->percent_off,
                    'currency' => $liveCoupon->currency,
                    'duration' => $liveCoupon->duration,
                    'duration_in_months' => $liveCoupon->duration_in_months ?? null,
                    'max_redemptions' => $liveCoupon->max_redemptions,
                    'redeem_by' => $liveCoupon->redeem_by,
                    'metadata' => $liveCoupon->metadata,
                ]);
                $log('Cloned coupon: '.$testCoupon->id);
            }
        }

        $log('Sync complete.');
    }
}
