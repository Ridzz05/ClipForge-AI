<?php

namespace App\Livewire;

use App\Services\MediaCleanupService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Projects extends Component
{
    public function cleanWorkFiles(MediaCleanupService $cleanup): void
    {
        $freed = $cleanup->purgeWorkFiles();

        $this->dispatch('toast', message: 'Berkas kerja dibersihkan: '.self::bytes($freed).' dibebaskan.', type: 'success');
    }

    public static function bytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        $units = ['KB', 'MB', 'GB', 'TB'];
        $i = min((int) floor(log($bytes, 1024)) - 1, count($units) - 1);

        return number_format($bytes / (1024 ** ($i + 1)), $i >= 1 ? 2 : 1).' '.$units[$i];
    }

    public function render(MediaCleanupService $cleanup)
    {
        return view('livewire.projects', ['usage' => $cleanup->usage()]);
    }
}
