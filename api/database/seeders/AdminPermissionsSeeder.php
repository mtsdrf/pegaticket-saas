<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $groupId = DB::table('groups')->where('slug', 'administrators')->value('id');
        $userId = DB::table('users')->where('email', 'mreisf.contato@gmail.com')->value('id');

        // 1) Vincular usuário ao grupo (SOFT-SAFE + UUID)
        $pivot = DB::table('group_user')
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->first();

        if (!$pivot) {
            DB::table('group_user')->insert([
                'uuid' => (string) Str::uuid(),
                'group_id' => $groupId,
                'user_id' => $userId,
                'created_by' => null,
                'updated_by' => null,
                'deleted_by' => null,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            // restaura se estava soft deleted
            DB::table('group_user')
                ->where('group_id', $groupId)
                ->where('user_id', $userId)
                ->update([
                    'deleted_at' => null,
                    'deleted_by' => null,
                    'updated_at' => now(),
                ]);
        }

        // 2) Permissões totais (SOFT-SAFE + UUID)
        $functionalityIds = DB::table('functionalities')->pluck('id');
        $actionIds = DB::table('actions')->pluck('id');

        foreach ($functionalityIds as $fid) {
            foreach ($actionIds as $aid) {

                $perm = DB::table('group_permissions')
                    ->where('group_id', $groupId)
                    ->where('functionality_id', $fid)
                    ->where('action_id', $aid)
                    ->first();

                if (!$perm) {
                    DB::table('group_permissions')->insert([
                        'uuid' => (string) Str::uuid(),
                        'group_id' => $groupId,
                        'functionality_id' => $fid,
                        'action_id' => $aid,
                        'created_by' => null,
                        'updated_by' => null,
                        'deleted_by' => null,
                        'deleted_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    // restaura se estava soft deleted
                    DB::table('group_permissions')
                        ->where('group_id', $groupId)
                        ->where('functionality_id', $fid)
                        ->where('action_id', $aid)
                        ->update([
                            'deleted_at' => null,
                            'deleted_by' => null,
                            'updated_at' => now(),
                        ]);
                }
            }
        }
    }
}
