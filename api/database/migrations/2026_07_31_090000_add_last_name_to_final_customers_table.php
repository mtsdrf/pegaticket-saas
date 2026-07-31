<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FinalCustomer absorve Client (decisão 2026-07-31): last_name é atributo
 * de identidade GLOBAL, junto com name/email.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('final_customers', function (Blueprint $table) {
            $table->string('last_name')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('final_customers', function (Blueprint $table) {
            $table->dropColumn('last_name');
        });
    }
};
