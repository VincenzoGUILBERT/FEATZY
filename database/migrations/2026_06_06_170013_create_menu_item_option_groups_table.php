<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_item_option_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('min_select')->default(0);
            $table->unsignedTinyInteger('max_select')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['menu_item_id', 'position']);
        });

        DB::statement('ALTER TABLE menu_item_option_groups ADD CONSTRAINT chk_menu_item_option_groups_select CHECK (max_select IS NULL OR max_select >= min_select)');
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_option_groups');
    }
};
