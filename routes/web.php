<?php

namespace Gnarhard\StripeStorefront;

use Illuminate\Support\Facades\Route;
use Gnarhard\StripeStorefront\Http\Controllers\ProductController;


Route::prefix('store')->name('store.')->group(function () {
    Route::get('/products/{category}', [ProductController::class, 'showCategory'])->name('category');
    Route::get('/products/{category}/{product:slug}', [ProductController::class, 'showProduct'])->name('product');

    Route::get('/checkout', [ProductController::class, 'showCheckout'])->name('checkout');
    Route::get('/cancel', [ProductController::class, 'cancel'])->name('cancel');
    Route::get('/thank-you', [ProductController::class, 'thankYou'])->name('thank-you');
    Route::get('/download', [ProductController::class, 'download'])->name('download');
});
