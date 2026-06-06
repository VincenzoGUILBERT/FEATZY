<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->string('service_type', 20);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('capacity');
            $table->unsignedSmallInteger('max_party_size');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['restaurant_id', 'day_of_week', 'service_type']);
            $table->index(['restaurant_id', 'is_active']);
            $table->index(['restaurant_id', 'day_of_week']);
        });

        DB::statement('ALTER TABLE service_schedules ADD CONSTRAINT chk_service_schedules_time CHECK (end_time > start_time)');
        DB::statement('ALTER TABLE service_schedules ADD CONSTRAINT chk_service_schedules_party_size CHECK (max_party_size <= capacity)');
        DB::statement('ALTER TABLE service_schedules ADD CONSTRAINT chk_service_schedules_day_of_week CHECK (day_of_week BETWEEN 0 AND 6)');
    }

    public function down(): void
    {
        Schema::dropIfExists('service_schedules');
    }
};
