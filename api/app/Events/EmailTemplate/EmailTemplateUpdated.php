<?php

namespace App\Events\EmailTemplate;

class EmailTemplateUpdated
{
    public function __construct(
        public string $emailTemplateUuid,
        public string $type,
        public int $actorId
    ) {}
}
