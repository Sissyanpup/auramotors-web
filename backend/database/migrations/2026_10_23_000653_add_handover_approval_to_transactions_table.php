<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Serah terima dua tahap (catatan dosen 16 Okt): penjual mengajukan, admin
            // menyetujui, baru kendaraan boleh diserahkan/diberangkatkan ke pembeli.
            $table->timestamp('handover_requested_at')->nullable()->after('escrow_status');
            $table->timestamp('handover_approved_at')->nullable()->after('handover_requested_at');
            $table->foreignId('handover_approved_by')->nullable()->after('handover_approved_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handover_approved_by');
            $table->dropColumn(['handover_requested_at', 'handover_approved_at']);
        });
    }
};
