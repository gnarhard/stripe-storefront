<?php

namespace Gnarhard\StripeStorefront\Listeners;

use Gnarhard\StripeStorefront\Events\OrderCreated;
use Illuminate\Support\Facades\Mail;
use Gnarhard\StripeStorefront\Models\Product;
use Gnarhard\StripeStorefront\Mail\NewOrder;
use Gnarhard\StripeStorefront\Models\Customer;

class SendNewOrderEmail
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
        Mail::to(config('mail.from.address'))->send(new NewOrder($event->product, $event->customer));
    }
}
