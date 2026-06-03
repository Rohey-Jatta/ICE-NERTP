<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AuditTrailObserver
{
    public function created(Model $model): void
    {
        $this->record($model, 'created', [
            'old_values' => null,
            'new_values' => $this->sanitizeValues($model->getAttributes()),
        ]);
    }

    public function updated(Model $model): void
    {
        $changes = Arr::except($model->getChanges(), ['updated_at']);

        if (empty($changes)) {
            return;
        }

        $previous = method_exists($model, 'getPrevious')
            ? Arr::only($model->getPrevious(), array_keys($changes))
            : [];

        $this->record($model, 'updated', [
            'old_values' => $this->sanitizeValues($previous),
            'new_values' => $this->sanitizeValues($changes),
        ]);
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', [
            'old_values' => $this->sanitizeValues($model->getOriginal()),
            'new_values' => null,
        ]);
    }

    public function restored(Model $model): void
    {
        $this->record($model, 'restored', [
            'old_values' => null,
            'new_values' => $this->sanitizeValues($model->getAttributes()),
        ]);
    }

    private function record(Model $model, string $event, array $extra): void
    {
        if ($model instanceof AuditLog) {
            return;
        }

        $modelName = class_basename($model);
        $module = Str::headline($modelName);
        $action = Str::snake($modelName) . '.' . $event;

        AuditLog::record(
            action: $action,
            event: $event,
            module: $module,
            auditable: $model,
            extra: $extra
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function sanitizeValues(array $values): array
    {
        $sensitive = [
            'password',
            'remember_token',
            'two_factor_secret',
            'token',
            'secret',
        ];

        foreach ($values as $key => $value) {
            $lowered = Str::lower((string) $key);

            foreach ($sensitive as $needle) {
                if (Str::contains($lowered, $needle)) {
                    $values[$key] = '[REDACTED]';
                    continue 2;
                }
            }
        }

        return $values;
    }
}
