<?php

namespace App\Events\Report;

class ReceivableInteractionCreated
{
    public function __construct(
        public string $interactionUuid,
        public string $orderUuid,
        public ?string $installmentUuid,
        public string $interactionType,
        public int $actorId
    ) {
    }
}
