<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'model',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    // Simple relationship - no complex logic
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Simple static method to create logs
    public static function record($action, $model, $modelId = null, $oldValues = null, $newValues = null)
    {
        try {
            return self::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'model' => $model,
                'model_id' => $modelId,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Audit log failed: ' . $e->getMessage());
            return null;
        }
    }
}
