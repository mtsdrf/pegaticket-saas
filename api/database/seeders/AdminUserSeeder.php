<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'mreisf.contato@gmail.com'],
            [
                'name' => 'Administrador Principal',
                'password' => Hash::make('atlonxp233'),
                'is_active' => true,
            ]
        );
    }
}
