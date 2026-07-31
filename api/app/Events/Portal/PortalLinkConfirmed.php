<?php

namespace App\Events\Portal;

class PortalLinkConfirmed
{
    public function __construct(
        public string $finalCustomerUuid,
        public string $tenantUuid,
        public string $clientUuid,
    ) {
    }
}
