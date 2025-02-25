<?php

return [
    'downloads-storage-disk' => env('STRIPE_STOREFRONT_DOWNLOADS_STORAGE_DISK', 'product-downloads'),
    'products-storage-disk' => env('STRIPE_STOREFRONT_PRODUCTS_STORAGE_DISK', 'products'),
    'stripe' => [
        'live_secret' => env('STRIPE_LIVE_SECRET'),
        'test_secret' => env('STRIPE_TEST_SECRET'),
    ]
];
