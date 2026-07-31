<?php

namespace App\Events\Portal;

class PortalOtpVerified
{
    public function __construct(
        public string $finalCustomerUuid,
    ) {
    }
}
