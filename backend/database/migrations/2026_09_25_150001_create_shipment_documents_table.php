<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'export_declaration',
                'bill_of_lading',
                'air_waybill',
                'import_declaration',
                'customs_duty_receipt',
                'certificate_of_conformity',
                'proof_of_delivery',
                'letter_of_acceptance',
            ]);
            $table->string('path');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();

            $table->index(['shipment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_documents');
    }
};
