<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fenêtre hebdomadaire récurrente d'ouverture d'un service un jour donné. Plusieurs
     * fenêtres par (service, jour) sont permises pour les services coupés (ex. 12h-14h30).
     *
     * `opens_at` = premier créneau d'arrivée, `last_seating_at` = dernière arrivée acceptée,
     * `closes_at` = fin de service. Les heures sont des heures murales locales (mono-fuseau).
     * `crosses_midnight` indique qu'une borne après minuit appartient au lendemain.
     */
    public function up(): void
    {
        Schema::create('service_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at');
            $table->time('last_seating_at');
            $table->time('closes_at');
            $table->boolean('crosses_midnight')->default(false);
            $table->timestamps();

            $table->index(['service_id', 'day_of_week']);
        });

        DB::statement('ALTER TABLE service_schedules ADD CONSTRAINT chk_service_schedules_day_of_week CHECK (day_of_week BETWEEN 0 AND 6)');
        DB::statement('ALTER TABLE service_schedules ADD CONSTRAINT chk_service_schedules_window CHECK (crosses_midnight = 1 OR (last_seating_at >= opens_at AND closes_at >= last_seating_at))');
    }

    public function down(): void
    {
        Schema::dropIfExists('service_schedules');
    }
};
