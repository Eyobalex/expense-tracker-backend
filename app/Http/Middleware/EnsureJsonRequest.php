<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureJsonRequest
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->expectsJson() && ! $request->isJson()) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_ACCEPTABLE',
                    'message' => 'The API requires an Accept: application/json header.',
                    'fields' => (object) [],
                    'request_id' => $request->attributes->get('request_id'),
                ],
            ], Response::HTTP_NOT_ACCEPTABLE);
        }

        return $next($request);
    }
}
