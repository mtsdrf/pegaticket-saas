<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ActionsSeeder::class,
            FunctionalitiesSeeder::class,
            InitialPlansSeeder::class,
            GroupsSeeder::class,
            AdminUserSeeder::class,
            AdminPermissionsSeeder::class,
            LegalDocumentsSeeder::class,
            PlanPricesSeeder::class,
        ]);
    }

}
