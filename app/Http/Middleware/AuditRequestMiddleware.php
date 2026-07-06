<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * AuditRequestMiddleware
 *
 * Only logs FAILED write operations (4xx/5xx on POST/PUT/PATCH/DELETE)
 * and DELETE operations that may not have an explicit AuditTrailService call.
 *
 * GET / HEAD / OPTIONS are never logged — they are read-only and produce
 * no audit value. Logging them floods the table with request.get.* noise
 * that has no workflow_stage, description, or result_id.
 *
 * All successful workflow mutations (submissions, approvals, rejections,
 * auth events, etc.) are recorded explicitly by AuditTrailService in the
 * controllers — this middleware only catches what slips through.
 */
class AuditRequestMiddleware
{
    /** Only these HTTP methods mutate state. */
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** Route name prefixes that are never worth auditing. */
    private const SKIP_PREFIXES = [
        'sanctum.',
        'horizon.',
        'telescope.',
        'debugbar.',
        'livewire.',
    ];

    /** Exact route names to skip (prevents recursive audit-log-reads-audit-log). */
    private const SKIP_EXACT = [
        'admin.audit-logs',
        'admin.audit-logs.chain',
        'admin.audit-logs.export',
        'admin.audit-logs.chain.export',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // ── Never log read-only requests ─────────────────────────────────────
        if (!in_array($request->method(), self::WRITE_METHODS, true)) {
            return $response;
        }

        // ── Must be authenticated ─────────────────────────────────────────────
        if (!Auth::check()) {
            return $response;
        }

        // ── Skip internal / framework / audit-log routes ──────────────────────
        $routeName = $request->route()?->getName() ?? '';

        if (in_array($routeName, self::SKIP_EXACT, true)) {
            return $response;
        }

        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return $response;
            }
        }

        $statusCode = $response->getStatusCode();
        $isFailed   = $statusCode >= 400;
        $isDelete   = $request->method() === 'DELETE';

        // ── Only record failures or DELETE operations ─────────────────────────
        // Successful POST/PUT/PATCH are already covered by AuditTrailService.
        if (!$isFailed && !$isDelete) {
            return $response;
        }

        try {
            $user   = Auth::user();
            $method = $request->method();

            $action = $routeName
                ? "request.{$method}.{$routeName}"
                : 'request.' . strtolower($method) . '.' . str_replace('/', '_', ltrim($request->path(), '/'));

            $description = $isFailed
                ? sprintf(
                    '%s %s by %s (ID:%d) FAILED with HTTP %d at %s.',
                    $method,
                    $request->path(),
                    $user->name,
                    $user->id,
                    $statusCode,
                    now()->toDateTimeString()
                )
                : sprintf(
                    '%s (ID:%d) deleted resource at %s at %s.',
                    $user->name,
                    $user->id,
                    $request->path(),
                    now()->toDateTimeString()
                );

            AuditLog::create([
                'user_id'        => $user->id,
                'user_role'      => $user->getRoleNames()->first(),
                'action'         => $action,
                'event'          => $isFailed ? 'failure' : 'deleted',
                'module'         => 'Admin',
                'description'    => $description,
                'ip_address'     => $request->ip(),
                'user_agent'     => $request->userAgent(),
                'url'            => $request->fullUrl(),
                'http_method'    => $method,
                'outcome'        => $isFailed ? 'failure' : 'success',
                'failure_reason' => $isFailed ? "HTTP {$statusCode}" : null,
            ]);

        } catch (\Throwable $e) {
            Log::error('[AuditMiddleware] Failed to write audit record: ' . $e->getMessage());
        }

        return $response;
    }
}