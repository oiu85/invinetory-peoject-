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
        Schema::table('room_layouts', function (Blueprint $table) {
            $table->json('compartment_config')->nullable()->comment('Grid and compartment settings');
            $table->integer('grid_columns')->nullable()->comment('Number of grid columns');
            $table->integer('grid_rows')->nullable()->comment('Number of grid rows');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_layouts', function (Blueprint $table) {
            $table->dropColumn(['compartment_config', 'grid_columns', 'grid_rows']);
        });
    }
};
