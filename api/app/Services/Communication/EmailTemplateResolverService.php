<?php

namespace App\Services\Communication;

use App\Models\EmailTemplate;

/**
 * Resolve o override de assunto/corpo (App\Models\EmailTemplate) de um
 * Mailable, com fallback silencioso para o texto/view hardcoded atual
 * quando não existe customização — comportamento padrão de 100% dos
 * tenants hoje, nunca deve quebrar o envio. Chamado direto pelo build() de
 * cada Mailable (não é injetável via construtor porque o Laravel resolve
 * Mailables fora do container em alguns fluxos de fila).
 *
 * Type sem tenant (tenantId null, usado pelos 3 tipos de plataforma/
 * segurança) só resolve contra um EmailTemplate global (tenant_id null) —
 * na prática nunca existe, porque App\Services\EmailTemplate\EmailTemplateService
 * não permite o CRUD do tenant gravar esses types. A checagem fica genérica
 * mesmo assim, pelos 7 Mailables seguirem o mesmo caminho de código.
 */
class EmailTemplateResolverService
{
    public function resolveSubject(string $type, ?int $tenantId, string $default, array $placeholders): string
    {
        $template = $this->findActive($type, $tenantId);

        if (! $template || ! $template->subject) {
            return $default;
        }

        return $this->applyPlaceholders($template->subject, $placeholders);
    }

    public function resolveBodyHtml(string $type, ?int $tenantId, array $placeholders): ?string
    {
        $template = $this->findActive($type, $tenantId);

        if (! $template || ! $template->body_html) {
            return null;
        }

        return $this->applyPlaceholders($template->body_html, $placeholders);
    }

    private function findActive(string $type, ?int $tenantId): ?EmailTemplate
    {
        if ($tenantId) {
            $tenantTemplate = EmailTemplate::query()
                ->where('tenant_id', $tenantId)
                ->where('type', $type)
                ->whereNull('deleted_at')
                ->first();

            if ($tenantTemplate) {
                return $tenantTemplate;
            }
        }

        return EmailTemplate::query()
            ->whereNull('tenant_id')
            ->where('type', $type)
            ->whereNull('deleted_at')
            ->first();
    }

    private function applyPlaceholders(string $content, array $placeholders): string
    {
        $search = array_map(fn (string $key) => '{{'.$key.'}}', array_keys($placeholders));

        return str_replace($search, array_values($placeholders), $content);
    }
}
