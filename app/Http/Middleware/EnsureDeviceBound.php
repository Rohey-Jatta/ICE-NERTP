<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\Device;
use App\Services\DeviceBindingService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeviceBound
{
    private const CONTACT_ADMIN_FOR_DEVICE = 'Please contact the IEC Administrator for help with device registration.';

    public function __construct(
        private readonly DeviceBindingService $deviceService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return ApiResponse::error('You need to login to continue.', 'UNAUTHENTICATED', [], 401);
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

            return ApiResponse::error(
                'No registered device was found for this account. Please log in again to register this device, or contact the IEC Administrator if the problem continues.',
                'DEVICE_NOT_FOUND',
                [],
                403
            );
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

            return ApiResponse::error(
                'This account is already registered to another device. ' . self::CONTACT_ADMIN_FOR_DEVICE,
                'DEVICE_MISMATCH',
                [],
                403
            );
        }

        $device->recordUsage($request->ip());

        return $next($request);
    }
}