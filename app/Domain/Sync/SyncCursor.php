<?php

namespace App\Domain\Sync;

use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use Carbon\CarbonImmutable;
use JsonException;

final readonly class SyncCursor
{
    public function __construct(
        public CarbonImmutable $sinceAt,
        public CarbonImmutable $snapshotAt,
        public ?CarbonImmutable $lastChangedAt = null,
        public ?string $lastResourceType = null,
        public ?string $lastResourceId = null,
        public bool $fullResync = false,
    ) {}

    public function encode(): string
    {
        try {
            $payload = json_encode([
                'since_at' => $this->sinceAt->toIso8601String(),
                'snapshot_at' => $this->snapshotAt->toIso8601String(),
                'last_changed_at' => $this->lastChangedAt?->toIso8601String(),
                'last_resource_type' => $this->lastResourceType,
                'last_resource_id' => $this->lastResourceId,
                'full_resync' => $this->fullResync,
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw DomainException::for(DomainErrorCode::SyncCursorExpired, 'The synchronization cursor is invalid or has expired.');
        }

        $encodedPayload = self::base64UrlEncode($payload);
        $signature = hash_hmac('sha256', $encodedPayload, (string) config('app.key'));

        return $encodedPayload.'.'.$signature;
    }

    public static function decode(string $value): self
    {
        [$encodedPayload, $signature] = explode('.', $value, 2) + [null, null];
        if (! is_string($encodedPayload) || ! is_string($signature) || ! hash_equals(hash_hmac('sha256', $encodedPayload, (string) config('app.key')), $signature)) {
            throw DomainException::for(DomainErrorCode::SyncCursorExpired, 'The synchronization cursor is invalid or has expired.');
        }

        try {
            /** @var array{since_at?: string, snapshot_at?: string, last_changed_at?: string|null, last_resource_type?: string|null, last_resource_id?: string|null, full_resync?: bool} $payload */
            $payload = json_decode(self::base64UrlDecode($encodedPayload), true, 512, JSON_THROW_ON_ERROR);

            return new self(
                CarbonImmutable::parse($payload['since_at'] ?? throw new \UnexpectedValueException),
                CarbonImmutable::parse($payload['snapshot_at'] ?? throw new \UnexpectedValueException),
                isset($payload['last_changed_at']) ? CarbonImmutable::parse($payload['last_changed_at']) : null,
                $payload['last_resource_type'] ?? null,
                $payload['last_resource_id'] ?? null,
                (bool) ($payload['full_resync'] ?? false),
            );
        } catch (\Throwable) {
            throw DomainException::for(DomainErrorCode::SyncCursorExpired, 'The synchronization cursor is invalid or has expired.');
        }
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if (! is_string($decoded)) {
            throw new \UnexpectedValueException;
        }

        return $decoded;
    }
}
