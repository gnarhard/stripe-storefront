<?php

namespace Gnarhard\StripeStorefront\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $guarded = [];

    protected $casts = [
        'address' => 'array',
    ];

    public function getAddressFormattedAttribute(): string
    {
        return $this->address['line1'].', '.$this->address['line2'].', '.$this->address['city'].', '.$this->address['state'].' '.$this->address['postal_code'].', '.$this->address['country'];
    }

    public function getFirstNameAttribute(): string
    {
        return explode(' ', $this->name)[0];
    }

    public function getLastNameAttribute(): string
    {
        return explode(' ', $this->name)[1];
    }
}
