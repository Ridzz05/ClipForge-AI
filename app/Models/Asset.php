<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    public const KIND_MUSIC = 'music';

    public const KIND_IMAGE = 'image';

    protected $fillable = [
        'kind',
        'name',
        'path',
        'mime',
        'size_bytes',
        'duration_seconds',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'duration_seconds' => 'integer',
    ];
}
