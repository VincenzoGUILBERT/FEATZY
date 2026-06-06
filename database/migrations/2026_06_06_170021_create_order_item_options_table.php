<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_item_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('menu_item_option_id')->nullable()->constrained('menu_item_options')->nullOnDelete();
            $table->string('label_snapshot');
            $table->bigInteger('price_delta_snapshot');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamps();

            $table->index('order_item_id');
            $table->index('menu_item_option_id');
        });

        DB::statement('ALTER TABLE order_item_options ADD CONSTRAINT chk_order_item_options_quantity CHECK (quantity >= 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_item_options');
    }
};
