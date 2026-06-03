<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuditRequestMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$this->shouldLog($request)) {
            return $response;
        }

        $route = $request->route();
        $routeName = $route?->getName();
        $method = Str::lower($request->method());
        $path = trim($request->path(), '/');
        $normalizedPath = $path === '' ? 'home' : str_replace('/', '.', $path);
        $module = $this->resolveModule($routeName, $path);
        $statusCode = $response->getStatusCode();

        AuditLog::record(
            action: 'request.' . $method . '.' . ($routeName ? str_replace('.', '_', $routeName) : $normalizedPath),
            event: $request->isMethod('GET') ? 'accessed' : 'action',
            module: $module,
            extra: [
                'outcome' => $statusCode >= 500 ? 'failure' : ($statusCode >= 400 ? 'blocked' : 'success'),
                'new_values' => [
                    'method' => $request->method(),
                    'route' => $routeName,
                    'path' => '/' . $path,
                    'status_code' => $statusCode,
                ],
            ]
        );

        return $response;
    }

    private function shouldLog(Request $request): bool
    {
        if (!$request->user()) {
            return false;
        }

        if (!$request->route()) {
            return false;
        }

        if ($request->is('up')) {
            return false;
        }

        if ($request->is('telescope*') || $request->is('_debugbar*')) {
            return false;
        }

        if ($request->is('storage/*') || $request->is('build/*') || $request->is('assets/*')) {
            return false;
        }

        return true;
    }

    private function resolveModule(?string $routeName, string $path): string
    {
        $source = $routeName ?: $path;
        $firstSegment = Str::of($source)->before('.')->before('/')->value();

        if ($firstSegment === '') {
            return 'System';
        }

        return Str::headline($firstSegment);
    }
}
