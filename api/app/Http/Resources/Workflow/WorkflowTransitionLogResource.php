<?php

namespace App\Http\Resources\Workflow;

use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowTransitionLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'workflow_type' => $this->workflow_type,
            'entity_uuid' => $this->entity_uuid,
            'from_stage' => $this->from_stage,
            'to_stage' => $this->to_stage,
            'transition_type' => $this->transition_type,
            'reason' => $this->reason,
            'meta' => $this->meta,
            'moved_at' => $this->moved_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn() => $this->user ? [
                'uuid' => $this->user->uuid,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
        ];
    }
}
