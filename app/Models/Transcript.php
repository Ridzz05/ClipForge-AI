<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transcript extends Model
{
    protected $fillable = [
        'video_id',
        'full_text',
        'language',
    ];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function segments(): HasMany
    {
        return $this->hasMany(TranscriptSegment::class)->orderBy('start_ms');
    }
}
