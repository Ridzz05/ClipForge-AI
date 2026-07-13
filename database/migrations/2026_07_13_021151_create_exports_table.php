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
        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clip_candidate_id')->constrained()->cascadeOnDelete();
            $table->string('aspect_ratio')->default('9:16');
            $table->string('output_path')->nullable(); // server-generated, set on render
            $table->boolean('watermark_applied')->default(false);
            $table->string('caption_style')->default('default');
            // Reframe/render lifecycle so a crashed render is visible + retryable.
            $table->string('status')->default('queued')
                ->index(); // queued|rendering|rendered|failed
            $table->text('last_error')->nullable();
            $table->timestamp('rendered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
