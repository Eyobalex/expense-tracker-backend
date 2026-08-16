<?php

namespace App\Jobs;

use App\Application\Notifications\NotificationEvaluationService;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EvaluateUserNotifications implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    public int $uniqueFor = 60;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public int $userId, public ?string $requestId = null) {}

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function handle(NotificationEvaluationService $notifications): void
    {
        $user = User::query()->find($this->userId);
        if ($user instanceof User) {
            $notifications->evaluate($user, $this->requestId);
        }
    }

    public function failed(\Throwable $exception): void
    {
        if (User::query()->whereKey($this->userId)->exists()) {
            AuditEvent::query()->create([
                'user_id' => $this->userId,
                'event_name' => 'notification.evaluation_failed',
                'aggregate_type' => 'user',
                'aggregate_id' => (string) $this->userId,
                'request_id' => $this->requestId,
                'summary' => ['failure_code' => class_basename($exception)],
            ]);
        }
    }
}
