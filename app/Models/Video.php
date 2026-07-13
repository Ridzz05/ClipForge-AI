<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Video extends Model
{
    protected $fillable = [
        'source_type',
        'source_ref',
        'status',
        'duration_seconds',
        'storage_path',
    ];

    protected $casts = [
        'duration_seconds' => 'integer',
    ];

    public function pipelineJobs(): HasMany
    {
        return $this->hasMany(PipelineJob::class);
    }

    public function transcript(): HasOne
    {
        return $this->hasOne(Transcript::class);
    }
}
