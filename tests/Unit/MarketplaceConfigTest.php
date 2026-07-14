<?php

declare(strict_types=1);

it('loads the marketplace config with the locked decisions', function () {
    expect(config('marketplace.default_currency'))->toBe('KWD')
        ->and(config('marketplace.supported_currencies'))->toContain('KWD', 'USD', 'AED', 'PKR')
        ->and(config('marketplace.supported_locales'))->toContain('en', 'ar')
        ->and(config('marketplace.guest_browsing'))->toBeTrue()
        ->and(config('marketplace.guest_checkout'))->toBeFalse()
        ->and(config('marketplace.earnings_release_days'))->toBe(7)
        ->and(config('marketplace.default_commissions.basic'))->toBe(30.0)
        ->and(config('marketplace.default_commissions.standard'))->toBe(20.0)
        ->and(config('marketplace.default_commissions.professional'))->toBe(10.0);
});
