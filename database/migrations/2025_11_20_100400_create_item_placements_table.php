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
        Schema::create('item_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_layout_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('warehouse_stock_id')->nullable()->constrained('warehouse_stock')->onDelete('set null');
            $table->decimal('x_position', 10, 2)->comment('cm from left wall');
            $table->decimal('y_position', 10, 2)->comment('cm from front wall');
            $table->decimal('z_position', 10, 2)->comment('cm from floor');
            $table->decimal('width', 10, 2)->comment('actual placed width');
            $table->decimal('depth', 10, 2)->comment('actual placed depth');
            $table->decimal('height', 10, 2)->comment('actual placed height');
            $table->enum('rotation', ['0', '90', '180', '270'])->default('0');
            $table->integer('layer_index')->default(0);
            $table->timestamps();

            $table->index(['room_layout_id', 'layer_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_placements');
    }
};
