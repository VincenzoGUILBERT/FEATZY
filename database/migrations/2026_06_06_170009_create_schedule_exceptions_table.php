<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->date('date');
            $table->string('service_type', 20)->nullable();
            $table->string('service_type_key')->storedAs("COALESCE(service_type, '__ALL__')");
            $table->boolean('is_closed')->default(false);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->unsignedSmallInteger('max_party_size')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'date', 'service_type_key']);
            $table->index(['restaurant_id', 'date']);
        });

        DB::statement('ALTER TABLE schedule_exceptions ADD CONSTRAINT chk_schedule_exceptions_capacity CHECK (capacity IS NULL OR capacity >= 0)');
        DB::statement('ALTER TABLE schedule_exceptions ADD CONSTRAINT chk_schedule_exceptions_time CHECK (end_time IS NULL OR start_time IS NULL OR end_time > start_time)');
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_exceptions');
    }
};
