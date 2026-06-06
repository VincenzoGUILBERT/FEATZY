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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('reservation_participant_id')->constrained('reservation_participants')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('menu_items')->restrictOnDelete();
            $table->string('name_snapshot');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_snapshot');
            $table->bigInteger('options_total_snapshot');
            $table->bigInteger('line_total')->storedAs('(unit_price_snapshot + options_total_snapshot) * quantity');
            $table->string('status', 20)->default('pending');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('reservation_participant_id');
            $table->index('menu_item_id');
            $table->index(['order_id', 'status']);
        });

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT chk_order_items_quantity CHECK (quantity >= 1)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT chk_order_items_line_total_positive CHECK (line_total >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
