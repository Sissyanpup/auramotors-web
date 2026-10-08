<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'commercial_invoice',
                'deposit_receipt',
                'bill_of_sale',
                'sales_purchase_agreement',
                'escrow_disbursement_note',
                'tax_invoice',
            ]);
            $table->string('path');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->index(['transaction_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_documents');
    }
};
