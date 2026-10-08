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
        Schema::create('transaction_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->enum('method', ['manual', 'xendit']);
            $table->enum('status', ['pending', 'paid', 'failed']);
            $table->decimal('commission_rate', 6, 4);
            $table->decimal('commission_amount', 14, 2);
            $table->decimal('payout_amount', 14, 2);
            $table->string('reference')->nullable();
            $table->string('failure_reason')->nullable();
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['transaction_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_payouts');
    }
};
