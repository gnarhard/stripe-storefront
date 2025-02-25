<?php

namespace Gnarhard\StripeStorefront;

use Stripe\StripeClient;

class StripeStorefront
{
    private StripeClient $client;

    public function __construct(public string $apiKey)
    {
        $this->client = new StripeClient($this->apiKey);
    }

    public function getClient(): StripeClient
    {
        return $this->client;
    }
}
