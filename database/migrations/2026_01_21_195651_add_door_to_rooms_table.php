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
        Schema::table('rooms', function (Blueprint $table) {
            $table->decimal('door_x', 10, 2)->nullable()->comment('Door X position in cm');
            $table->decimal('door_y', 10, 2)->nullable()->comment('Door Y position in cm');
            $table->decimal('door_width', 10, 2)->nullable()->comment('Door width in cm');
            $table->decimal('door_height', 10, 2)->nullable()->comment('Door height in cm');
            $table->enum('door_wall', ['north', 'south', 'east', 'west'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['door_x', 'door_y', 'door_width', 'door_height', 'door_wall']);
        });
    }
};
