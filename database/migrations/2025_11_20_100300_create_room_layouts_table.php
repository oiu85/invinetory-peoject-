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
        Schema::create('room_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->onDelete('cascade');
            $table->string('algorithm_used', 50)->comment('laff, maxrects, skyline, etc.');
            $table->decimal('utilization_percentage', 5, 2);
            $table->integer('total_items_placed');
            $table->integer('total_items_attempted');
            $table->json('layout_data')->comment('Stores all placement positions');
            $table->timestamps();

            $table->index(['room_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_layouts');
    }
};
