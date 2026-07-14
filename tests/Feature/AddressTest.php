<?php

declare(strict_types=1);

use App\Models\Address;
use App\Models\User;

it('creates an address for a user', function () {
    $user = User::factory()->create();

    $address = Address::create([
        'user_id'    => $user->id,
        'label'      => 'Home',
        'type'       => 'shipping',
        'country'    => 'KW',
        'city'       => 'Kuwait City',
        'area'       => 'Salmiya',
        'block'      => '10',
        'street'     => 'Salem Mubarak St',
        'building'   => '24',
        'phone'      => '+96599999999',
        'is_default' => true,
    ]);

    expect($address->id)->not->toBeNull()
        ->and($user->addresses)->toHaveCount(1);
});

it('enforces a single default address per user', function () {
    $user = User::factory()->create();

    $a = Address::create([
        'user_id'    => $user->id,
        'country'    => 'KW',
        'city'       => 'Kuwait City',
        'is_default' => true,
    ]);

    $b = Address::create([
        'user_id'    => $user->id,
        'country'    => 'KW',
        'city'       => 'Hawalli',
        'is_default' => true,
    ]);

    $a->refresh();
    $b->refresh();

    expect($b->is_default)->toBeTrue()
        ->and($a->is_default)->toBeFalse();
});

it('soft-deletes addresses', function () {
    $user = User::factory()->create();
    $address = Address::create([
        'user_id' => $user->id,
        'country' => 'KW',
        'city'    => 'Kuwait City',
    ]);

    $address->delete();

    expect(Address::find($address->id))->toBeNull()
        ->and(Address::withTrashed()->find($address->id))->not->toBeNull();
});

it('builds a readable full address line', function () {
    $address = new Address([
        'block'    => '4',
        'street'   => 'Gulf Road',
        'area'     => 'Salmiya',
        'city'     => 'Kuwait City',
        'country'  => 'KW',
        'building' => 'Tower 2',
    ]);

    $line = $address->fullAddressLine();
    expect($line)->toContain('Tower 2')
        ->and($line)->toContain('Gulf Road')
        ->and($line)->toContain('Salmiya');
});
