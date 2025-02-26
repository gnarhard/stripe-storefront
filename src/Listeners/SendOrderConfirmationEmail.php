<?php

namespace Gnarhard\StripeStorefront\Listeners;

use Gnarhard\StripeStorefront\Events\OrderCreated;
use Gnarhard\StripeStorefront\Mail\OrderConfirmation;
use Illuminate\Support\Facades\Mail;

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
