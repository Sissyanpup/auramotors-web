<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pembiayaan leasing untuk transaksi skema kredit. Angka disalin dari
        // snapshot price_breakdown.credit saat pengajuan supaya kontrak tetap.
        Schema::create('vehicle_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('lender_name');
            $table->string('contract_number')->unique();
            $table->string('status')->index();
            $table->decimal('vehicle_financed', 15, 2);
            $table->decimal('insurance_financed', 15, 2)->default(0);
            $table->decimal('principal', 15, 2);
            $table->decimal('flat_rate', 6, 4);
            $table->unsignedSmallInteger('tenor_months');
            $table->decimal('interest_total', 15, 2);
            $table->decimal('monthly_installment', 15, 2);
            $table->decimal('total_payable', 15, 2);
            $table->decimal('effective_rate', 6, 4);
            $table->timestamp('submitted_at');
            $table->timestamp('auto_approve_at');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('lender_disbursed_at')->nullable();
            $table->timestamp('paid_off_at')->nullable();
            // Jam simulasi untuk demo jatuh tempo & denda tanpa menunggu berbulan-bulan.
            $table->unsignedSmallInteger('clock_offset_months')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_loans');
    }
};
