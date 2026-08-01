<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('functionalities')
            ->where('slug', 'orders')
            ->update([
                'slug' => 'sales',
                'name' => 'Vendas',
                'description' => 'Gestão de vendas',
                'updated_at' => now(),
            ]);

        DB::table('functionalities')
            ->where('slug', 'storefront-orders')
            ->update([
                'slug' => 'storefront-sales',
                'name' => 'Vendas Online',
                'description' => 'Gestão das vendas geradas pela bilheteria online',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('functionalities')
            ->where('slug', 'sales')
            ->update([
                'slug' => 'orders',
                'name' => 'Pedidos',
                'description' => 'Gestão de pedidos',
                'updated_at' => now(),
            ]);

        DB::table('functionalities')
            ->where('slug', 'storefront-sales')
            ->update([
                'slug' => 'storefront-orders',
                'name' => 'Vendas Online',
                'description' => 'Gestão das vendas geradas pela bilheteria online (aprovar, cancelar, despachar, entregar)',
                'updated_at' => now(),
            ]);
    }
};
