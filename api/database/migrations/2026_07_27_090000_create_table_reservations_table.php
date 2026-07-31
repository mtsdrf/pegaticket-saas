<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('table_reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_id')->nullable()->constrained('tables')->nullOnDelete();
            $table->foreignId('seated_comanda_id')->nullable()->constrained('comandas')->nullOnDelete();

            $table->string('customer_name');
            $table->string('customer_phone', 30)->nullable();
            $table->string('customer_email')->nullable();
            $table->unsignedInteger('party_size');
            $table->timestamp('scheduled_for')->index();
            $table->unsignedInteger('duration_minutes')->default(120);
            $table->string('status', 20)->default('confirmed')->index();
            $table->string('source', 20)->default('internal')->index();
            $table->text('notes')->nullable();
            $table->text('cancelled_reason')->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable()->index();
            $table->timestamp('seated_at')->nullable();
            $table->unsignedBigInteger('seated_by')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable()->index();
            $table->timestamp('no_show_at')->nullable();
            $table->unsignedBigInteger('no_show_by')->nullable()->index();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status', 'scheduled_for'], 'idx_tbl_res_tenant_status_sched');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_reservations');
    }
};
