<?php

namespace Gnarhard\StripeStorefront\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Price extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function getAmountAttribute(): float
    {
        if (is_null($this->attributes['unit_amount'])) {
            return 00.00;
        }

        return $this->attributes['unit_amount'] / 100;
    }
}
