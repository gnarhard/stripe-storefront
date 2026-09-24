<?php

use Gnarhard\StripeStorefront\Models\Customer;

it('splits the name into first and last names', function (?string $name, ?string $firstName, ?string $lastName) {
    $customer = new Customer(['name' => $name]);

    expect($customer->first_name)->toBe($firstName)
        ->and($customer->last_name)->toBe($lastName);
})->with([
    'first and last' => ['Jane Doe', 'Jane', 'Doe'],
    'one word' => ['Cher', 'Cher', null],
    'no name, as on a 100%-off order' => [null, null, null],
]);

it('formats the address', function () {
    $customer = new Customer(['address' => [
        'line1' => '1 Main St', 'line2' => 'Apt 2', 'city' => 'Denver', 'state' => 'CO', 'postal_code' => '80202', 'country' => 'US',
    ]]);

    expect($customer->address_formatted)->toBe('1 Main St, Apt 2, Denver, CO 80202, US');
});

it('has no formatted address when Stripe gave no address', function () {
    expect((new Customer)->address_formatted)->toBeNull();
});
