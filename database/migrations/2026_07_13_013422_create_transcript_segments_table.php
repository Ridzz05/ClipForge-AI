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
        Schema::create('transcript_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transcript_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('start_ms');
            $table->unsignedBigInteger('end_ms');
            $table->text('text');
            $table->string('speaker_label')->nullable();
            // Word-level timing [{word,start_ms,end_ms}, ...] — required for
            // caption burn-in (spec 5.4) and highlight boundary snapping (5.2).
            $table->json('words')->nullable();
            $table->timestamps();

            $table->index(['transcript_id', 'start_ms']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transcript_segments');
    }
};
