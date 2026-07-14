<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\CurrencyRate;
use Illuminate\Database\Seeder;

class CurrenciesSeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'KWD', 'name' => 'Kuwaiti Dinar',   'symbol' => 'KD',  'decimal_places' => 3, 'is_default' => true,  'sort_order' => 1],
            ['code' => 'USD', 'name' => 'US Dollar',       'symbol' => '$',   'decimal_places' => 2, 'is_default' => false, 'sort_order' => 2],
            ['code' => 'AED', 'name' => 'UAE Dirham',      'symbol' => 'AED', 'decimal_places' => 2, 'is_default' => false, 'sort_order' => 3],
            ['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => '₨',   'decimal_places' => 2, 'is_default' => false, 'sort_order' => 4],
        ];

        foreach ($currencies as $c) {
            Currency::updateOrCreate(['code' => $c['code']], $c + ['is_active' => true]);
        }

        // Initial cross-rates (approximate — admin updates these later).
        // Source: typical ~mid-2024 rates. NOT live. Admin/cron job replaces them.
        $rates = [
            // base, target, rate (1 base = X target)
            ['KWD', 'USD', 3.25],
            ['KWD', 'AED', 11.95],
            ['KWD', 'PKR', 905.00],
            ['USD', 'KWD', 0.308],
            ['USD', 'AED', 3.67],
            ['USD', 'PKR', 278.50],
            ['AED', 'USD', 0.272],
            ['AED', 'KWD', 0.0836],
            ['AED', 'PKR', 75.85],
            ['PKR', 'USD', 0.00359],
            ['PKR', 'KWD', 0.00110],
            ['PKR', 'AED', 0.01318],
        ];

        $now = now();
        foreach ($rates as [$base, $target, $rate]) {
            CurrencyRate::updateOrCreate(
                [
                    'base_currency'   => $base,
                    'target_currency' => $target,
                    'effective_at'    => $now,
                ],
                ['rate' => $rate, 'source' => 'seeded'],
            );
        }

        $this->command?->info('Seeded 4 currencies (KWD default) and 12 initial exchange rates.');
    }
}
