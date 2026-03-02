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

        $fingerprint = $this->deviceService->extractFingerprint($request);

        if (!$fingerprint) {
            return response()->json([
                'message' => 'Device not recognized. Please log in again.',
                'code' => 'DEVICE_NOT_FOUND',
            ], 403);
        }

        $device = Device::where('user_id', $user->id)
            ->where('device_fingerprint', $fingerprint)
            ->where('is_revoked', false)
            ->whereNotNull('verified_at')
            ->first();

        if (!$device) {
            AuditLog::record(
                action: 'auth.device.unbound_request',
                event: 'blocked',
                module: 'Authentication',
                extra: [
                    'outcome' => 'blocked',
                    'failure_reason' => 'Request from unregistered device',
                ]
            );

            return response()->json([
                'message' => 'Unrecognized device. Please complete device registration.',
                'code' => 'DEVICE_UNBOUND',
            ], 403);
        }

        $device->recordUsage($request->ip());

        return $next($request);
    }
}
