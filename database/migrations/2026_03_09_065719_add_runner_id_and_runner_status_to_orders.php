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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('runner_id')->nullable()->constrained('runners')->cascadeOnDelete();
            $table->enum('runner_status', ['assigned', 'pending', 'picked_up', 'delivered'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign('orders_runner_id_foreign');
            $table->dropColumn('runner_id');
            $table->dropColumn('runner_status');
        });
    }
};
