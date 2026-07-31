<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('store_business_hours', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // 0=domingo ... 6=sábado (Carbon::dayOfWeek), exatamente 7 linhas
            // por tenant. Sem linha para um dia = tratado como fechado nesse
            // dia (fail-safe: não vender por omissão).
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_closed')->default(false);

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'day_of_week'], 'uniq_tenant_business_hour_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_business_hours');
    }
};
