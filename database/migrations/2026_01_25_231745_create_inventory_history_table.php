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
        Schema::create('inventory_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('performed_at');
            $table->json('stock_snapshot');
            $table->decimal('earnings_before_reset', 10, 2);
            $table->decimal('earnings_after_reset', 10, 2)->default(0);
            $table->decimal('total_stock_value', 10, 2);
            $table->decimal('total_cost_value', 10, 2);
            $table->date('period_start_date');
            $table->date('period_end_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_history');
    }
};
