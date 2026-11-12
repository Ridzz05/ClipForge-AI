<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('export_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 32); // free label: tiktok, reels, shorts, ...
            $table->dateTime('scheduled_at');
            $table->text('caption')->nullable();
            $table->string('status', 16)->default('planned'); // planned | posted
            $table->dateTime('posted_at')->nullable();
            $table->timestamps();
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_posts');
    }
};
