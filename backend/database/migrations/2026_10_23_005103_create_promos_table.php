<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Promo pembeli dikelola admin. kind=auto → otomatis berlaku & tampil
        // sebagai harga coret di katalog; kind=voucher → butuh kode saat checkout.
        // Manfaat ditanggung platform (subsidi), hak penjual tidak berkurang.
        Schema::create('promos', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->nullable()->unique();
            $table->string('kind', 10);
            $table->string('benefit', 20);
            $table->string('discount_type', 10)->nullable();
            $table->decimal('discount_value', 15, 2)->nullable();
            $table->decimal('max_discount', 15, 2)->nullable();
            $table->string('addon_code', 40)->nullable();
            $table->decimal('rate_cut', 6, 4)->nullable();
            $table->decimal('min_vehicle_price', 15, 2)->nullable();
            $table->string('brand', 60)->nullable();
            $table->string('badge', 30)->nullable();
            $table->string('description', 500)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('quota')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promos');
    }
};
