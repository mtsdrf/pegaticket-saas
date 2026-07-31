<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('fiscal_provider', 40)->nullable()->after('ibge_city_code');
            $table->string('fiscal_nfe_series', 20)->nullable()->after('fiscal_provider');
            $table->string('fiscal_nfce_series', 20)->nullable()->after('fiscal_nfe_series');
            $table->string('fiscal_nfse_series', 20)->nullable()->after('fiscal_nfce_series');
            $table->unsignedInteger('fiscal_next_nfe_number')->nullable()->after('fiscal_nfse_series');
            $table->unsignedInteger('fiscal_next_nfce_number')->nullable()->after('fiscal_next_nfe_number');
            $table->unsignedInteger('fiscal_next_nfse_number')->nullable()->after('fiscal_next_nfce_number');
            $table->string('fiscal_nfce_csc_id', 40)->nullable()->after('fiscal_next_nfse_number');
            $table->text('fiscal_nfce_csc_code')->nullable()->after('fiscal_nfce_csc_id');
            $table->text('fiscal_provider_api_token')->nullable()->after('fiscal_nfce_csc_code');
            $table->binary('fiscal_certificate_a1_data')->nullable()->after('fiscal_provider_api_token');
            $table->string('fiscal_certificate_a1_name')->nullable()->after('fiscal_certificate_a1_data');
            $table->string('fiscal_certificate_a1_mime', 100)->nullable()->after('fiscal_certificate_a1_name');
            $table->text('fiscal_certificate_a1_password')->nullable()->after('fiscal_certificate_a1_mime');
            $table->timestamp('fiscal_certificate_a1_updated_at')->nullable()->after('fiscal_certificate_a1_password');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'fiscal_provider',
                'fiscal_nfe_series',
                'fiscal_nfce_series',
                'fiscal_nfse_series',
                'fiscal_next_nfe_number',
                'fiscal_next_nfce_number',
                'fiscal_next_nfse_number',
                'fiscal_nfce_csc_id',
                'fiscal_nfce_csc_code',
                'fiscal_provider_api_token',
                'fiscal_certificate_a1_data',
                'fiscal_certificate_a1_name',
                'fiscal_certificate_a1_mime',
                'fiscal_certificate_a1_password',
                'fiscal_certificate_a1_updated_at',
            ]);
        });
    }
};
