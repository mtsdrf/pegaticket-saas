<?php

namespace App\Repositories\Eloquent;

use App\Models\EmailTemplate;
use App\Repositories\Contracts\EmailTemplateRepositoryInterface;
use Illuminate\Support\Collection;

class EmailTemplateRepository extends BaseRepository implements EmailTemplateRepositoryInterface
{
    public function __construct(EmailTemplate $model)
    {
        parent::__construct($model);
    }

    public function allForTenant(int $tenantId): Collection
    {
        return EmailTemplate::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->get();
    }

    public function findForTenant(int $tenantId, string $type): ?EmailTemplate
    {
        return EmailTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('type', $type)
            ->whereNull('deleted_at')
            ->first();
    }

    public function upsert(int $tenantId, string $type, array $data): EmailTemplate
    {
        $template = $this->findForTenant($tenantId, $type);

        if ($template) {
            $template->fill($data);
            $template->save();

            return $template->fresh();
        }

        return EmailTemplate::create(array_merge($data, [
            'tenant_id' => $tenantId,
            'type' => $type,
        ]));
    }
}
