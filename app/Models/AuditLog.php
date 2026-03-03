<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;

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

    // Relationship
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Static method to create logs - FULLY FIXED
    public static function record($action, $model, $modelId = null, $oldValues = null, $newValues = null)
    {
        try {
            return self::create([
                'user_id' => Auth::id(),  // FIXED: Use Auth facade
                'action' => $action,
                'model' => $model,
                'model_id' => $modelId,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => Request::ip(),  // FIXED: Use Request facade
                'user_agent' => Request::userAgent(),  // FIXED: Use Request facade
            ]);
        } catch (\Exception $e) {
            Log::error('Audit log failed: ' . $e->getMessage());
            return null;
        }
    }
}
