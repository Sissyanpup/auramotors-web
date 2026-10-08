<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ktp_number', 16)->nullable()->after('email');
            $table->string('ktp_name', 255)->nullable()->after('ktp_number');
            $table->timestamp('ktp_verified_at')->nullable()->after('ktp_name');

            $table->unique('ktp_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['ktp_number']);
            $table->dropColumn(['ktp_number', 'ktp_name', 'ktp_verified_at']);
        });
    }
};
