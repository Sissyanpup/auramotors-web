<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // VIN standar internasional: 17 karakter alfanumerik (kecuali I/O/Q).
            $table->string('vin', 17)->nullable()->after('year');
            $table->index('vin');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex(['vin']);
            $table->dropColumn('vin');
        });
    }
};
