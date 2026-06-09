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
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('street')->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('city', 120)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedTinyInteger('price_level')->nullable();
            $table->boolean('accepts_preorders')->default(false);
            $table->boolean('accepts_online_payment')->default(false);
            $table->unsignedSmallInteger('cancellation_deadline_hours')->default(24);
            $table->unsignedSmallInteger('booking_horizon_days')->default(90);
            // Paramètres de réservation par créneau (défauts hérités par les services).
            $table->unsignedSmallInteger('default_seating_duration_minutes')->default(90);
            $table->unsignedTinyInteger('slot_interval_minutes')->default(15);
            $table->unsignedSmallInteger('min_lead_time_minutes')->default(0);
            $table->unsignedSmallInteger('min_party_size')->default(1);
            $table->unsignedSmallInteger('max_party_size')->default(20);
            $table->string('status', 16)->default('draft');
            $table->decimal('average_rating', 3, 2)->nullable();
            $table->unsignedInteger('reviews_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'average_rating', 'id']);
            $table->index(['status', 'city']);
            $table->index(['status', 'latitude']);
            $table->index('owner_id');
            $table->fullText(['name', 'city', 'description']);
        });

        DB::statement('ALTER TABLE restaurants ADD CONSTRAINT chk_restaurants_price_level CHECK (price_level IS NULL OR price_level BETWEEN 1 AND 3)');
        DB::statement('ALTER TABLE restaurants ADD CONSTRAINT chk_restaurants_average_rating CHECK (average_rating IS NULL OR average_rating BETWEEN 0 AND 5)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
