<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Réservation par créneau. Cœur transactionnel : `reserved_at` (arrivée), `slot_at`
     * (bucket d'arrivée aligné sur la grille → pacing par simple égalité) et `ends_at`
     * (départ = arrivée + durée d'assise) sont des datetimes locaux (mono-fuseau).
     *
     * `seating_duration_minutes` et `capacity_pool_key` sont figés (snapshot) à la création
     * pour que la disponibilité reste cohérente même si le service est reconfiguré ensuite.
     */
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('restaurant_id')->constrained('restaurants')->restrictOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->foreignId('organizer_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('party_size');
            $table->dateTime('reserved_at');
            $table->dateTime('slot_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('seating_duration_minutes');
            $table->string('capacity_pool_key', 40);
            $table->string('status', 20)->default('confirmed');
            $table->boolean('is_preorder')->default(false);
            $table->text('special_requests')->nullable();
            $table->timestamp('seated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Occupation simultanée du pool (somme des couverts chevauchant un intervalle).
            $table->index(['restaurant_id', 'capacity_pool_key', 'status', 'reserved_at'], 'res_pool_idx');
            // Pacing : couverts arrivant exactement sur un bucket de créneau.
            $table->index(['service_id', 'status', 'slot_at'], 'res_slot_idx');
            $table->index('organizer_id');
            $table->index('cancelled_by_id');
            $table->index(['organizer_id', 'status']);
            $table->index(['status', 'reserved_at', 'id']);
        });

        DB::statement('ALTER TABLE reservations ADD CONSTRAINT chk_reservations_party_size CHECK (party_size >= 1)');
        DB::statement('ALTER TABLE reservations ADD CONSTRAINT chk_reservations_window CHECK (ends_at > reserved_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
