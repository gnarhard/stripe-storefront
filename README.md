# Stripe Storefront

## Getting Started

This package depends on Spatie Media Library and Laravel Cashier in order to work. Please publish the migrations before proceeding.

## Installation

You can install the package via composer:

```bash
composer require gnarhard/stripe-storefront
```

Make sure you create two new storage disks:

```php
    'products' => [
        'driver'     => 'local',
        'root'       => storage_path('app/public/products'),
        'url'        => env('APP_URL').'/storage/products',
        'visibility' => 'public',
    ],

    'product_downloads' => [
        'driver' => 'local',
        'root'   => storage_path('app/public/downloads'),
        'url'    => env('APP_URL').'/storage/downloads',
    ],
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="migrations"
php artisan vendor:publish --tag="cashier-migrations"
php artisan vendor:publish --tag="stripe-storefront-migrations"
php artisan migrate
```

Add the following env vars:

```bash
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="stripe-storefront-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="stripe-storefront-views"
```

## Usage

Synchronize your live products to the test environment on Stripe using Stripe's PHP API.

```bash
php artisan products:sync-live-to-test
```

Synchronize the Stripe products to your database. In production it pulls from live, otherwise it pulls test products.

```bash
php artisan products:add-to-db
```

To share a checkout link with a discount, copy the unique discount code id and append it to the url like this:
`https://graysonerhard.com/store/checkout?product=plantable-collection&discount_code_id=WChg0TfU`

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

-   [Grayson Erhard](https://github.com/gnarhard)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
