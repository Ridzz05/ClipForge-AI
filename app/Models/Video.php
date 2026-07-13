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
        'last_error',
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

    public function clipCandidates(): HasMany
    {
        return $this->hasMany(ClipCandidate::class);
    }

    /**
     * Ordered pipeline stages with a status each, for the UI progress strip.
     *
     * @return array<int, array{key:string, label:string, state:string}>
     */
    public function stageProgress(): array
    {
        $stages = [
            ['key' => 'ingest', 'label' => 'Ingest', 'reached' => ['ingested', 'transcribing', 'transcribed', 'scoring', 'reviewing', 'done'], 'active' => ['downloading']],
            ['key' => 'transcribe', 'label' => 'Transcribe', 'reached' => ['transcribed', 'scoring', 'reviewing', 'done'], 'active' => ['transcribing']],
            ['key' => 'score', 'label' => 'Score', 'reached' => ['reviewing', 'done'], 'active' => ['scoring']],
            ['key' => 'review', 'label' => 'Review', 'reached' => ['done'], 'active' => ['reviewing']],
        ];

        if ($this->status === 'failed') {
            return array_map(fn ($s) => [
                'key' => $s['key'], 'label' => $s['label'], 'state' => 'failed',
            ], $stages);
        }

        return array_map(function ($s) {
            $state = 'pending';
            if (in_array($this->status, $s['reached'], true)) {
                $state = 'done';
            } elseif (isset($s['active']) && in_array($this->status, $s['active'], true)) {
                $state = 'active';
            }

            return ['key' => $s['key'], 'label' => $s['label'], 'state' => $state];
        }, $stages);
    }

    /** Is the pipeline still actively working (so the UI keeps polling)? */
    public function isProcessing(): bool
    {
        return in_array($this->status, ['downloading', 'ingested', 'transcribing', 'scoring'], true);
    }

    /**
     * The reason this video failed, for the UI. Prefers the video-level error,
     * falling back to the last failed pipeline stage's message.
     */
    public function failureReason(): ?string
    {
        if ($this->status !== 'failed') {
            return null;
        }

        if (! empty($this->last_error)) {
            return $this->last_error;
        }

        $failed = $this->pipelineJobs()
            ->where('status', 'failed')
            ->latest('updated_at')
            ->first();

        return $failed?->last_error;
    }
}
