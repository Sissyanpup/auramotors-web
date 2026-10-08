<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Skema pembayaran + rincian DP
            $table->enum('payment_scheme', ['down_payment', 'full'])->default('down_payment')->after('amount');
            $table->decimal('dp_percent', 5, 2)->nullable()->after('payment_scheme');
            $table->decimal('vehicle_price', 14, 2)->nullable()->after('dp_percent');

            // Asuransi
            $table->enum('insurance_type', ['none', 'tlo', 'all_risk'])->default('none')->after('vehicle_price');
            $table->decimal('insurance_premium', 14, 2)->default(0)->after('insurance_type');

            // Info pengantaran & catatan buyer
            $table->text('buyer_address')->nullable()->after('insurance_premium');
            $table->string('buyer_phone', 32)->nullable()->after('buyer_address');
            $table->text('buyer_notes')->nullable()->after('buyer_phone');

            // Nota resmi / invoice
            $table->string('invoice_number', 32)->nullable()->unique()->after('buyer_notes');
            $table->string('bank_transfer_bank', 64)->nullable()->after('invoice_number');
            $table->string('bank_transfer_account_number', 64)->nullable()->after('bank_transfer_bank');
            $table->string('bank_transfer_account_holder', 128)->nullable()->after('bank_transfer_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
            $table->dropColumn([
                'payment_scheme', 'dp_percent', 'vehicle_price',
                'insurance_type', 'insurance_premium',
                'buyer_address', 'buyer_phone', 'buyer_notes',
                'invoice_number',
                'bank_transfer_bank', 'bank_transfer_account_number', 'bank_transfer_account_holder',
            ]);
        });
    }
};
