<?php

namespace Gnarhard\StripeStorefront\Listeners;

use Gnarhard\StripeStorefront\Mail\OrderConfirmation;
use Gnarhard\StripeStorefront\Models\Product;
use Illuminate\Support\Facades\Mail;
use Stripe\Customer;

class SendOrderConfirmationEmail
{
    /**
     * Create the event listener.
     */
    public function __construct(public Product $product, public Customer $customer)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        Mail::to($this->customer->email)->send(new OrderConfirmation($this->product));
    }
}
