<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clip_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('start_ms');
            $table->unsignedBigInteger('end_ms');
            $table->unsignedTinyInteger('hook_score'); // 0-100
            $table->text('score_rationale'); // from LLM, treated as data only
            // Human review gate (spec 4): pending until Rizki approves/rejects.
            $table->string('status')->default('pending')
                ->index(); // pending|approved|rejected|exported
            $table->timestamps();

            $table->index(['video_id', 'hook_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clip_candidates');
    }
};
