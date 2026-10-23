<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bonus keberhasilan menjual (catatan dosen 16 Okt): bonus per unit
        // terjual + bonus tier saat penjual mencapai jumlah penjualan tertentu.
        Schema::create('seller_bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_payout_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 10);
            $table->unsignedSmallInteger('tier_threshold')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('basis');
            $table->string('status', 10)->default('paid');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['seller_id', 'tier_threshold']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_bonuses');
    }
};
