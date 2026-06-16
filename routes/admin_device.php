<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Models\Device;
use App\Models\AuditLog;

Route::middleware(['auth', 'role:iec-administrator'])->prefix('admin')->group(function () {
    Route::post('/users/{user}/device/reset', function (Request $request, User $user) {
        try {
            $old = $user->bound_device_id;
            $user->bound_device_id = null;
            $user->save();

            AuditLog::record(action: 'auth.device.reset', event: 'updated', module: 'Authentication', auditable: $user, extra: ['old_bound_device_id' => $old, 'reset_by' => $request->user()->id]);

            return redirect()->back()->with('success', 'Device binding reset for user.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to reset device binding: ' . $e->getMessage()]);
        }
    })->name('admin.users.device.reset');

    Route::post('/users/{user}/device/transfer', function (Request $request, User $user) {
        $request->validate(['device_id' => 'required|exists:devices,id']);
        try {
            $device = Device::findOrFail($request->device_id);
            $old = $user->bound_device_id;
            $user->bound_device_id = $device->id;
            $user->save();

            AuditLog::record(action: 'auth.device.transferred', event: 'updated', module: 'Authentication', auditable: $user, extra: ['old_bound_device_id' => $old, 'new_bound_device_id' => $device->id, 'transferred_by' => $request->user()->id]);

            return redirect()->back()->with('success', 'Device binding transferred.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to transfer device binding: ' . $e->getMessage()]);
        }
    })->name('admin.users.device.transfer');
});
