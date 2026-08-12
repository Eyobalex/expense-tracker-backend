<?php

namespace App\Domain\Shared\Audit;

final class AuditPayloadRedactor
{
    /** @var list<string> */
    private const SensitiveKeys = [
        'authorization',
        'cookie',
        'password',
        'password_confirmation',
        'token',
        'plain_text_token',
        'raw_ocr_response',
        'receipt_image',
        'image',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function redact(array $payload): array
    {
        $redacted = [];

        foreach ($payload as $key => $value) {
            $normalizedKey = strtolower($key);
            $redacted[$key] = in_array($normalizedKey, self::SensitiveKeys, true)
                ? '[REDACTED]'
                : (is_array($value) ? $this->redact($value) : $value);
        }

        return $redacted;
    }
}
