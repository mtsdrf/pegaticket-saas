<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameTableIfExists('orders', 'sales');
        $this->renameTableIfExists('order_items', 'sale_items');
        $this->renameTableIfExists('order_installments', 'sale_installments');
        $this->renameTableIfExists('order_prep_links', 'sale_prep_links');
        $this->renameTableIfExists('order_ratings', 'sale_ratings');
        $this->renameTableIfExists('order_item_options', 'sale_item_options');

        $this->renameColumnIfExists('sale_items', 'order_id', 'sale_id');
        $this->renameColumnIfExists('sale_installments', 'order_id', 'sale_id');
        $this->renameColumnIfExists('sale_prep_links', 'order_id', 'sale_id');
        $this->renameColumnIfExists('sale_ratings', 'order_id', 'sale_id');
        $this->renameColumnIfExists('coupon_redemptions', 'order_id', 'sale_id');
        $this->renameColumnIfExists('cashback_earnings', 'order_id', 'sale_id');
        $this->renameColumnIfExists('cashback_redemptions', 'order_id', 'sale_id');
        $this->renameColumnIfExists('sale_refunds', 'order_id', 'sale_id');
        $this->renameColumnIfExists('tickets', 'order_item_id', 'sale_item_id');
        $this->renameColumnIfExists('inventory_holds', 'converted_order_id', 'converted_sale_id');
        $this->renameColumnIfExists('tenants', 'next_order_code', 'next_sale_code');
    }

    public function down(): void
    {
        $this->renameColumnIfExists('tenants', 'next_sale_code', 'next_order_code');
        $this->renameColumnIfExists('inventory_holds', 'converted_sale_id', 'converted_order_id');
        $this->renameColumnIfExists('tickets', 'sale_item_id', 'order_item_id');
        $this->renameColumnIfExists('sale_refunds', 'sale_id', 'order_id');
        $this->renameColumnIfExists('cashback_redemptions', 'sale_id', 'order_id');
        $this->renameColumnIfExists('cashback_earnings', 'sale_id', 'order_id');
        $this->renameColumnIfExists('coupon_redemptions', 'sale_id', 'order_id');
        $this->renameColumnIfExists('sale_ratings', 'sale_id', 'order_id');
        $this->renameColumnIfExists('sale_prep_links', 'sale_id', 'order_id');
        $this->renameColumnIfExists('sale_installments', 'sale_id', 'order_id');
        $this->renameColumnIfExists('sale_items', 'sale_id', 'order_id');

        $this->renameTableIfExists('sale_item_options', 'order_item_options');
        $this->renameTableIfExists('sale_ratings', 'order_ratings');
        $this->renameTableIfExists('sale_prep_links', 'order_prep_links');
        $this->renameTableIfExists('sale_installments', 'order_installments');
        $this->renameTableIfExists('sale_items', 'order_items');
        $this->renameTableIfExists('sales', 'orders');
    }

    private function renameTableIfExists(string $from, string $to): void
    {
        if (Schema::hasTable($from) && !Schema::hasTable($to)) {
            Schema::rename($from, $to);
        }
    }

    private function renameColumnIfExists(string $table, string $from, string $to): void
    {
        if (!Schema::hasTable($table) || Schema::hasColumn($table, $to) || !Schema::hasColumn($table, $from)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($from, $to) {
            $table->renameColumn($from, $to);
        });
    }
};
