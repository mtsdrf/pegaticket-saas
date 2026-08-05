<?php

namespace App\Http\Resources\EmailTemplate;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recebe o shape `{type, template, placeholders}` montado por
 * App\Services\EmailTemplate\EmailTemplateService (não um Model direto —
 * `template` pode ser null quando o tenant nunca customizou o type),
 * então acessa `$this->resource` (array) em vez das props mágicas de
 * JsonResource (que não funcionam sobre array puro).
 */
class EmailTemplateResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = $this->resource;
        $template = $data['template'];

        return [
            'type' => $data['type'],
            'placeholders' => $data['placeholders'],
            'is_customized' => $template !== null,
            'subject' => $template?->subject,
            'body_html' => $template?->body_html,
            'updated_at' => $template?->updated_at,
        ];
    }
}
