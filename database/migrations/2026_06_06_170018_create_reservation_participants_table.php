<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 20);
            // VIRTUAL (et non STORED) : MySQL interdit une FK ON DELETE CASCADE sur la colonne
            // de base (reservation_id) d'une colonne generee STORED. En VIRTUAL, CASCADE est autorise.
            $table->unsignedBigInteger('organizer_key')->virtualAs("CASE WHEN role = 'organizer' THEN reservation_id ELSE NULL END");
            $table->string('invitation_status', 20)->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->boolean('is_attending')->nullable();
            $table->timestamps();

            $table->unique(['reservation_id', 'user_id']);
            $table->unique('organizer_key');
            $table->index('user_id');
            $table->index(['reservation_id', 'invitation_status']);
            $table->index(['user_id', 'invitation_status', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_participants');
    }
};
