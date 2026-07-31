<?php

namespace App\Events\Portal;

class FinalCustomerRegistered
{
    public function __construct(
        public string $finalCustomerUuid,
        public string $email,
    ) {
    }
}
