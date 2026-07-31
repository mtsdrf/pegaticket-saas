<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('cnpj', 14)->nullable()->after('name');
            $table->string('ie', 30)->nullable()->after('cnpj');
            $table->string('im', 30)->nullable()->after('ie');
            $table->string('cnae', 20)->nullable()->after('im');
            $table->string('tax_regime', 30)->nullable()->after('cnae');
            $table->string('fiscal_environment', 20)->nullable()->after('tax_regime');
            $table->string('ibge_city_code', 10)->nullable()->after('fiscal_environment');
            $table->string('fiscal_provider', 20)->nullable()->after('ibge_city_code');
            $table->string('fiscal_nfe_series', 20)->nullable()->after('fiscal_provider');
            $table->string('fiscal_nfce_series', 20)->nullable()->after('fiscal_nfe_series');
            $table->string('fiscal_nfse_series', 20)->nullable()->after('fiscal_nfce_series');
            $table->unsignedInteger('fiscal_next_nfe_number')->nullable()->after('fiscal_nfse_series');
            $table->unsignedInteger('fiscal_next_nfce_number')->nullable()->after('fiscal_next_nfe_number');
            $table->unsignedInteger('fiscal_next_nfse_number')->nullable()->after('fiscal_next_nfce_number');
            $table->string('fiscal_nfce_csc_id', 20)->nullable()->after('fiscal_next_nfse_number');
            $table->text('fiscal_nfce_csc_code')->nullable()->after('fiscal_nfce_csc_id');
            $table->text('fiscal_provider_api_token')->nullable()->after('fiscal_nfce_csc_code');
            $table->longText('fiscal_certificate_a1_data')->nullable()->after('fiscal_provider_api_token');
            $table->string('fiscal_certificate_a1_name')->nullable()->after('fiscal_certificate_a1_data');
            $table->string('fiscal_certificate_a1_mime', 100)->nullable()->after('fiscal_certificate_a1_name');
            $table->string('fiscal_certificate_a1_password')->nullable()->after('fiscal_certificate_a1_mime');
            $table->timestamp('fiscal_certificate_a1_updated_at')->nullable()->after('fiscal_certificate_a1_password');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'cnpj',
                'ie',
                'im',
                'cnae',
                'tax_regime',
                'fiscal_environment',
                'ibge_city_code',
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
