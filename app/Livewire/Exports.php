<?php

namespace App\Livewire;

use App\Models\Export;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Exports extends Component
{
    public function render()
    {
        $exports = Export::query()
            ->with('clipCandidate.video')
            ->latest()
            ->limit(100)
            ->get();

        // Poll while any export is still queued or rendering.
        $poll = $exports->contains(fn (Export $e) => in_array(
            $e->status,
            [Export::STATUS_QUEUED, Export::STATUS_RENDERING],
            true,
        ));

        return view('livewire.exports', [
            'exports' => $exports,
            'poll' => $poll,
        ]);
    }
}
