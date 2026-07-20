<?php

declare(strict_types=1);

namespace App\Enums;

enum PipelineStatus: string
{
    case Idle = 'idle';
    case Pending = 'pending';
    case Ingesting = 'ingesting';
    case Transcribing = 'transcribing';
    case Scoring = 'scoring';
    case Reframing = 'reframing';
    case Rendering = 'rendering';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Idle => 'Idle',
            self::Pending => 'Dalam Antrean',
            self::Ingesting => 'Ingest Video',
            self::Transcribing => 'Transkripsi',
            self::Scoring => 'Scoring LLM',
            self::Reframing => 'Reframing 9:16',
            self::Rendering => 'Rendering Export',
            self::Completed => 'Selesai',
            self::Failed => 'Gagal',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Completed => 'badge-green',
            self::Failed => 'badge-red',
            self::Idle, self::Pending => 'badge-amber',
            default => 'badge-purple',
        };
    }
}
