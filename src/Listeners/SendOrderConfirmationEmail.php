<?php

namespace Gnarhard\StripeStorefront\Listeners;

use Gnarhard\StripeStorefront\Events\OrderCreated;
use Illuminate\Support\Facades\Mail;
use Gnarhard\StripeStorefront\Mail\OrderConfirmation;
use Gnarhard\StripeStorefront\Models\Customer;
use Gnarhard\StripeStorefront\Models\Product;

class SendOrderConfirmationEmail
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(OrderCreated $event): void
    {
        Mail::to($event->customer->email)->send(new OrderConfirmation($event->product));
    }
}
