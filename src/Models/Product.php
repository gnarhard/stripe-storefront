<?php

namespace Gnarhard\StripeStorefront\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\URL;

/**
 * @property string $stripe_id
 * @property string $name
 * @property string $slug
 * @property array|null $metadata
 * @property-read Price|null $price
 */
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

    /**
     * A non-expiring signed link to this product's download, so emailed links keep working. The
     * signature leaves out the host so the link survives proxies and alternate domains.
     */
    public function downloadUrl(): string
    {
        return url(URL::signedRoute('store.download', ['product' => $this], absolute: false));
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
