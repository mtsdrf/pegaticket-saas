<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClientPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $groupId = DB::table('groups')->where('slug', 'clients')->value('id');

        if (!$groupId) {
            return;
        }

        DB::table('group_permissions')->where('group_id', $groupId)->delete();
    }
}
