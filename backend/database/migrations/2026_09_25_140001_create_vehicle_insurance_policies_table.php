<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_insurance_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->enum('policy_type', ['all_risk', 'tlo', 'agreed_value']);
            $table->string('insurer_name');
            $table->string('policy_number');
            $table->decimal('coverage_amount', 14, 2);
            // Agreed Value: nilai kesepakatan bersama; berbeda dari coverage_amount
            // untuk kendaraan langka/klasik dengan appreciation nilai.
            $table->decimal('agreed_value_amount', 14, 2)->nullable();
            $table->date('valid_from');
            $table->date('valid_until');
            $table->string('certificate_path');
            $table->timestamps();

            $table->index(['vehicle_id', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_insurance_policies');
    }
};
