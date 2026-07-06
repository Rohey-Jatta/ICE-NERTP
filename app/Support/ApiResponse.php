<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * ApiResponse - standardized success/failure envelope for JSON responses.
 *
 * success:
 *   { "success": true, "message": "...", "data": {...} }
 *
 * failure:
 *   { "success": false, "message": "human readable", "error_code": "VALIDATION_ERROR", "fields": {...} }
 */
class ApiResponse
{
    public static function success(string $message = 'Operation completed successfully', array $data = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    public static function error(
        string $message,
        string $errorCode = 'ERROR',
        array $fields = [],
        int $status = 422
    ): JsonResponse {
        return response()->json([
            'success'    => false,
            'message'    => $message,
            'error_code' => $errorCode,
            'fields'     => $fields,
        ], $status);
    }

    /** Map common HTTP statuses to a friendly default message. */
    public static function friendlyMessageForStatus(int $status): string
    {
        return match (true) {
            $status === 401 => 'You need to login to continue.',
            $status === 403 => 'You don\'t have permission to perform this action. Please contact your administrator.',
            $status === 404 => 'The requested resource could not be found.',
            $status === 409 => 'This action could not be completed because of a conflicting change. Please refresh and try again.',
            $status === 419 => 'Your session has expired. Please login again.',
            $status === 422 => 'Some of the information provided is invalid. Please check the form and try again.',
            $status === 429 => 'Too many attempts. Please wait a moment and try again.',
            $status >= 500  => 'Something went wrong on our end. Please try again, and contact support if the problem continues.',
            default         => 'The request could not be completed.',
        };
    }
}