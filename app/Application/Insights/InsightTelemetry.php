<?php

namespace App\Application\Insights;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class InsightTelemetry
{
    /** @param array<string, mixed> $insight */
    public function record(Request $request, User $user, array $insight, int $startedAtNanoseconds): void
    {
        $formula = $insight['formula'] ?? [];
        $categories = $insight['categories'] ?? [];
        $sources = $insight['sources'] ?? [];
        Log::info('insight.calculated', [
            'request_id' => $request->attributes->get('request_id'),
            'user_id' => $user->getKey(),
            'formula' => is_array($formula) ? ($formula['name'] ?? null) : null,
            'formula_version' => is_array($formula) ? ($formula['version'] ?? null) : null,
            'calculation_duration_ms' => round((hrtime(true) - $startedAtNanoseconds) / 1_000_000, 3),
            'category_count' => is_countable($categories) ? count($categories) : 0,
            'source_count' => is_countable($sources) ? count($sources) : 0,
        ]);
    }
}
