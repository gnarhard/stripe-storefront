<?php

namespace Feature;

use Gnarhard\MailingList\Jobs\SignUpForMailingList;
use Gnarhard\StripeStorefront\Mail\NewOrder;
use Gnarhard\StripeStorefront\Mail\OrderConfirmation;
use Gnarhard\StripeStorefront\Models\Product;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(WithFaker::class);

beforeEach(function () {
    Artisan::call('sync:products');
});

it('can load the single product page', function ($slug, $category) {
    $product = Product::where('slug', $slug)->first();

    $this->get(route('store.product', ['category' => $category, $product]))
        ->assertOk()
        ->assertSee($product->name)
        ->assertSee('BUY');
})->with([
    ['tablature-collection', 'merch'],
    ['plantable-collection', 'merch'],
    ['karma-poster', 'merch'],
    ['single-lesson', 'lessons'],
    ['lesson-4-pack', 'lessons'],
]);

test('downloadable products have filename metadata', function ($slug) {
    $product = Product::where('slug', $slug)->first();

    expect($product->metadata['filename'])->not->toBeNull();
})->with([
    'tablature-collection',
    'plantable-collection',
    'karma-poster',
]);

it('can load the product category page', function ($category) {
    $this->get(route('store.category', ['category' => $category]))
        ->assertOk()
        ->assertSee('FEATURED');
})->with(['lessons', 'merch']);

it('can get a plantable collection', function () {
    $this->get(route('give-plantable-collection'))->assertOk();

    $product = Product::where('slug', 'plantable-collection')->first();

    Livewire::test('password-protected-plantable-collection', ['product' => $product])
        ->set('password', 'thankyou')
        ->assertSee('Your support is appreciated')
        ->assertSee('download');

    $this->get(route('store.download', ['product' => $product]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/zip')
        ->assertDownload();
});

test('checking out a product redirects to Stripe Checkout page', function ($slug) {
    $product = Product::where('slug', $slug)->first();

    $this->get(route('store.checkout', ['product' => $product]))
        ->assertRedirect();
})->with([
    'tablature-collection',
    'karma-poster',
    'plantable-collection',
    'single-lesson',
    'lesson-4-pack',
]);

it('cannot see product success page if session_id is not present', function () {
    $product = Product::factory()->create();

    $response = $this->get(route('store.success', ['product' => $product]).'&session_id=');
    $response->assertRedirect();
});

it('can see product success page', function ($slug) {
    Mail::fake();
    Queue::fake();

    $product = Product::where('slug', $slug)->first();
    $session_id = $this->faker->uuid();

    $response = $this->get(route('store.success', ['product' => $product]).'&session_id='.$session_id);
    $response
        ->assertOk()
        ->assertSee($product->name)
        ->assertSee('Your purchase was successful.');

    $products_with_downloads = ['tablature-collection', 'karma-poster', 'plantable-collection'];

    if (in_array($slug, $products_with_downloads)) {
        $response->assertSee('download');
    } else {
        $response->assertDontSee('download');
    }

    Mail::assertQueued(OrderConfirmation::class);
    Mail::assertQueued(NewOrder::class);

    $this->assertDatabaseHas(
        'orders',
        [
            'stripe_session_id' => $session_id,
            'email' => 'test@example.com',
        ]
    );

    Queue::assertPushed(SignUpForMailingList::class);
})->with([
    'tablature-collection',
    'karma-poster',
    'plantable-collection',
    'single-lesson',
    'lesson-4-pack',
]);

it('can see product success page without zip code', function () {
    Mail::fake();
    Queue::fake();

    $product = Product::where('slug', 'tablature-collection')->first();
    $session_id = $this->faker->uuid();

    $response = $this->get(route('store.success', ['product' => $product]).'&session_id='.$session_id);
    $response
        ->assertOk()
        ->assertSee($product->name)
        ->assertSee('Your purchase was successful.');

    $response->assertSee('download');

    Mail::assertQueued(OrderConfirmation::class);
    Mail::assertQueued(NewOrder::class);

    $this->assertDatabaseHas(
        'orders',
        [
            'stripe_session_id' => $session_id,
            'email' => 'test@example.com',
        ]
    );

    Queue::assertPushed(SignUpForMailingList::class);
});

it('can download product downloads', function ($slug) {
    $product = Product::where('slug', $slug)->first();

    $this->get(route('store.download', ['product' => $product]))->assertDownload();
})->with([
    'tablature-collection',
    'karma-poster',
    'plantable-collection',
]);

it('aborts when trying to download products without digital downloads', function ($slug) {
    $product = Product::where('slug', $slug)->first();

    $this->get(route('store.download', ['product' => $product]))->assertStatus(404);
})->with([
    'single-lesson',
    'lesson-4-pack',
]);
