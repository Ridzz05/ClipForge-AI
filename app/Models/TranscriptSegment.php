<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscriptSegment extends Model
{
    protected $fillable = [
        'transcript_id',
        'start_ms',
        'end_ms',
        'text',
        'speaker_label',
        'words',
    ];

    protected $casts = [
        'start_ms' => 'integer',
        'end_ms' => 'integer',
        'words' => 'array',
    ];

    public function transcript(): BelongsTo
    {
        return $this->belongsTo(Transcript::class);
    }
}
