<?php

namespace App\Repositories\Contracts;

use App\Models\Webhook\WebhookDelivery;

interface WebhookDeliveryRepositoryInterface
{
    public function create(array $data): WebhookDelivery;
}
