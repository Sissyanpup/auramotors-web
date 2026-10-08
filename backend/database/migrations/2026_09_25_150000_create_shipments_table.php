<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->unique()->constrained()->cascadeOnDelete();

            // Info dasar shipment
            $table->string('origin_country', 2);       // ISO 3166-1 alpha-2 (mis. ID)
            $table->string('destination_country', 2);
            $table->enum('shipping_mode', ['sea', 'air', 'land']);
            $table->string('carrier_name');
            $table->string('tracking_number')->nullable(); // Bill of Lading no. / AWB no.
            $table->date('estimated_arrival')->nullable();
            $table->date('actual_arrival')->nullable();

            // Cargo insurance (fields inline, bukan tabel terpisah — hanya 1 polis per shipment)
            $table->string('cargo_insurer_name')->nullable();
            $table->string('cargo_policy_number')->nullable();
            $table->decimal('cargo_coverage_amount', 14, 2)->nullable();
            $table->string('cargo_certificate_path')->nullable();

            // State
            $table->enum('status', [
                'draft',
                'logistics_prep',
                'in_transit',
                'customs_clearance',
                'delivered',
                'delayed',
            ])->default('draft');

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
