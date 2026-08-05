<?php

namespace App\Events\EmailTemplate;

class EmailTemplateReset
{
    public function __construct(
        public int $tenantId,
        public string $type,
        public int $actorId
    ) {}
}
