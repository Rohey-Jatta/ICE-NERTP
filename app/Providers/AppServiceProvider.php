<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Observers\AuditTrailObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (app()->environment          ('production')) {
            URL::forceScheme('https');
        }

        $observer = app(AuditTrailObserver::class);
        $modelPath = app_path('Models');

        foreach (File::files($modelPath) as $file) {
            $class = 'App\\Models\\' . $file->getFilenameWithoutExtension();

            if (!class_exists($class)) {
                continue;
            }

            if (!is_subclass_of($class, Model::class)) {
                continue;
            }

            if ($class === AuditLog::class) {
                continue;
            }

            $class::observe($observer);
        }
    }
}
