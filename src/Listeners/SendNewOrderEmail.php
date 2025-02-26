<?php

namespace Gnarhard\StripeStorefront\Listeners;

use Gnarhard\StripeStorefront\Events\OrderCreated;
use Gnarhard\StripeStorefront\Mail\NewOrder;
use Illuminate\Support\Facades\Mail;

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
