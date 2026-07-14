<?php

declare(strict_types=1);

use App\Domain\Money\Money;

it('constructs from minor units', function () {
    $m = new Money(1000, 'KWD');
    expect($m->amount)->toBe(1000)
        ->and($m->currency)->toBe('KWD');
});

it('converts a major-unit decimal to minor units', function () {
    // 10.500 KWD with 3 decimals → 10500 fils
    $m = Money::fromMajor(10.500, 'KWD', 3);
    expect($m->amount)->toBe(10500);

    // 5.25 USD with 2 decimals → 525 cents
    $m = Money::fromMajor(5.25, 'USD', 2);
    expect($m->amount)->toBe(525);
});

it('adds two Money values of same currency', function () {
    $a = new Money(1000, 'KWD');
    $b = new Money(500, 'KWD');
    expect($a->add($b)->amount)->toBe(1500);
});

it('throws on currency mismatch in arithmetic', function () {
    $a = new Money(1000, 'KWD');
    $b = new Money(500, 'USD');
    expect(fn () => $a->add($b))->toThrow(InvalidArgumentException::class, 'Currency mismatch');
});

it('computes a percentage correctly', function () {
    // 20% of 1000 minor units = 200
    $m = new Money(1000, 'USD');
    expect($m->percentage(20.0)->amount)->toBe(200);
});

it('formats with the right decimal places', function () {
    expect((new Money(10500, 'KWD'))->format(3))->toBe('10.500');
    expect((new Money(525, 'USD'))->format(2))->toBe('5.25');
});

it('is immutable — operations return new instances', function () {
    $a = new Money(1000, 'KWD');
    $b = $a->add(new Money(500, 'KWD'));

    expect($a->amount)->toBe(1000)  // unchanged
        ->and($b->amount)->toBe(1500); // new
});

it('rejects invalid currency codes', function () {
    expect(fn () => new Money(100, 'XX'))->toThrow(InvalidArgumentException::class);
});

it('treats zero as zero', function () {
    expect(Money::zero('KWD')->isZero())->toBeTrue()
        ->and(Money::zero('KWD')->amount)->toBe(0);
});
