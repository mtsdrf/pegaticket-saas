<?php

namespace App\Events\Payment;

class TenantPagBankConnectionStatusChanged
{
    public function __construct(
        public int $tenantId,
        public int $actorId,
        public string $fromStatus,
        public string $toStatus
    ) {}
}
