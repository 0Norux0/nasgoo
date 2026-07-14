<?php

declare(strict_types=1);

use App\Domain\Settings\SettingsRepository;
use App\Models\Setting;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
    $this->repo = app(SettingsRepository::class);
});

it('seeds settings across all 10 groups', function () {
    $groups = Setting::distinct('group')->pluck('group')->toArray();
    foreach (['general', 'marketplace', 'currency', 'payment', 'shipping', 'commission', 'email', 'seo', 'social', 'security'] as $g) {
        expect($groups)->toContain($g);
    }
});

it('reads a seeded string setting via repository', function () {
    expect($this->repo->get('general', 'site_name'))->toBe('Marketplace');
});

it('reads a seeded boolean setting with correct type', function () {
    expect($this->repo->get('marketplace', 'guest_checkout'))->toBeFalse()
        ->and($this->repo->get('marketplace', 'guest_browsing'))->toBeTrue();
});

it('reads a seeded integer setting with correct type', function () {
    $value = $this->repo->get('marketplace', 'earnings_release_days');
    expect($value)->toBe(7)->and($value)->toBeInt();
});

it('reads a seeded array setting with correct type', function () {
    $enabled = $this->repo->get('currency', 'enabled');
    expect($enabled)->toBeArray()
        ->toContain('KWD')
        ->toContain('USD')
        ->toContain('AED')
        ->toContain('PKR');
});

it('writes a setting via repository and reads it back', function () {
    $this->repo->set('general', 'new_test_key', 'hello world');
    expect($this->repo->get('general', 'new_test_key'))->toBe('hello world');
});

it('returns default when setting does not exist', function () {
    expect($this->repo->get('nonexistent', 'key', 'fallback'))->toBe('fallback');
});
