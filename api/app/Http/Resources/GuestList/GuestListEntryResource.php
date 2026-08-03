<?php

namespace App\Http\Resources\GuestList;

use Illuminate\Http\Resources\Json\JsonResource;

class GuestListEntryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'document' => $this->document,
            'redeemed_at' => $this->redeemed_at,
            'invite_url' => rtrim((string) config('app.frontend_url'), '/').'/convite-ingresso/'.$this->token,
        ];
    }
}
