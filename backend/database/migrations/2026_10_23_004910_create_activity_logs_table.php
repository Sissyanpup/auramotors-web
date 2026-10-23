<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jejak audit global (append-only): setiap proses — verifikasi, transaksi,
        // serah terima, kredit, payout, promo — supaya admin bisa memantau
        // semua gerak-gerik jual beli (catatan dosen 16 Okt).
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 20)->nullable();
            $table->string('category', 30)->index();
            $table->string('action', 60)->index();
            $table->string('description', 500);
            $table->nullableMorphs('subject');
            $table->foreignId('transaction_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
