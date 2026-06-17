<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\Device;
use App\Services\DeviceBindingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeviceBound
{
    public function __construct(
        private readonly DeviceBindingService $deviceService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $role = $user->getRoleNames()->first();

        if (!Device::roleRequiresBinding($role)) {
            return $next($request);
        }

        // Use the server-side fingerprint (not a cookie)
        $fingerprint = $this->deviceService->deriveServerFingerprint($request);

        $device = Device::where('user_id', $user->id)
            ->where('is_revoked', false)
            ->whereNotNull('verified_at')
            ->first();

        if (!$device) {
            // No device registered — this shouldn't happen post-login but handle gracefully
            AuditLog::record(
                action: 'auth.device.no_binding',
                event: 'blocked',
                module: 'Authentication',
                extra: [
                    'outcome'        => 'blocked',
                    'failure_reason' => 'No registered device found for user',
                    'user_id'        => $user->id,
                ]
            );

            return response()->json([
                'message' => 'No registered device found. Please log in again.',
                'code'    => 'DEVICE_NOT_FOUND',
            ], 403);
        }

        if (!hash_equals($device->device_fingerprint, $fingerprint)) {
            AuditLog::record(
                action: 'auth.device.mismatch_request',
                event: 'blocked',
                module: 'Authentication',
                extra: [
                    'outcome'        => 'blocked',
                    'failure_reason' => 'Request from unregistered device',
                    'user_id'        => $user->id,
                ]
            );

            return response()->json([
                'message' => 'This account is already registered to another device. Please contact the IEC Administrator for assistance.',
                'code'    => 'DEVICE_MISMATCH',
            ], 403);
        }

        $device->recordUsage($request->ip());

        return $next($request);
    }
}