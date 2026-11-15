<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            $table->string('caption_animation', 16)->nullable();
            $table->string('caption_effect', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            $table->dropColumn(['caption_animation', 'caption_effect']);
        });
    }
};
