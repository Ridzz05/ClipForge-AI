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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('source_type'); // upload | url | bot
            $table->string('source_ref')->nullable(); // original name / URL, informational only
            $table->string('status')->default('ingested')
                ->index(); // ingested|transcribing|scoring|reviewing|exporting|done|failed
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('storage_path'); // server-generated, job-scoped path
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
