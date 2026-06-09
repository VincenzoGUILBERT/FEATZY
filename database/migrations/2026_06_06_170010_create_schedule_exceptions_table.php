<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dérogation datée : fermeture, horaires spéciaux ou capacité réduite. `service_id` null
     * cible tout le restaurant ; un service précis prime sur une dérogation restaurant.
     *
     * L'unicité (restaurant, service, date, type) couvre les dérogations ciblant un service ;
     * le cas restaurant-wide (service_id NULL, hors portée de l'index unique) est garanti côté
     * application (FormRequests).
     */
    public function up(): void
    {
        Schema::create('schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 20);
            $table->time('opens_at')->nullable();
            $table->time('last_seating_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('crosses_midnight')->default(false);
            $table->unsignedSmallInteger('capacity_override')->nullable();
            $table->unsignedSmallInteger('pacing_override')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'service_id', 'date', 'type']);
            $table->index(['restaurant_id', 'date']);
        });

        DB::statement('ALTER TABLE schedule_exceptions ADD CONSTRAINT chk_schedule_exceptions_time CHECK (last_seating_at IS NULL OR opens_at IS NULL OR crosses_midnight = 1 OR last_seating_at >= opens_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_exceptions');
    }
};
