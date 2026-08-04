<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_finance_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->decimal('platform_fee_fixed_amount', 10, 2)->default(0);
            $table->unsignedSmallInteger('default_settlement_offset_days')->default(1);
            $table->string('settlement_reference', 30)->default('event_end');
            $table->boolean('split_custody_enabled')->default(true);
            $table->boolean('extra_reserve_enabled')->default(false);

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_finance_settings');
    }
};
