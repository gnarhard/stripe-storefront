<?php

namespace Gnarhard\StripeStorefront\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Stripe\StripeClient getClient()
 *
 * @see \Gnarhard\StripeStorefront\StripeStorefront
 */
class StripeStorefront extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Gnarhard\StripeStorefront\StripeStorefront::class;
    }
}
