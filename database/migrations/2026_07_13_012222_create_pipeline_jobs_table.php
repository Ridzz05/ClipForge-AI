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
        // Spec section 4 calls this `jobs`, renamed to `pipeline_jobs` to
        // avoid colliding with Laravel's own queue `jobs` table. This tracks
        // per-stage pipeline progress for observability, distinct from the
        // queue backend.
        Schema::create('pipeline_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->string('stage'); // ingest|transcribe|score|reframe|export
            $table->string('status')->default('queued'); // queued|running|failed|done
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['video_id', 'stage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipeline_jobs');
    }
};
