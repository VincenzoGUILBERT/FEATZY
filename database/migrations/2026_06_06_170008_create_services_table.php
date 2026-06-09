<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un service est une période de repas (Déjeuner, Dîner, Brunch…) d'un restaurant.
     * Il porte les deux plafonds de couverts — simultanés (occupation de la salle) et
     * par créneau (pacing / lissage des arrivées) — et peut surcharger la durée d'assise,
     * la granularité de créneau et les bornes de groupe héritées du restaurant.
     *
     * Les services partageant `capacity_pool_key` représentent la même salle physique :
     * leurs couverts présents s'additionnent contre `max_simultaneous_covers`.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20);
            $table->string('capacity_pool_key', 40)->default('default');
            $table->unsignedSmallInteger('max_simultaneous_covers');
            $table->unsignedSmallInteger('max_covers_per_slot');
            $table->unsignedSmallInteger('seating_duration_minutes')->nullable();
            $table->unsignedTinyInteger('slot_interval_minutes')->nullable();
            $table->unsignedSmallInteger('min_party_size')->nullable();
            $table->unsignedSmallInteger('max_party_size')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['restaurant_id', 'is_active']);
            $table->index(['restaurant_id', 'capacity_pool_key']);
        });

        DB::statement('ALTER TABLE services ADD CONSTRAINT chk_services_covers CHECK (max_simultaneous_covers >= 1 AND max_covers_per_slot >= 1 AND max_covers_per_slot <= max_simultaneous_covers)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT chk_services_slot_interval CHECK (slot_interval_minutes IS NULL OR slot_interval_minutes >= 1)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT chk_services_party_size CHECK (max_party_size IS NULL OR min_party_size IS NULL OR max_party_size >= min_party_size)');
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
