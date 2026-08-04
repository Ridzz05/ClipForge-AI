<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasDuplicates = DB::table('exports')
            ->select('clip_candidate_id')
            ->groupBy('clip_candidate_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new \RuntimeException(
                'Cannot enforce one export per candidate while duplicate exports exist. '
                .'Resolve duplicate exports, then run the migration again.'
            );
        }

        Schema::table('exports', function (Blueprint $table): void {
            $table->unique('clip_candidate_id', 'exports_clip_candidate_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('exports', function (Blueprint $table): void {
            $table->dropUnique('exports_clip_candidate_id_unique');
        });
    }
};
