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
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['new', 'pending', 'completed', 'cancelled'])->default('new')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('orders')->where('status', 'cancelled')->update(['status' => 'pending']);

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['new', 'pending', 'completed'])->default('new')->change();
        });
    }
};
