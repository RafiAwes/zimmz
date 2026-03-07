<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Expand enum to include both old and new values
        Schema::table('runners', function (Blueprint $table) {
            $table->enum('type', ['registered', 'assigned', 'my_runner', 'other'])->change();
        });

        // 2. Update existing data
        DB::table('runners')->where('type', 'my_runner')->update(['type' => 'registered']);
        DB::table('runners')->where('type', 'other')->update(['type' => 'assigned']);

        // 3. Narrow enum to only new values
        Schema::table('runners', function (Blueprint $table) {
            $table->enum('type', ['registered', 'assigned'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Expand enum to include both old and new values
        Schema::table('runners', function (Blueprint $table) {
            $table->enum('type', ['registered', 'assigned', 'my_runner', 'other'])->change();
        });

        // 2. Rollback data
        DB::table('runners')->where('type', 'registered')->update(['type' => 'my_runner']);
        DB::table('runners')->where('type', 'assigned')->update(['type' => 'other']);

        // 3. Narrow enum to only old values
        Schema::table('runners', function (Blueprint $table) {
            $table->enum('type', ['my_runner', 'other'])->change();
        });
    }
};
