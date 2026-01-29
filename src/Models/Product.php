<?php

namespace Gnarhard\StripeStorefront\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'image_urls' => 'array',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function price(): HasOne
    {
        return $this->hasOne(Price::class);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('metadata->featured', 1);
    }

    public function scopeUnfeatured(Builder $query): Builder
    {
        return $query->where('metadata->featured', null)->orWhere('metadata->featured', '!=', 1);
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('metadata->category', $category);
    }
}
