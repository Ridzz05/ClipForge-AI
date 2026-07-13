<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Export extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RENDERING = 'rendering';

    public const STATUS_RENDERED = 'rendered';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'clip_candidate_id',
        'aspect_ratio',
        'output_path',
        'watermark_applied',
        'caption_style',
        'status',
        'last_error',
        'rendered_at',
    ];

    protected $casts = [
        'watermark_applied' => 'boolean',
        'rendered_at' => 'datetime',
    ];

    public function clipCandidate(): BelongsTo
    {
        return $this->belongsTo(ClipCandidate::class);
    }
}
