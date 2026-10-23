<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_loan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->date('due_date');
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->timestamp('paid_at')->nullable();
            $table->decimal('late_fee', 15, 2)->nullable();
            $table->decimal('paid_amount', 15, 2)->nullable();
            // Sama seperti pembayaran DP: nomor kartu & CVV tidak pernah disimpan.
            $table->string('payment_method')->nullable();
            $table->string('payment_channel')->nullable();
            $table->string('payment_account_masked')->nullable();
            $table->string('payment_approval_code')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['vehicle_loan_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_installments');
    }
};
