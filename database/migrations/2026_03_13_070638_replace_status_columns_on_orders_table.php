<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add the new columns (temp name for runner_status to avoid conflict with existing column)
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('admin_status', ['new', 'pending', 'completed', 'cancelled'])->default('new')->after('status');
            $table->enum('user_status', ['pending', 'ongoing', 'pending_approval', 'completed', 'cancelled'])->default('pending')->after('admin_status');
            $table->enum('runner_status_v2', ['new', 'ongoing', 'completed', 'cancelled'])->nullable()->after('user_status');
            $table->boolean('delivery_requested')->default(false)->after('runner_status_v2');
        });

        // Step 2: Backfill new columns from old columns
        DB::statement("
            UPDATE orders SET
                admin_status = CASE
                    WHEN status = 'cancelled' THEN 'cancelled'
                    WHEN status = 'completed' THEN 'completed'
                    WHEN status = 'pending'   THEN 'pending'
                    ELSE 'new'
                END,
                user_status = CASE
                    WHEN status = 'cancelled' THEN 'cancelled'
                    WHEN status = 'completed' THEN 'completed'
                    ELSE 'pending'
                END,
                runner_status_v2 = CASE
                    WHEN runner_status = 'pending'   THEN 'new'
                    WHEN runner_status = 'assigned'  THEN 'ongoing'
                    WHEN runner_status = 'delivered' THEN 'completed'
                    ELSE NULL
                END,
                delivery_requested = IF(runner_status = 'delivered', 1, 0)
        ");

        // Step 3: Drop old columns
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['status', 'runner_status']);
        });

        // Step 4: Rename temp column to final name
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('runner_status_v2', 'runner_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['new', 'pending', 'completed', 'cancelled'])->default('new');
            $table->enum('runner_status_old', ['assigned', 'pending', 'picked_up', 'delivered'])->nullable();
        });

        DB::statement("
            UPDATE orders SET
                status = CASE
                    WHEN admin_status = 'cancelled' THEN 'cancelled'
                    WHEN admin_status = 'completed' THEN 'completed'
                    WHEN admin_status = 'pending'   THEN 'pending'
                    ELSE 'new'
                END,
                runner_status_old = CASE
                    WHEN runner_status = 'new'       THEN 'pending'
                    WHEN runner_status = 'ongoing'   THEN 'assigned'
                    WHEN runner_status = 'completed' THEN 'delivered'
                    ELSE NULL
                END
        ");

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['admin_status', 'user_status', 'runner_status', 'delivery_requested']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('runner_status_old', 'runner_status');
        });
    }
};
