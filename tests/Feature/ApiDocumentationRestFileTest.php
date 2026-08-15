<?php

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

test('the REST Client collection documents every implemented API v1 route', function (): void {
    $contents = file_get_contents(base_path('docs/api/expense-tracker.rest'));

    expect($contents)->not->toBeFalse();

    preg_match_all('/^(GET|POST|PUT|PATCH|DELETE) \{\{baseUrl}}(\/[^\s?]+)/m', (string) $contents, $matches, PREG_SET_ORDER);
    $documented = collect($matches)
        ->map(fn (array $match): string => $match[1].' api/v1'.$match[2])
        ->map(fn (string $signature): string => (string) preg_replace('/\{\{[^}]+}}|\{[^}]+}/', '{}', $signature))
        ->values();
    $implemented = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route): bool => str_starts_with($route->uri(), 'api/v1/'))
        ->flatMap(fn (LaravelRoute $route): Collection => collect($route->methods())
            ->reject(fn (string $method): bool => $method === 'HEAD')
            ->map(fn (string $method): string => $method.' '.$route->uri()))
        ->map(fn (string $signature): string => (string) preg_replace('/\{[^}]+}/', '{}', $signature))
        ->values();

    expect($implemented->diff($documented)->values())->toBeEmpty();
});

test('the REST Client collection supplies an idempotency key for every documented mutation', function (): void {
    $sections = preg_split('/^###/m', (string) file_get_contents(base_path('docs/api/expense-tracker.rest')));

    expect($sections)->not->toBeFalse();

    foreach ($sections as $section) {
        if (preg_match('/^(POST|PUT|PATCH|DELETE) \{\{baseUrl}}/m', $section) !== 1) {
            continue;
        }

        expect($section)->toContain('Idempotency-Key: {{$guid}}');
    }
});
