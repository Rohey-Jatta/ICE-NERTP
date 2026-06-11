<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ForcePasswordChangeController extends Controller
{
    public function show(Request $request)
    {
        // Only meaningful while a change is pending; otherwise go home.
        if (!$request->user()->must_change_password) {
            return redirect($request->user()->dashboard_url ?? '/');
        }

        return Inertia::render('Auth/ForcePasswordChange', [
            'auth' => ['user' => $request->user()],
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed|different:current_password',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill([
            'password'             => Hash::make($request->password),
            'must_change_password' => false,
            'password_changed_at'  => now(),
        ])->save();

        AuditLog::record(
            action: 'auth.password.changed',
            event: 'updated',
            module: 'Authentication',
            auditable: $user,
            extra: ['outcome' => 'success']
        );

        return redirect($user->dashboard_url ?? '/')
            ->with('success', 'Your password has been updated.');
    }
}
