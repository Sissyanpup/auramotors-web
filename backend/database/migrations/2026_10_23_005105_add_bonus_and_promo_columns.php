<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_payouts', function (Blueprint $table) {
            // Bonus penjual yang ikut ditransfer bersama pencairan ini (untuk rekonsiliasi).
            $table->decimal('bonus_amount', 15, 2)->default(0)->after('payout_amount');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('promo_code', 32)->nullable()->after('insurance_policy_number');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_payouts', function (Blueprint $table) {
            $table->dropColumn('bonus_amount');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('promo_code');
        });
    }
};
