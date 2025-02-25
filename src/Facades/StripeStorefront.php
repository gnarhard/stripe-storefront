<?php

namespace Gnarhard\StripeStorefront\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Grayson Erhard\StripeStorefront\StripeStorefront
 */
class StripeStorefront extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Gnarhard\StripeStorefront\StripeStorefront::class;
    }
}
