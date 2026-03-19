<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BulkUploadBatch extends Model
{
    protected $fillable = [
        'batch_id',
        'file_path',
        'original_filename',
        'status',
        'total_rows',
        'processed_rows',
        'success_count',
        'warning_count',
        'failed_count',
        'results',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'results' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ── Boot ───────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        // Auto-generate UUID batch_id on creation
        static::creating(function (self $batch) {
            if (empty($batch->batch_id)) {
                $batch->batch_id = (string) Str::uuid();
            }
        });
    }

    // ── Accessors ──────────────────────────────────────────────

    /**
     * Progress percentage (0–100).
     */
    public function getProgressAttribute(): int
    {
        if ($this->total_rows === 0) {
            return 0;
        }

        return (int) round(($this->processed_rows / $this->total_rows) * 100);
    }

    /**
     * Human-readable summary string.
     */
    public function getSummaryAttribute(): array
    {
        return [
            'total' => $this->total_rows,
            'processed' => $this->processed_rows,
            'success' => $this->success_count,
            'warnings' => $this->warning_count,
            'failed' => $this->failed_count,
            'progress' => $this->progress.'%',
        ];
    }

    // ── Scopes ─────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
