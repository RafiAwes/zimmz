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
        Schema::create('ferry_drops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('pickup_location')->nullable();
            $table->foreignId('ferry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('island_id')->constrained()->cascadeOnDelete();
            $table->decimal('drop_fee', 10, 2)->default(0);
            $table->decimal('package_fee', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ferry_drops');
    }
};
