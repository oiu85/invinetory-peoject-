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
        Schema::create('product_dimensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->decimal('width', 10, 2)->comment('in cm');
            $table->decimal('depth', 10, 2)->comment('in cm');
            $table->decimal('height', 10, 2)->comment('in cm');
            $table->decimal('weight', 10, 2)->nullable()->comment('in kg');
            $table->boolean('rotatable')->default(true);
            $table->boolean('fragile')->default(false);
            $table->timestamps();

            $table->unique('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_dimensions');
    }
};
