<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Snapshot rincian harga saat checkout — angka di kontrak tidak boleh berubah
            // walaupun tarif di config/pricing.php diubah setelahnya.
            $table->json('price_breakdown')->nullable()->after('insurance_premium');
            $table->json('insurance_addons')->nullable()->after('insurance_type');
            $table->string('insurance_policy_number')->nullable()->after('insurance_addons');

            // Detail pembayaran dari portal bank simulasi. Nomor kartu & CVV tidak pernah
            // disimpan — hanya brand + 4 digit terakhir (pola PCI-DSS).
            $table->string('payment_method')->nullable()->after('payment_status');
            $table->string('payment_channel')->nullable()->after('payment_method');
            $table->string('payment_account_masked')->nullable()->after('payment_channel');
            $table->string('payment_payer_name')->nullable()->after('payment_account_masked');
            $table->string('payment_approval_code')->nullable()->after('payment_payer_name');
        });

        // Enum lama hanya mengenal 6 tipe dokumen; jadikan string supaya tipe baru
        // (rincian harga, surat bank, polis, BAST, kuasa, kuitansi) bisa disimpan.
        // Validasi nilai tetap lewat cast App\Enums\TransactionDocumentType.
        Schema::table('transaction_documents', function (Blueprint $table) {
            $table->string('type', 64)->change();
        });

        Schema::create('transaction_document_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('role');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('signature_path');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('signed_at');
            $table->timestamps();

            $table->unique(['transaction_id', 'document_type', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_document_signatures');

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'price_breakdown', 'insurance_addons', 'insurance_policy_number',
                'payment_method', 'payment_channel', 'payment_account_masked',
                'payment_payer_name', 'payment_approval_code',
            ]);
        });
    }
};
