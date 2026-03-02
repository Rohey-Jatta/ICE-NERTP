<?php

namespace App\Http\Middleware;

use App\Services\GPSValidationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGpsValid
{
    public function __construct(
        private readonly GPSValidationService $gpsService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$user->hasRole('polling-officer')) {
            return $next($request);
        }

        $lat = $request->header('X-GPS-Latitude');
        $lng = $request->header('X-GPS-Longitude');
        $accuracy = (float) $request->header('X-GPS-Accuracy', 0);

        if (!$lat || !$lng) {
            return response()->json([
                'message' => 'GPS coordinates are required for this action.',
                'code' => 'GPS_REQUIRED',
            ], 403);
        }

        $result = $this->gpsService->validateOfficerLocation(
            $user,
            (float) $lat,
            (float) $lng,
            $accuracy
        );

        if (!$result['valid']) {
            return response()->json([
                'message' => $result['message'],
                'code' => 'GPS_VALIDATION_FAILED',
                'gps_code' => $result['code'],
                'data' => $result['data'],
            ], 403);
        }

        $request->merge(['_validated_gps' => $result['data']]);

        return $next($request);
    }
}
