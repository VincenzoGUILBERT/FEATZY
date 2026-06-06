<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_item_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_group_id')->constrained('menu_item_option_groups')->cascadeOnDelete();
            $table->string('name');
            $table->bigInteger('price_delta');
            $table->unsignedInteger('stock_quantity')->nullable();
            $table->boolean('is_available')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['option_group_id', 'position']);
            $table->index(['option_group_id', 'is_available']);
        });

        DB::statement('ALTER TABLE menu_item_options ADD CONSTRAINT chk_menu_item_options_stock CHECK (stock_quantity IS NULL OR stock_quantity >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_options');
    }
};
