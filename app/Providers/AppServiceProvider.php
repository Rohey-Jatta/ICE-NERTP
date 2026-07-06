<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Result;
use App\Observers\AuditTrailObserver;
use App\Services\AuditTrailService;
use App\Services\CertificationWorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        // ── AuditTrailService — singleton ─────────────────────────────────────
        // Registered as a singleton so every controller, service, and route
        // closure shares the same instance. This means:
        //  - The UA parse cache is warm after the first request
        //  - A single object is constructed per HTTP request lifecycle
        //  - CertificationWorkflowService resolves it via DI without "new"
        $this->app->singleton(AuditTrailService::class, function ($app) {
            return new AuditTrailService();
        });

        // ── CertificationWorkflowService — transient ──────────────────────────
        // Not a singleton because it captures no state between calls —
        // binding it makes DI resolution explicit and testable.
        $this->app->bind(CertificationWorkflowService::class, function ($app) {
            return new CertificationWorkflowService(
                $app->make(AuditTrailService::class)
            );
        });
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        // ── Strict model behaviour (local + testing only) ─────────────────────
        Model::shouldBeStrict(! $this->app->isProduction());

        // ── Slow query logger (production threshold: 1 500 ms) ────────────────
        if ($this->app->isProduction()) {
            DB::listen(function ($query) {
                if ($query->time > 1500) {
                    Log::warning('[SlowQuery] ' . $query->time . 'ms', [
                        'sql'      => $query->sql,
                        'bindings' => $query->bindings,
                    ]);
                }
            });
        }

        // ── Observers ─────────────────────────────────────────────────────────
        // AuditTrailObserver records model-level created/updated/deleted events
        // for Candidate and Election — lightweight complement to the explicit
        // AuditTrailService calls in controllers.
        // (Result and AuditLog are excluded: Results are audited explicitly in
        // the officer route; AuditLog itself must never be observed to avoid
        // recursive inserts.)
        Candidate::observe(AuditTrailObserver::class);
        Election::observe(AuditTrailObserver::class);
    }
}