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
        'caption_font',
        'layout',
        'manual_crop_x',
        'split_top_crop_x',
        'split_bottom_crop_x',
        'segments',
        'cta_text',
        'status',
        'last_error',
        'rendered_at',
        'caption_margin_v',
        'headline_text',
        'headline_style',
        'music_asset_id',
        'music_volume',
    ];

    protected $casts = [
        'watermark_applied' => 'boolean',
        'rendered_at' => 'datetime',
        'caption_margin_v' => 'integer',
        'manual_crop_x' => 'float',
        'split_top_crop_x' => 'float',
        'split_bottom_crop_x' => 'float',
        'segments' => 'array',
        'music_volume' => 'integer',
    ];

    public function musicAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'music_asset_id');
    }

    public function clipCandidate(): BelongsTo
    {
        return $this->belongsTo(ClipCandidate::class);
    }
}
