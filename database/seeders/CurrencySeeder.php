<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'ETB', 'display_name' => 'Ethiopian Birr', 'symbol' => 'Br', 'minor_unit_exponent' => 2],
            ['code' => 'USD', 'display_name' => 'US Dollar', 'symbol' => '$', 'minor_unit_exponent' => 2],
            ['code' => 'EUR', 'display_name' => 'Euro', 'symbol' => '€', 'minor_unit_exponent' => 2],
            ['code' => 'GBP', 'display_name' => 'Pound Sterling', 'symbol' => '£', 'minor_unit_exponent' => 2],
            ['code' => 'KES', 'display_name' => 'Kenyan Shilling', 'symbol' => 'KSh', 'minor_unit_exponent' => 2],
            ['code' => 'JPY', 'display_name' => 'Japanese Yen', 'symbol' => '¥', 'minor_unit_exponent' => 0],
        ] as $currency) {
            Currency::query()->updateOrCreate(['code' => $currency['code']], $currency + ['is_active' => true]);
        }
    }
}
