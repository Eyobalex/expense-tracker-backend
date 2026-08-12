<?php

use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\Route;

test('domain exceptions use the stable api error envelope', function (): void {
    Route::get('/api/v1/test-domain-error', function (): never {
        throw DomainException::for(DomainErrorCode::InvalidMoney, 'Money is invalid.');
    });

    $this->getJson('/api/v1/test-domain-error')
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'INVALID_MONEY')
        ->assertJsonPath('error.message', 'Money is invalid.')
        ->assertHeader('X-Request-Id');
});
