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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->restrictOnDelete();
            // restrictOnDelete (et non nullOnDelete) : MySQL interdit ON DELETE SET NULL sur la colonne
            // de base (reservation_participant_id) de la colonne generee active_participant_key.
            // restrictOnDelete est compatible et coherent : on ne supprime pas un participant ayant un paiement.
            $table->foreignId('reservation_participant_id')->nullable()->constrained('reservation_participants')->restrictOnDelete();
            $table->foreignId('payer_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('method', 20);
            $table->string('status', 20)->default('pending');
            $table->string('stripe_payment_intent_id')->nullable();
            $table->unsignedBigInteger('amount_refunded')->default(0);
            $table->unsignedBigInteger('active_participant_key')->storedAs("CASE WHEN status IN ('pending','processing','paid') THEN reservation_participant_id ELSE NULL END");
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('stripe_payment_intent_id');
            $table->unique('active_participant_key');
            $table->index(['reservation_id', 'status']);
            $table->index('reservation_participant_id');
            $table->index('payer_user_id');
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_payments_refund_le_amount CHECK (amount_refunded <= amount)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
