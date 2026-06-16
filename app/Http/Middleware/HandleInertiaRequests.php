<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\User;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            // Use a closure so the user is only resolved when Inertia actually
            // needs to include it in the response — not on every middleware pass
            'auth' => fn () => [
                'user' => $this->userPayload($request->user()),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'info'    => fn () => $request->session()->get('info'),
            ],
        ];
    }

    private function userPayload(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $role = $user->getRoleNames()->first();

        return [
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'phone'         => $user->phone,
            'status'        => $user->status,
            'roles'         => $role ? [['name' => $role]] : [],
            'permissions'   => $user->getAllPermissions()->pluck('name')->values()->all(),
            'dashboard_url' => $this->dashboardUrlForRole($role),
        ];
    }

    private function dashboardUrlForRole(?string $role): string
    {
        return match ($role) {
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
    }
}
