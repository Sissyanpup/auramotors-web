<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('escrow_status', ['escrow_hold', 'serah_terima', 'payout_release', 'selesai', 'dispute', 'refunded'])
                ->nullable()
                ->after('payment_status');
            $table->timestamp('buyer_confirmed_at')->nullable();
            $table->timestamp('seller_confirmed_at')->nullable();
            $table->string('escrow_status_before_dispute')->nullable();
            $table->text('dispute_reason')->nullable();
            $table->foreignId('disputed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disputed_at')->nullable();
            $table->text('dispute_resolution_note')->nullable();
            $table->foreignId('dispute_resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispute_resolved_at')->nullable();

            $table->index(['escrow_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['disputed_by']);
            $table->dropForeign(['dispute_resolved_by']);
            $table->dropIndex(['escrow_status']);
            $table->dropColumn([
                'escrow_status',
                'buyer_confirmed_at',
                'seller_confirmed_at',
                'escrow_status_before_dispute',
                'dispute_reason',
                'disputed_by',
                'disputed_at',
                'dispute_resolution_note',
                'dispute_resolved_by',
                'dispute_resolved_at',
            ]);
        });
    }
};
