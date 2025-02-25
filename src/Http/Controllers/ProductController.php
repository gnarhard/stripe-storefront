<?php

namespace Gnarhard\StripeStorefront\Http\Controllers;

use Exception;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Gnarhard\StripeStorefront\Events\OrderCreated;
use Gnarhard\StripeStorefront\Events\OrderFailed;
use Gnarhard\StripeStorefront\Facades\StripeStorefront;
use Gnarhard\StripeStorefront\Models\{
    Order,
    Product
};
use Illuminate\Http\{
    RedirectResponse,
    Request
};
use Illuminate\Support\Facades\{
    Cache,
    Log,
    Storage
};

class ProductController extends Controller
{
    public function showCategory(Request $request, string $category): View
    {
        if (view()->exists("pages.store.category.index")) {
            $featured_product = Cache::remember('featured_' . $category, 60 * 24, function () use ($category) {
                return Product::where('metadata->category', $category)->featured()->first();
            });

            $products = Cache::remember('unfeatured_' . $category, 60 * 24, function () use ($category) {
                return Product::where('metadata->category', $category)->unfeatured()->get();
            });

            return view("pages.store.category.index", [
                'products'         => $products,
                'featured_product' => $featured_product,
                'category'         => $category,
            ]);
        } else {
            return view("errors.404");
        }
    }

    public function showProduct(Request $request, string $category, string $product_slug): View
    {
        $product = Product::where('slug', $product_slug)->firstOrFail();

        if ($product->metadata['category'] !== $category) {
            return view("errors.404");
        }

        return view("pages.store.show", ['product' => $product]);
    }


    public function showCheckout(Request $request)
    {
        $request->validate([
            'discount_code_id' => 'string',
            'product'          => 'string|required|exists:products,slug',
        ]);

        $product = Product::where('slug', $request->input('product'))->firstOrFail();

        // Build the checkout session parameters
        $sessionData = [
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price'    => $product->price->stripe_id,
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => route('store.thank-you', ['product' => $product->slug]) . '&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => route('store.product', [
                'category' => $product->metadata['category'],
                'product'  => $product->slug
            ]),
            'customer_creation' => 'always',
        ];

        // Optionally, if a discount code is provided, add it to the session.
        if ($request->filled('discount_code_id')) {
            $sessionData['discounts'] = [
                ['coupon' => $request->input('discount_code_id')]
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
        // Retrieve the product by ID (or adjust as needed)
        $product = Product::where('slug', $request->get('product'))->firstOrFail();
        $sessionId = $request->get('session_id');

        if (!$sessionId) {
            return $this->showCheckoutError($product, new \Exception('No session ID provided.'));
        }

        $customer = null;

        if (app()->environment('testing')) {
            // Provide dummy customer details for testing
            $customer = (object)[
                'email'   => 'test@example.com',
                'zip'     => '12345',
                'country' => 'US',
            ];
        } else {
            try {
                // Retrieve the Checkout Session using the provided session ID
                $session = StripeStorefront::getClient()->checkout->sessions->retrieve($sessionId);

                if (!empty($session->customer)) {
                    // Retrieve the customer information using the customer ID from the session
                    $customer = StripeStorefront::getClient()->customers->retrieve($session->customer);
                }
            } catch (\Exception $e) {
                return $this->showCheckoutError($product, $e);
            }
        }

        // Create an order if one doesn't exist for this session
        if (!Order::where('stripe_session_id', $sessionId)->exists()) {
            Order::create([
                'stripe_session_id' => $sessionId,
                'email'             => $customer->email,
            ]);

            event(new OrderCreated($product, $customer));
        }

        return view('pages.store.thank-you', [
            'product'  => $product,
            'customer' => $customer,
            'order'    => Order::where('stripe_session_id', $sessionId)->first(),
        ]);
    }

    private function showCheckoutError(Product $product, ?Exception $e): RedirectResponse
    {
        event(new OrderFailed($e));

        session()->flash('error', 'There was an error processing your payment. Please try again.');

        return redirect()->route('store.product', ['category' => $product->metadata['category'], $product->slug]);
    }

    public function download(): BinaryFileResponse
    {
        $product = Product::where('slug', request('product'))->firstOrFail();

        if (!isset($product->metadata['filename'])) {
            abort(404);
        }

        if (!Storage::disk(config('stripe-storefront.downloads-storage-disk'))->exists($product->metadata['filename'])) {
            abort(404);
        }

        return response()->download(Storage::disk(config('stripe-storefront.downloads-storage-disk'))->path($product->metadata['filename']));
    }

    public function promo_code_exists(string $promoCode): bool
    {
        try {
            $coupon = StripeStorefront::getClient()->coupons->retrieve($promoCode, []);

            return $coupon && $coupon->valid;
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return false;
        }
    }
}
