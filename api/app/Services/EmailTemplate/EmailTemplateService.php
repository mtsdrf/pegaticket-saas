<?php

namespace App\Services\EmailTemplate;

use App\DTOs\EmailTemplate\UpsertEmailTemplateDTO;
use App\Events\EmailTemplate\EmailTemplateReset;
use App\Events\EmailTemplate\EmailTemplateUpdated;
use App\Exceptions\InvalidEmailTemplateTypeException;
use App\Models\EmailTemplate;
use App\Repositories\Contracts\EmailTemplateRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * CRUD de templates de e-mail configuráveis por tenant. Só os `type`
 * listados em CUSTOMIZABLE_TYPES podem ser editados por aqui — os demais
 * (`password_reset`, `portal_otp`, `email_confirmation`) são fluxos de
 * segurança/plataforma e ficam de fora do editor do tenant por decisão de
 * produto (ver CLAUDE.md/tarefa original): mexer no texto de um e-mail que
 * carrega token/OTP é mais sensível que mexer num e-mail de conteúdo.
 */
class EmailTemplateService
{
    public const CUSTOMIZABLE_TYPES = [
        'ticket_delivery',
        'event_reminder',
        'recompra_nudge',
        'waitlist_available',
        'tenant_user_invite',
    ];

    /**
     * Placeholders disponíveis em `subject`/`body_html` por type, documentado
     * aqui para o CRUD expor ao staff (ex.: tela de edição mostra a lista).
     * Substituição é `str_replace` simples via EmailTemplateResolverService,
     * sem motor de template genérico.
     */
    public const PLACEHOLDERS = [
        'ticket_delivery' => ['nome_comprador', 'codigo_venda', 'quantidade_ingressos', 'link'],
        'event_reminder' => ['nome_comprador', 'codigo_venda', 'quantidade_ingressos', 'link'],
        'recompra_nudge' => ['cliente', 'empresa', 'link'],
        'waitlist_available' => ['tipo_ingresso', 'link'],
        'tenant_user_invite' => ['nome', 'empresa', 'link'],
    ];

    public function __construct(
        private EmailTemplateRepositoryInterface $repository
    ) {}

    /**
     * @return array<int, array{type: string, template: EmailTemplate|null, placeholders: string[]}>
     */
    public function listForTenant(int $tenantId): array
    {
        $existing = $this->repository->allForTenant($tenantId)->keyBy('type');

        return array_map(fn (string $type) => [
            'type' => $type,
            'template' => $existing->get($type),
            'placeholders' => self::PLACEHOLDERS[$type],
        ], self::CUSTOMIZABLE_TYPES);
    }

    public function findForTenant(int $tenantId, string $type): array
    {
        $this->assertCustomizable($type);

        return [
            'type' => $type,
            'template' => $this->repository->findForTenant($tenantId, $type),
            'placeholders' => self::PLACEHOLDERS[$type],
        ];
    }

    public function upsert(UpsertEmailTemplateDTO $dto): EmailTemplate
    {
        $this->assertCustomizable($dto->type);

        return DB::transaction(function () use ($dto) {
            $template = $this->repository->upsert($dto->tenantId, $dto->type, [
                'subject' => $dto->subject,
                'body_html' => $dto->bodyHtml,
            ]);

            event(new EmailTemplateUpdated(
                emailTemplateUuid: $template->uuid,
                type: $dto->type,
                actorId: Auth::id()
            ));

            return $template;
        });
    }

    public function reset(int $tenantId, string $type): void
    {
        $this->assertCustomizable($type);

        $template = $this->repository->findForTenant($tenantId, $type);

        if (! $template) {
            return;
        }

        DB::transaction(function () use ($template, $tenantId, $type) {
            $this->repository->delete($template);

            event(new EmailTemplateReset(
                tenantId: $tenantId,
                type: $type,
                actorId: Auth::id()
            ));
        });
    }

    private function assertCustomizable(string $type): void
    {
        if (! in_array($type, self::CUSTOMIZABLE_TYPES, true)) {
            throw new InvalidEmailTemplateTypeException(
                __('messages.email_template.invalid_type', ['type' => $type])
            );
        }
    }
}
