<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineJob extends Model
{
    protected $fillable = [
        'video_id',
        'stage',
        'status',
        'attempts',
        'last_error',
    ];

    protected $casts = [
        'attempts' => 'integer',
    ];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
