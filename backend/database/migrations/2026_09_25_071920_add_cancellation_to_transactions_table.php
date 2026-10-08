<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite enum diimplementasi sebagai CHECK constraint sehingga menambah
        // value baru butuh recreate kolom. Konversi ke string biar fleksibel;
        // integritas nilai tetap dijaga oleh cast Enum di model Transaction.
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('payment_status', 32)->default('pending')->change();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->text('cancellation_reason')->nullable()->after('dispute_resolved_at');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete()->after('cancellation_reason');
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancellation_reason', 'cancelled_at']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'expired'])->default('pending')->change();
        });
    }
};
