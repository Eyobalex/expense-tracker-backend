<?php

namespace App\Application\Notifications;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;
use App\Models\User;

final class NotificationPreferences
{
    /** @return array<string, mixed> */
    public function normalized(User $user): array
    {
        /** @var array<string, mixed> $stored */
        $stored = $user->notification_preferences ?? [];

        return $this->merge($stored);
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    public function update(User $user, array $changes): array
    {
        /** @var array<string, mixed> $stored */
        $stored = $user->notification_preferences ?? [];
        $preferences = $this->merge(array_replace_recursive($stored, $changes));
        $user->forceFill(['notification_preferences' => $preferences])->save();

        return $preferences;
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    public function merged(User $user, array $changes): array
    {
        /** @var array<string, mixed> $stored */
        $stored = $user->notification_preferences ?? [];

        return $this->merge(array_replace_recursive($stored, $changes));
    }

    public function channelEnabled(User $user, NotificationType $type, NotificationChannel $channel): bool
    {
        $preferences = $this->normalized($user);

        return $preferences['enabled']
            && $preferences['types'][$type->value]
            && $preferences['channels'][$channel->value];
    }

    /** @return list<int> */
    public function budgetThresholds(User $user): array
    {
        /** @var list<int> $thresholds */
        $thresholds = $this->normalized($user)['budget_thresholds'];

        return $thresholds;
    }

    /**
     * @param  array<string, mixed>  $preferences
     * @return array<string, mixed>
     */
    private function merge(array $preferences): array
    {
        $defaults = [
            'enabled' => true,
            'channels' => array_fill_keys(array_map(fn (NotificationChannel $channel): string => $channel->value, NotificationChannel::cases()), true),
            'types' => array_fill_keys(array_map(fn (NotificationType $type): string => $type->value, NotificationType::cases()), true),
            'budget_thresholds' => [50, 75, 90, 100],
        ];
        $merged = array_replace_recursive($defaults, $preferences);
        $configuredThresholds = $preferences['budget_thresholds'] ?? $defaults['budget_thresholds'];
        $thresholds = array_values(array_unique(array_map('intval', is_array($configuredThresholds) ? $configuredThresholds : [])));
        sort($thresholds);

        return [...$merged, 'budget_thresholds' => $thresholds];
    }
}
