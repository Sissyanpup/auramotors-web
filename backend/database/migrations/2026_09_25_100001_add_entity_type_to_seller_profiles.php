<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->enum('entity_type', ['individu', 'perusahaan'])->default('individu')->after('user_id');
            $table->string('company_registration_path')->nullable()->after('npwp_path');
            $table->string('articles_of_association_path')->nullable()->after('company_registration_path');
            $table->string('ubo_declaration_path')->nullable()->after('articles_of_association_path');
        });
    }

    public function down(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->dropColumn(['entity_type', 'company_registration_path', 'articles_of_association_path', 'ubo_declaration_path']);
        });
    }
};
