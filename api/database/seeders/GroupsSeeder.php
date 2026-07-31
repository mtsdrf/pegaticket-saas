<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Group\Group;
use Illuminate\Support\Str;

class GroupsSeeder extends Seeder
{
    public function run(): void
    {
        Group::updateOrCreate(
            ['slug' => 'administrators'],
            ['name' => 'Administradores', 'slug' => 'administrators', 'is_active' => true]
        );

        Group::updateOrCreate(
            ['slug' => 'clients'],
            ['name' => 'Empresas', 'slug' => 'clients', 'is_active' => true]
        );
    }
}
