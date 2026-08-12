<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyOperation;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || $key === '' || Str::length($key) > 255) {
            return $this->error($request, 'IDEMPOTENCY_KEY_REQUIRED', 'An Idempotency-Key header is required.');
        }

        $user = $request->user();
        $scopeKey = $user !== null
            ? 'user:'.$user->getKey()
            : 'public:'.hash('sha256', $request->ip().'|'.(string) $request->input('email', ''));
        $lock = Cache::lock('idempotency:'.$scopeKey.':'.hash('sha256', $request->method().'|'.$request->path().'|'.$key), 30);

        if (! $lock->get()) {
            return $this->error($request, 'IDEMPOTENCY_IN_PROGRESS', 'The operation is already in progress.', Response::HTTP_CONFLICT);
        }

        try {
            $requestHash = hash('sha256', (string) $request->getContent());
            $operation = IdempotencyOperation::query()
                ->where('scope_key', $scopeKey)
                ->where('idempotency_key', $key)
                ->where('method', $request->method())
                ->where('path', $request->path())
                ->first();

            if ($operation !== null) {
                if (! hash_equals($operation->request_hash, $requestHash)) {
                    return $this->error($request, 'IDEMPOTENCY_KEY_REUSED', 'The Idempotency-Key was already used with a different request.', Response::HTTP_CONFLICT);
                }

                if ($operation->completed_at !== null) {
                    return response()->json($operation->response_body, $operation->status_code)->header('Idempotency-Replayed', 'true');
                }

                return $this->error($request, 'IDEMPOTENCY_IN_PROGRESS', 'The operation is already in progress.', Response::HTTP_CONFLICT);
            }

            $operation = DB::transaction(fn (): IdempotencyOperation => IdempotencyOperation::query()->create([
                'user_id' => $user?->getKey(),
                'scope_key' => $scopeKey,
                'idempotency_key' => $key,
                'method' => $request->method(),
                'path' => $request->path(),
                'request_hash' => $requestHash,
            ]));
            $response = $next($request);

            if ($response instanceof JsonResponse) {
                $operation->forceFill([
                    'status_code' => $response->getStatusCode(),
                    'response_body' => $response->getData(true),
                    'completed_at' => now(),
                ])->save();
            }

            return $response;
        } catch (\Throwable $throwable) {
            isset($operation) && $operation->delete();

            throw $throwable;
        } finally {
            $lock->release();
        }
    }

    private function error(Request $request, string $code, string $message, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'fields' => (object) [],
                'request_id' => $request->attributes->get('request_id'),
            ],
        ], $status);
    }
}
