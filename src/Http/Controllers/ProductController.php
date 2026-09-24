<?php

namespace Gnarhard\StripeStorefront\Http\Controllers;

use Exception;
use Gnarhard\StripeStorefront\Events\OrderCancelled;
use Gnarhard\StripeStorefront\Events\OrderCreated;
use Gnarhard\StripeStorefront\Events\OrderFailed;
use Gnarhard\StripeStorefront\Facades\StripeStorefront;
use Gnarhard\StripeStorefront\Models\Customer;
use Gnarhard\StripeStorefront\Models\Order;
use Gnarhard\StripeStorefront\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Stripe\Checkout\Session;
use Stripe\Exception\InvalidRequestException;
use Stripe\LineItem;

class ProductController extends Controller
{
    public function showCategory(Request $request, string $category): View
    {
        if (view()->exists('pages.store.category.index')) {
            $featured_product = Cache::remember('featured_'.$category, 60 * 24, function () use ($category) {
                return Product::where('metadata->category', $category)->featured()->first();
            });

            $products = Cache::remember('unfeatured_'.$category, 60 * 24, function () use ($category) {
                return Product::where('metadata->category', $category)->unfeatured()->get();
            });

            return view('pages.store.category.index', [
                'products' => $products,
                'featured_product' => $featured_product,
                'category' => $category,
            ]);
        } else {
            return view('errors.404');
        }
    }

    public function showProduct(Request $request, string $category, Product $product): View
    {
        if (! $product->metadata || $product->metadata['category'] !== $category) {
            return view('errors.404');
        }

        return view('pages.store.show', ['product' => $product]);
    }

    public function showCheckout(Request $request)
    {
        $request->validate([
            'discount_code_id' => 'string',
            'product' => 'string|required|exists:products,slug',
        ]);

        $product = Product::where('slug', $request->input('product'))->firstOrFail();

        // Build the checkout session parameters
        $sessionData = [
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $product->price->stripe_id,
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => route('store.thank-you', ['product' => $product->slug]).'&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('store.product.show', [
                'category' => $product->metadata['category'],
                'product' => $product->slug,
            ]),
        ];

        if ($request->filled('discount_code_id') && $this->couponAppliesTo($request->input('discount_code_id'), $product)) {
            $sessionData['discounts'] = [
                ['coupon' => $request->input('discount_code_id')],
            ];
        } else {
            $sessionData['allow_promotion_codes'] = true;
        }

        // Create the Stripe Checkout Session
        $checkoutSession = StripeStorefront::getClient()->checkout->sessions->create($sessionData);

        // Redirect the user to the checkout page
        return redirect($checkoutSession->url);
    }

    public function thankYou(Request $request): View|RedirectResponse
    {
        $request->validate([
            'product' => 'string|required|exists:products,slug',
        ]);

        // Retrieve the product by ID (or adjust as needed)
        $product = Product::where('slug', $request->input('product'))->firstOrFail();
        $sessionId = $request->input('session_id');

        if (! $sessionId) {
            return $this->showCheckoutError($product, new Exception('No session ID provided.'));
        }

        $customer = null;

        if (app()->environment('testing')) {
            $customer = Customer::updateOrCreate([
                'email' => 'test@example.com',
            ], [
                'name' => 'Test Testerson',
                'email' => 'test@example.com',
                'address' => [
                    'postal_code' => '12345',
                    'country' => 'US',
                ],
            ]);
        } else {
            try {
                // Retrieve the Checkout Session using the provided session ID
                $session = StripeStorefront::getClient()->checkout->sessions->retrieve($sessionId, ['expand' => ['line_items']]);

                if (! $this->paidFor($session, $product)) {
                    return $this->showCheckoutError($product, new Exception("Checkout session {$sessionId} is not a paid order for {$product->slug}."));
                }

                $details = $session->customer_details;

                // A 100%-off order collects no payment method, so Stripe has no name or address for it.
                // Skipping the gaps also keeps what a returning customer gave on an earlier order.
                $customer = Customer::updateOrCreate(
                    ['email' => $details->email],
                    array_filter([
                        'name' => $details->name,
                        'phone' => $details->phone,
                        'address' => $details->address?->toArray(),
                    ], fn ($value) => $value !== null),
                );
            } catch (Exception $e) {
                return $this->showCheckoutError($product, $e);
            }
        }

        $order = Order::updateOrCreate(
            [
                'stripe_session_id' => $sessionId,
            ],
            [
                'stripe_session_id' => $sessionId,
                'email' => $customer->email,
                'total' => app()->environment('testing') ? 69 : $session->amount_total,
            ]
        );

        OrderCreated::dispatch($product, $customer, $order);

        return view('pages.store.thank-you', [
            'product' => $product,
            'customer' => $customer,
            'order' => $order,
        ]);
    }

    /**
     * The thank-you page records the order and hands out the download link, so a session paid for one
     * product must not unlock another through a swapped slug. Anything but unpaid counts, per Stripe's
     * fulfillment guide, so 100% discounts pass.
     */
    private function paidFor(Session $session, Product $product): bool
    {
        return $session->payment_status !== 'unpaid'
            && collect($session->line_items?->data)->contains(fn (LineItem $item) => $item->price?->product === $product->stripe_id);
    }

    /**
     * Anyone can edit a checkout link, so its coupon only counts when Stripe limits the coupon to the
     * product being bought. A coupon without that limit would discount every product in the store.
     */
    private function couponAppliesTo(string $couponId, Product $product): bool
    {
        try {
            $coupon = StripeStorefront::getClient()->coupons->retrieve($couponId, ['expand' => ['applies_to']]);
        } catch (InvalidRequestException) {
            $coupon = null;
        }

        $applies = $coupon?->valid === true && in_array($product->stripe_id, $coupon->applies_to->products ?? [], true);

        if (! $applies) {
            Log::warning('Checkout ignored a coupon that is unknown, no longer valid, or not limited to the product.', [
                'coupon' => $couponId,
                'product' => $product->slug,
            ]);
        }

        return $applies;
    }

    private function showCheckoutError(Product $product, ?Exception $e): View
    {
        event(new OrderFailed($e));

        return view('pages.store.order-failed');
    }

    public function download(Request $request): RedirectResponse
    {
        // Only the query string is signed; input() would let a JSON body pick another product.
        $product = Product::where('slug', $request->query('product'))->firstOrFail();

        if (! isset($product->metadata['filename'])) {
            abort(404);
        }

        if (! Storage::disk(config('stripe-storefront.downloads-storage-disk'))->exists('downloads/'.$product->metadata['filename'])) {
            abort(404);
        }

        return redirect(Storage::disk('r2')->temporaryUrl('downloads/'.$product->metadata['filename'], now()->addMinutes(10)));
    }

    public function cancel(Product $product)
    {
        event(new OrderCancelled);

        return redirect('/');
    }
}
