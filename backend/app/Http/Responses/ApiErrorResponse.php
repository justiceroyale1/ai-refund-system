<?php

namespace App\Http\Responses;

use App\Enums\Http\ApiErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use stdClass;

final class ApiErrorResponse
{
    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, string>  $headers
     */
    public static function make(
        ApiErrorCode|string $code,
        string $message,
        array $details = [],
        int $status = 500,
        array $headers = [],
    ): JsonResponse {
        return response()->json([
            'error' => [
                'code' => $code instanceof ApiErrorCode ? $code->value : $code,
                'message' => $message,
                'details' => $details === [] ? new stdClass : $details,
            ],
        ], $status, $headers);
    }

    public static function shouldRender(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}
