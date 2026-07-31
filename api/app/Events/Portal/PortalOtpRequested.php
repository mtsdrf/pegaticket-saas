<?php

namespace App\Events\Portal;

class PortalOtpRequested
{
    public function __construct(
        public string $finalCustomerUuid,
    ) {
    }
}
