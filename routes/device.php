<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {

    Route::get('/auth/device/verify', function () {
        $user    = Auth::user();
        $pending = app(\App\Services\DeviceBindingService::class)->getPendingDevice($user);
        return Inertia::render('Auth/DeviceVerification', [
            'device_info' => [
                'type'    => $pending['type']    ?? 'desktop',
                'os'      => $pending['os']      ?? 'Unknown',
                'browser' => $pending['browser'] ?? 'Unknown',
                'ip'      => request()->ip(),
            ],
        ]);
    })->name('device.verify');

    Route::post('/auth/device/register', function (Request $request) {
        $request->validate(['device_name' => 'required|string|max:100']);
        $user    = Auth::user();
        $service = app(\App\Services\DeviceBindingService::class);
        try {
            $service->registerDevice($user, $request, $request->device_name);
            $roleNames = $user->getRoleNames();
            $roleName = $roleNames?->first() ?? 'user';
            $redirectUrl = match ($roleName) {
                'polling-officer'       => '/officer/dashboard',
                'ward-approver'         => '/ward/dashboard',
                'constituency-approver' => '/constituency/dashboard',
                'admin-area-approver'   => '/admin-area/dashboard',
                'iec-chairman'          => '/chairman/dashboard',
                'iec-administrator'     => '/admin/dashboard',
                'party-representative'  => '/party/dashboard',
                'election-monitor'      => '/monitor/dashboard',
                default                 => '/',
            };
            return response()->json(['status' => 'authenticated', 'redirect_url' => $redirectUrl]);
        } catch (\Exception $e) {
            Log::error('Device registration failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            
            // User-friendly error messages
            $message = 'Device registration failed. Please try again or contact support.';
            if (str_contains($e->getMessage(), 'duplicate') || str_contains($e->getMessage(), 'unique')) {
                $message = 'This device is already registered. Please try logging in again.';
            }
            
            return response()->json(['message' => $message], 500);
        }
    })->name('device.register');
});
