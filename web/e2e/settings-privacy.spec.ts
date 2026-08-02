import { expect, test } from '@playwright/test'
import { mockApiRoute, mockApiError, mockAuthenticatedShell } from './support/api'

function makePrivacyRequest(overrides: Partial<{
  uuid: string
  requester_name: string
  requester_email: string | null
  requester_role: 'empresa' | 'usuario_interno' | 'titular_final' | 'outro'
  request_type: 'acesso' | 'correcao' | 'exclusao' | 'anonimizacao' | 'oposicao' | 'outro'
  channel: 'email' | 'whatsapp' | 'telefone' | 'atendimento_interno' | 'outro' | null
  status: 'open' | 'in_progress' | 'completed' | 'rejected'
  subject: string
  description: string
  resolution_notes: string | null
  requested_at: string | null
}> = {}) {
  return {
    uuid: overrides.uuid ?? 'privacy-request-1',
    requester_name: overrides.requester_name ?? 'Maria Operacional',
    requester_email: overrides.requester_email ?? 'maria@empresa.com',
    requester_role: overrides.requester_role ?? 'empresa',
    request_type: overrides.request_type ?? 'acesso',
    channel: overrides.channel ?? 'email',
    status: overrides.status ?? 'open',
    subject: overrides.subject ?? 'Solicitação de acesso aos dados',
    description: overrides.description ?? 'Cliente pediu cópia dos dados registrados na operação.',
    resolution_notes: overrides.resolution_notes ?? null,
    requested_at: overrides.requested_at ?? '2026-07-28T12:00:00Z',
    resolved_at: null,
    created_at: '2026-07-28T12:00:00Z',
    updated_at: '2026-07-28T12:00:00Z',
    requested_by_user: {
      uuid: 'user-qa-1',
      name: 'Usuário QA',
      email: 'qa@pegaticket.com',
    },
  }
}

test.describe('Dados e privacidade', () => {
  test('renderiza o bloco com documentos públicos, orientações e ações de privacidade disponíveis', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['tenant-profile:read', 'tenant-profile:update', 'tenant-profile:export', 'dashboard:read'],
      tenantFunctionalities: ['tenant-profile', 'dashboard'],
    })

    let privacyRequests = [
      makePrivacyRequest(),
    ]

    await mockApiRoute(page, {
      path: '/legal-documents/terms',
      body: {
        uuid: 'legal-terms-1',
        type: 'terms',
        version: '1.3',
        content: 'Termos',
        published_at: '2026-07-20T12:00:00Z',
      },
    })

    await mockApiRoute(page, {
      path: '/legal-documents/privacy',
      body: {
        uuid: 'legal-privacy-1',
        type: 'privacy',
        version: '2.1',
        content: 'Privacidade',
        published_at: '2026-07-21T12:00:00Z',
      },
    })

    await page.route('**/api/v1/tenant-profile/privacy-requests*', async (route) => {
      const method = route.request().method()

      if (method === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            message: 'OK',
            data: privacyRequests,
            meta: {},
          }),
        })
        return
      }

      if (method === 'PUT') {
        const payload = JSON.parse(route.request().postData() ?? '{}') as { status: 'in_progress' | 'completed' | 'rejected'; resolution_notes?: string | null }
        const uuid = route.request().url().split('/').pop()?.split('?')[0] ?? ''
        privacyRequests = privacyRequests.map((item) =>
          item.uuid === uuid
            ? {
                ...item,
                status: payload.status,
                resolution_notes: payload.resolution_notes ?? null,
              }
            : item,
        )

        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            message: 'OK',
            data: privacyRequests.find((item) => item.uuid === uuid),
            meta: {},
          }),
        })
        return
      }

      await route.fallback()
    })

    await page.goto('/configuracoes/dados-privacidade')

    await expect(page.getByText('Este espaço reúne o mínimo operacional de privacidade do PegaTicket para a sua empresa')).toBeVisible()
    await expect(page.getByText('A PegaTicket atua como controladora')).toBeVisible()
    await expect(page.getByText('Checklist operacional antes de ativar a empresa')).toBeVisible()
    await expect(page.getByText('Anonimização ampla e automação completa de requisições de titulares ainda não são self-service no produto.')).toBeVisible()
    await expect(page.getByText('Versão 1.3')).toBeVisible()
    await expect(page.getByText('Versão 2.1')).toBeVisible()
    await expect(page.getByRole('link', { name: 'Abrir' })).toHaveCount(2)
    await expect(page.getByText('Solicitação de acesso aos dados')).toBeVisible()
    await expect(page.getByText('Acesso / exportação · Empresa contratante · E-mail')).toBeVisible()

    await expect(page.getByRole('button', { name: 'Marcar em análise' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Marcar concluída' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Encerrar sem atendimento integral' })).toBeVisible()
  })

  test('registra uma nova solicitação e mostra mensagem clara quando a exportação atinge o limite por hora', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['tenant-profile:read', 'tenant-profile:update', 'tenant-profile:export', 'dashboard:read'],
      tenantFunctionalities: ['tenant-profile', 'dashboard'],
    })

    let privacyRequests = [] as ReturnType<typeof makePrivacyRequest>[]

    await mockApiRoute(page, {
      path: '/legal-documents/terms',
      body: {
        uuid: 'legal-terms-1',
        type: 'terms',
        version: '1.3',
        content: 'Termos',
        published_at: '2026-07-20T12:00:00Z',
      },
    })

    await mockApiRoute(page, {
      path: '/legal-documents/privacy',
      body: {
        uuid: 'legal-privacy-1',
        type: 'privacy',
        version: '2.1',
        content: 'Privacidade',
        published_at: '2026-07-21T12:00:00Z',
      },
    })

    await page.route('**/api/v1/tenant-profile/privacy-requests*', async (route) => {
      const method = route.request().method()

      if (method === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            message: 'OK',
            data: privacyRequests,
            meta: {},
          }),
        })
        return
      }

      if (method === 'POST') {
        const payload = route.request().postDataJSON() as {
          requester_name: string
          requester_email?: string | null
          requester_role: 'empresa' | 'usuario_interno' | 'titular_final' | 'outro'
          request_type: 'acesso' | 'correcao' | 'exclusao' | 'anonimizacao' | 'oposicao' | 'outro'
          channel?: 'email' | 'whatsapp' | 'telefone' | 'atendimento_interno' | 'outro' | null
          subject: string
          description: string
        }

        const created = makePrivacyRequest({
          uuid: 'privacy-request-new-1',
          requester_name: payload.requester_name,
          requester_email: payload.requester_email ?? null,
          requester_role: payload.requester_role,
          request_type: payload.request_type,
          channel: payload.channel ?? null,
          subject: payload.subject,
          description: payload.description,
          status: 'open',
          resolution_notes: null,
        })
        privacyRequests = [created, ...privacyRequests]

        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            message: 'OK',
            data: created,
            meta: {},
          }),
        })
        return
      }

      await route.fallback()
    })

    await mockApiError(page, {
      method: 'POST',
      path: '/tenant-data-export',
      status: 429,
      message: 'Too Many Attempts.',
      code: 'TOO_MANY_REQUESTS',
    })

    await page.goto('/configuracoes/dados-privacidade')

    await expect(page.getByText('Nenhuma solicitação registrada ainda.')).toBeVisible()

    await page.getByLabel('Nome do solicitante').fill('João do Financeiro')
    await page.getByLabel('E-mail do solicitante').fill('joao.financeiro@empresa.com')
    await page.getByLabel('Assunto').fill('Pedido de correção cadastral')
    await page.getByLabel('Descrição da solicitação').fill('A empresa solicitou correção do e-mail e revisão dos dados exportados.')
    await page.getByRole('button', { name: 'Registrar solicitação' }).click()

    await expect(page.getByText('Solicitação registrada. Agora você já tem um histórico interno para acompanhar esse atendimento.')).toBeVisible()
    await expect(page.getByText('Pedido de correção cadastral')).toBeVisible()
    await expect(page.getByText('João do Financeiro · joao.financeiro@empresa.com')).toBeVisible()

    await page.getByRole('button', { name: 'Exportar meus dados' }).click()

    await expect(page.getByText('Limite de exportações atingido (3 por hora). Aguarde um pouco e tente novamente.')).toBeVisible()
  })

  test('oculta gestão de solicitações quando o perfil só pode exportar dados da empresa', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['tenant-profile:export', 'dashboard:read'],
      tenantFunctionalities: ['tenant-profile', 'dashboard'],
    })

    await mockApiRoute(page, {
      path: '/legal-documents/terms',
      body: {
        uuid: 'legal-terms-1',
        type: 'terms',
        version: '1.3',
        content: 'Termos',
        published_at: '2026-07-20T12:00:00Z',
      },
    })

    await mockApiRoute(page, {
      path: '/legal-documents/privacy',
      body: {
        uuid: 'legal-privacy-1',
        type: 'privacy',
        version: '2.1',
        content: 'Privacidade',
        published_at: '2026-07-21T12:00:00Z',
      },
    })

    await page.goto('/configuracoes/dados-privacidade')

    await expect(page.getByText('Seu perfil atual pode consultar este guia, mas não pode registrar ou alterar solicitações de privacidade nesta empresa.')).toBeVisible()
    await expect(page.getByText('Seu perfil atual não pode consultar o histórico de solicitações de privacidade desta empresa.')).toBeVisible()
    await expect(page.getByLabel('Nome do solicitante')).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Marcar em análise' })).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Exportar meus dados' })).toBeVisible()
  })
})
