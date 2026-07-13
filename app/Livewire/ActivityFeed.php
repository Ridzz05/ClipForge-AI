<?php

namespace App\Livewire;

use App\Models\PipelineJob;
use Livewire\Component;

/**
 * A live timeline of recent pipeline activity (P4). Reads pipeline_jobs — the
 * per-stage status/error records the jobs already write — so the operator can
 * see what happened across the whole pipeline in one place.
 */
class ActivityFeed extends Component
{
    public int $limit = 25;

    public function render()
    {
        $events = PipelineJob::query()
            ->with('video:id,source_ref,source_type')
            ->latest('updated_at')
            ->limit($this->limit)
            ->get();

        $anyActive = $events->contains(
            fn (PipelineJob $j) => in_array($j->status, ['queued', 'running'], true)
        );

        return view('livewire.activity-feed', [
            'events' => $events,
            'poll' => $anyActive,
        ]);
    }
}
