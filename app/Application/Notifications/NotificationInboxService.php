<?php

namespace App\Application\Notifications;

use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Collection;

final class NotificationInboxService
{
    /** @return Collection<int, UserNotification> */
    public function inbox(User $user, bool $unreadOnly, int $limit): Collection
    {
        return UserNotification::query()
            ->ownedBy($user)
            ->where('in_app_enabled', true)
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->with('deliveries')
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function markRead(User $user, UserNotification $notification, int $expectedVersion): UserNotification
    {
        if ($notification->user_id !== $user->getKey()) {
            throw DomainException::for(DomainErrorCode::ResourceNotFound, 'The requested notification was not found.');
        }
        $updated = UserNotification::query()
            ->ownedBy($user)
            ->whereKey($notification->getKey())
            ->where('version', $expectedVersion)
            ->update(['read_at' => now(), 'version' => $expectedVersion + 1]);
        if ($updated !== 1) {
            throw DomainException::for(DomainErrorCode::ConcurrencyConflict, 'The notification version is stale.');
        }

        /** @var UserNotification $fresh */
        $fresh = $notification->fresh(['deliveries']);

        return $fresh;
    }
}
