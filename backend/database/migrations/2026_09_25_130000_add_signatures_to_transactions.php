<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('buyer_signature_path')->nullable()->after('buyer_notes');
            $table->timestamp('buyer_signed_at')->nullable()->after('buyer_signature_path');
            $table->string('seller_signature_path')->nullable()->after('buyer_signed_at');
            $table->timestamp('seller_signed_at')->nullable()->after('seller_signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'buyer_signature_path', 'buyer_signed_at',
                'seller_signature_path', 'seller_signed_at',
            ]);
        });
    }
};
