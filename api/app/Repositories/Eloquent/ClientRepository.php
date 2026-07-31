<?php

namespace App\Repositories\Eloquent;

use App\Models\Client\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClientRepository extends BaseRepository implements ClientRepositoryInterface
{
    public function __construct(Client $model)
    {
        parent::__construct($model);
    }

    /**
     * Sincroniza categorias via soft delete (mesmo padrão de
     * GroupRepository::syncUsers): desativa todos os vínculos existentes e
     * reativa/cria os informados, nunca faz hard delete no pivot.
     */
    public function syncCategories(Client $client, array $categoryUuids): void
    {
        $categoryIds = DB::table('client_categories')
            ->whereIn('uuid', $categoryUuids)
            ->where('tenant_id', $client->tenant_id)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        DB::table('client_client_categories')
            ->where('client_id', $client->id)
            ->update(['deleted_at' => now()]);

        foreach ($categoryIds as $categoryId) {
            DB::table('client_client_categories')->updateOrInsert(
                ['client_id' => $client->id, 'client_category_id' => $categoryId],
                [
                    'uuid' => (string) Str::uuid(),
                    'deleted_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
