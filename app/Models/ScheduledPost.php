<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledPost extends Model
{
    public const STATUS_PLANNED = 'planned';

    public const STATUS_POSTED = 'posted';

    protected $fillable = [
        'export_id',
        'platform',
        'scheduled_at',
        'caption',
        'status',
        'posted_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    public function export(): BelongsTo
    {
        return $this->belongsTo(Export::class);
    }
}
