<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('menu_category_id')->constrained('menu_categories')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price');
            $table->boolean('is_available')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedInteger('stock_quantity')->nullable();
            $table->unsignedSmallInteger('preparation_minutes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['menu_category_id', 'position']);
        });

        DB::statement('ALTER TABLE menu_items ADD CONSTRAINT chk_menu_items_stock CHECK (stock_quantity IS NULL OR stock_quantity >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
