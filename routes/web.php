<?php

namespace Gnarhard\StripeStorefront;

use Gnarhard\StripeStorefront\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::prefix('store')->name('store.')->group(function () {
        Route::get('/products/{category}', [ProductController::class, 'showCategory'])->name('category');
        Route::get('/products/{category}/{product:slug}', [ProductController::class, 'showProduct'])->name('product.show');

        Route::get('/checkout', [ProductController::class, 'showCheckout'])->name('checkout');
        Route::get('/cancel', [ProductController::class, 'cancel'])->name('cancel');
        Route::get('/thank-you', [ProductController::class, 'thankYou'])->name('thank-you');
        Route::get('/download', [ProductController::class, 'download'])->name('download');
    });
});
