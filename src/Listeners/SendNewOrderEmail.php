<?php

namespace Gnarhard\StripeStorefront\Listeners;

use Illuminate\Support\Facades\Mail;
use Stripe\Customer;
use Gnarhard\StripeStorefront\Models\Product;
use Gnarhard\StripeStorefront\Mail\NewOrder;

class SendNewOrderEmail
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
        Mail::to(config('mail.from.address'))->send(new NewOrder($this->product, $this->customer));
    }
}
