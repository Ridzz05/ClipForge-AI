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
        Schema::table('exports', function (Blueprint $table) {
            $table->float('split_top_crop_x')->nullable()->default(0.25)->after('manual_crop_x');
            $table->float('split_bottom_crop_x')->nullable()->default(0.75)->after('split_top_crop_x');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            $table->dropColumn(['split_top_crop_x', 'split_bottom_crop_x']);
        });
    }
};
