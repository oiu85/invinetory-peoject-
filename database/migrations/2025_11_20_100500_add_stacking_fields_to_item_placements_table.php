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
        Schema::table('item_placements', function (Blueprint $table) {
            $table->unsignedBigInteger('stack_id')->nullable()->after('layer_index');
            $table->integer('stack_position')->default(1)->after('stack_id')->comment('Position in stack (1 = bottom)');
            $table->decimal('stack_base_x', 10, 2)->nullable()->after('stack_position')->comment('X coordinate of stack base');
            $table->decimal('stack_base_y', 10, 2)->nullable()->after('stack_base_x')->comment('Y coordinate of stack base');
            $table->integer('items_below_count')->default(0)->after('stack_base_y')->comment('Number of items below this one');

            $table->index(['stack_id', 'stack_position']);
            $table->index(['product_id', 'stack_base_x', 'stack_base_y']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_placements', function (Blueprint $table) {
            $table->dropIndex(['stack_id', 'stack_position']);
            $table->dropIndex(['product_id', 'stack_base_x', 'stack_base_y']);
            $table->dropColumn([
                'stack_id',
                'stack_position',
                'stack_base_x',
                'stack_base_y',
                'items_below_count',
            ]);
        });
    }
};
