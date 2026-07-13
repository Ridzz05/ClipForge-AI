<?php

namespace App\Livewire;

use App\Models\Video;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Dashboard extends Component
{
    public function render()
    {
        return view('livewire.dashboard', [
            'videoCount' => Video::count(),
        ]);
    }
}
