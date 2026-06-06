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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('restaurant_id')->constrained('restaurants')->restrictOnDelete();
            $table->foreignId('service_availability_id')->constrained('service_availabilities')->restrictOnDelete();
            $table->foreignId('organizer_id')->constrained('users')->restrictOnDelete();
            $table->date('reservation_date');
            $table->string('service_type', 20);
            $table->unsignedSmallInteger('party_size');
            $table->string('status', 20)->default('confirmed');
            $table->boolean('is_preorder')->default(false);
            $table->text('special_requests')->nullable();
            $table->time('expected_arrival_time')->nullable();
            $table->timestamp('seated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('service_availability_id');
            $table->index('organizer_id');
            $table->index('cancelled_by_id');
            $table->index(['restaurant_id', 'reservation_date', 'status']);
            $table->index(['organizer_id', 'status']);
            $table->index(['status', 'reservation_date', 'id']);
        });

        DB::statement('ALTER TABLE reservations ADD CONSTRAINT chk_reservations_party_size CHECK (party_size >= 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
