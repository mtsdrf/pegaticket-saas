<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table): void {
            $table->json('provider_response_payload')->nullable()->after('payload_snapshot');
            $table->timestamp('provider_status_checked_at')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table): void {
            $table->dropColumn([
                'provider_response_payload',
                'provider_status_checked_at',
            ]);
        });
    }
};
