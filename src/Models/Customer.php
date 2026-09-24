<?php

namespace Gnarhard\StripeStorefront\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stripe has no name or address for a 100%-off order, so both can be null.
 *
 * @property string|null $name
 * @property string $email
 * @property array|null $address
 */
class Customer extends Model
{
    protected $guarded = [];

    protected $casts = [
        'address' => 'array',
    ];

    public function getAddressFormattedAttribute(): ?string
    {
        if ($this->address === null) {
            return null;
        }

        return $this->address['line1'].', '.$this->address['line2'].', '.$this->address['city'].', '.$this->address['state'].' '.$this->address['postal_code'].', '.$this->address['country'];
    }

    public function getFirstNameAttribute(): ?string
    {
        return $this->name === null ? null : explode(' ', $this->name)[0];
    }

    public function getLastNameAttribute(): ?string
    {
        return explode(' ', $this->name ?? '')[1] ?? null;
    }
}
