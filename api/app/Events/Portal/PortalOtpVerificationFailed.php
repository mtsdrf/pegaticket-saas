<?php

namespace App\Events\Portal;

class PortalOtpVerificationFailed
{
    public function __construct(
        public string $email,
        public string $reason,
    ) {
    }
}
