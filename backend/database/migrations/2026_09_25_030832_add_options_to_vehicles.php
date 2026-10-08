<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Preset DP kustom (null = pakai default 5/10/15/20 + full).
            // Struktur: [{"label":"DP 10%","percent":10}, {"label":"DP 25%","percent":25}, {"label":"Lunas","percent":100}]
            $table->json('payment_options')->nullable()->after('description');

            // Preset asuransi kustom (null = pakai default TLO/All Risk/None).
            // Struktur: [{"type":"tlo","label":"TLO","premium":5000000},{"type":"all_risk","label":"All Risk","premium":12000000}]
            $table->json('insurance_options')->nullable()->after('payment_options');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['payment_options', 'insurance_options']);
        });
    }
};
