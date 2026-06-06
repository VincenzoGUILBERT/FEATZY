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
        Schema::create('cuisine_type_restaurant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuisine_type_id')->constrained('cuisine_types')->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['cuisine_type_id', 'restaurant_id']);
            $table->index(['restaurant_id', 'cuisine_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuisine_type_restaurant');
    }
};
