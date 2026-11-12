<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            $table->string('headline_text', 120)->nullable();
            $table->string('headline_style', 32)->nullable();
            $table->unsignedBigInteger('music_asset_id')->nullable();
            $table->unsignedTinyInteger('music_volume')->default(20); // percent of original mix
        });
    }

    public function down(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            $table->dropColumn(['headline_text', 'headline_style', 'music_asset_id', 'music_volume']);
        });
    }
};
