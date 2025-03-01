<?php

namespace Gnarhard\StripeStorefront\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebhookHandled
{
    use Dispatchable, SerializesModels;

    /**
     * The webhook payload.
     *
     * @var array
     */
    public $payload;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }
}
