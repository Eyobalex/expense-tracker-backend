<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the Swagger-compatible UI and OpenAPI document expose the versioned API contract', function (): void {
    $this->get('/docs/api')->assertOk()->assertSee('Next-Gen Financial Tracker API');

    $document = $this->getJson('/docs/api.json')->assertOk()->json();

    expect($document)
        ->toHaveKey('openapi')
        ->and($document['openapi'])->toStartWith('3.1.')
        ->and(data_get($document, 'info.version'))->toBe('1.0.0')
        ->and(data_get($document, 'components.securitySchemes.http.scheme'))->toBe('bearer')
        ->and(data_get($document, 'paths./auth/login.post'))->toBeArray()
        ->and(data_get($document, 'paths./transactions.post'))->toBeArray()
        ->and(data_get($document, 'paths./receipts.post.requestBody.content.multipart/form-data'))->toBeArray();
});

test('OpenAPI documentation remains disabled when its explicit access setting is disabled', function (): void {
    config()->set('api_docs.enabled', false);

    $this->getJson('/docs/api.json')->assertForbidden();
});
