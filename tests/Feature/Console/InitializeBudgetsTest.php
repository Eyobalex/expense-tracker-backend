<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seedCurrencies();
});

test('monthly budget scheduler is idempotent', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB', 'budget_timezone' => 'UTC']);
    Category::factory()->for($user)->create([
        'kind' => 'expense', 'budget_enabled' => true, 'base_limit_minor_units' => 5000,
        'budget_currency_code' => 'ETB',
    ]);

    $this->artisan('budget:initialize', ['--month' => '2026-08'])->assertSuccessful();
    $this->artisan('budget:initialize', ['--month' => '2026-08'])->assertSuccessful();

    $this->assertDatabaseCount('budget_periods', 1);
});
