<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClipCandidate extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPORTED = 'exported';

    protected $fillable = [
        'video_id',
        'start_ms',
        'end_ms',
        'hook_score',
        'score_rationale',
        'status',
    ];

    protected $casts = [
        'start_ms' => 'integer',
        'end_ms' => 'integer',
        'hook_score' => 'integer',
    ];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function durationMs(): int
    {
        return $this->end_ms - $this->start_ms;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }
}
