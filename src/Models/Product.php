<?php

namespace Gnarhard\StripeStorefront\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Product extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
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
