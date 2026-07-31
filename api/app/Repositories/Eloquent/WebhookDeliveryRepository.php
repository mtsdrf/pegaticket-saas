<?php

namespace App\Repositories\Eloquent;

use App\Models\Webhook\WebhookDelivery;
use App\Repositories\Contracts\WebhookDeliveryRepositoryInterface;

/**
 * Repository write-only (log técnico, sem edição/exclusão/paginação
 * genérica) — não estende BaseRepository de propósito, mesmo raciocínio do
 * Model (WebhookDelivery não é BaseModel).
 */
class WebhookDeliveryRepository implements WebhookDeliveryRepositoryInterface
{
    public function __construct(private WebhookDelivery $model)
    {
    }

    public function create(array $data): WebhookDelivery
    {
        return $this->model->create($data);
    }
}
