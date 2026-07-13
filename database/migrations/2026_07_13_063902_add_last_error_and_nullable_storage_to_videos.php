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
        Schema::table('videos', function (Blueprint $table) {
            // Surface the terminal failure reason on the video itself (parallels
            // exports.last_error) so the UI can show WHY a video failed.
            $table->text('last_error')->nullable()->after('status');
            // A URL-sourced video exists (status=downloading) before its file
            // lands on disk, so the storage path is set later.
            $table->string('storage_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('last_error');
            $table->string('storage_path')->nullable(false)->change();
        });
    }
};
