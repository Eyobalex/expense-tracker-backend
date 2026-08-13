<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * @param  array<string, mixed>|null  $data
     * @param  array<string, mixed>  $meta
     */
    protected function success(Request $request, ?array $data = null, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => (object) $meta,
            'links' => (object) [],
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    protected function error(Request $request, string $code, string $message, int $status, array $fields = []): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'fields' => (object) $fields,
                'request_id' => $request->attributes->get('request_id'),
            ],
        ], $status);
    }
}
