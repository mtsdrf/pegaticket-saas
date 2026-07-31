<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('ncm', 10)->nullable()->after('brand');
            $table->string('cest', 10)->nullable()->after('ncm');
            $table->string('origin', 4)->nullable()->after('cest');
            $table->string('default_cfop', 10)->nullable()->after('origin');
            $table->string('csosn_cst', 10)->nullable()->after('default_cfop');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['ncm', 'cest', 'origin', 'default_cfop', 'csosn_cst']);
        });
    }
};
