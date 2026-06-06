<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->date('date');
            $table->string('service_type', 20);
            $table->unsignedSmallInteger('capacity');
            $table->unsignedSmallInteger('booked_seats')->default(0);
            $table->unsignedSmallInteger('max_party_size')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'date', 'service_type']);
            $table->index(['date', 'service_type']);
        });

        DB::statement('ALTER TABLE service_availabilities ADD CONSTRAINT chk_avail_seats CHECK (booked_seats <= capacity AND booked_seats >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('service_availabilities');
    }
};
