<?php

namespace App\Repositories\Contracts;

use App\Models\Client\Client;

interface ClientRepositoryInterface extends BaseRepositoryInterface
{
    public function syncCategories(Client $client, array $categoryUuids): void;
}
