<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantBackup extends Model
{
    protected $fillable = [
        'tenant_id',
        'filename',
        'path',
        'size_bytes',
        'status',
        'trigger',
        'error',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function humanSize(): string
    {
        $bytes = $this->size_bytes;
        if ($bytes < 1024) {
            return $bytes . ' o';
        }
        $units = ['Ko', 'Mo', 'Go'];
        $i = -1;
        do {
            $bytes /= 1024;
            $i++;
        } while ($bytes >= 1024 && $i < count($units) - 1);

        return round($bytes, 1) . ' ' . $units[$i];
    }
}
