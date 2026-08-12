<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DomainExceptionApiTest extends TestCase
{
    public function test_domain_exceptions_use_the_stable_api_error_envelope(): void
    {
        Route::get('/api/v1/test-domain-error', function (): never {
            throw DomainException::for(DomainErrorCode::InvalidMoney, 'Money is invalid.');
        });

        $this->getJson('/api/v1/test-domain-error')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_MONEY')
            ->assertJsonPath('error.message', 'Money is invalid.')
            ->assertHeader('X-Request-Id');
    }
}
