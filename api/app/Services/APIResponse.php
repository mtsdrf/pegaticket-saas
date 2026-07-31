<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Request;

class APIResponse
{
    public static function success(
        mixed $data = null,
        string $message = '',
        int $status = 200,
        array $meta = []
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'meta'    => (object) array_merge($meta, [
                'request_id' => Request::header('X-Request-Id'),
            ]),
        ], $status);
    }

    public static function error(
        string $message,
        int $status = 400,
        string $code = 'ERROR',
        array $errors = [],
        array $meta = []
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => $code,
            'errors' => $errors ?: (object) [],
            'meta' => $meta ?: (object) [],
        ], $status);
    }
}