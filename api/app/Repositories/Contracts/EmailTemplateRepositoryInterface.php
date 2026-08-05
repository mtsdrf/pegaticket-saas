<?php

namespace App\Repositories\Contracts;

use App\Models\EmailTemplate;
use Illuminate\Support\Collection;

interface EmailTemplateRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return Collection<int, EmailTemplate>
     */
    public function allForTenant(int $tenantId): Collection;

    public function findForTenant(int $tenantId, string $type): ?EmailTemplate;

    public function upsert(int $tenantId, string $type, array $data): EmailTemplate;
}
