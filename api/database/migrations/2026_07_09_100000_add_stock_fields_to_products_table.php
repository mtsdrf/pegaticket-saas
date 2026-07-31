<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sku')->nullable()->after('name');
            $table->string('barcode')->nullable()->after('sku');
            $table->string('brand')->nullable()->after('barcode');
            $table->string('unit')->default('un')->after('brand');

            $table->boolean('is_lot_controlled')->default(false)->after('unit');
            $table->boolean('is_expiry_controlled')->default(false)->after('is_lot_controlled');
            $table->boolean('is_serial_controlled')->default(false)->after('is_expiry_controlled');

            $table->integer('min_stock')->nullable()->after('is_serial_controlled');
            $table->integer('max_stock')->nullable()->after('min_stock');
            $table->integer('reorder_point')->nullable()->after('max_stock');
            $table->integer('reorder_qty')->nullable()->after('reorder_point');

            $table->decimal('last_purchase_cost', 10, 2)->nullable()->after('reorder_qty');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'sku',
                'barcode',
                'brand',
                'unit',
                'is_lot_controlled',
                'is_expiry_controlled',
                'is_serial_controlled',
                'min_stock',
                'max_stock',
                'reorder_point',
                'reorder_qty',
                'last_purchase_cost',
            ]);
        });
    }
};
