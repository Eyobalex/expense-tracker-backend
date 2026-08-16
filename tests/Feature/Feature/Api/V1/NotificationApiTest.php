<?php

use App\Application\Notifications\NotificationPreferences;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('notification preference normalization replaces the threshold list rather than retaining default entries', function (): void {
    $this->seedCurrencies();
    $user = User::factory()->create(['base_currency_code' => 'ETB', 'notification_preferences' => ['budget_thresholds' => [75, 100]]]);

    expect(app(NotificationPreferences::class)->normalized($user)['budget_thresholds'])->toBe([75, 100]);
});
