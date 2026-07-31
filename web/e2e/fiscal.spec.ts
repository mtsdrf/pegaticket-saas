import { expect, test } from '@playwright/test'
import { mockApiRoute, mockAuthenticatedShell } from './support/api'

test.describe('Fiscal', () => {
  test('opera as telas de regras tributárias e perfis fiscais com prontidão e remoção controlada', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['tax-rules:read', 'tax-rules:create', 'tax-rules:update', 'tax-rules:delete', 'dashboard:read'],
      tenantFunctionalities: ['fiscal', 'dashboard'],
    })

    let rules = [
      {
        uuid: 'tax-rule-1',
        tax_type: 'icms',
        rate_percent: 18,
        valid_from: '2026-01-01',
        valid_to: null,
        scope: { cfop: ['5102'], uf_origin: ['SP'] },
        is_active: true,
        created_at: '2026-07-28T10:00:00Z',
      },
    ]

    let profiles = [
      {
        uuid: 'fiscal-profile-1',
        name: 'Venda balcão NFC-e',
        operation_nature: 'sale',
        document_type: 'nfce',
        default_cfop: '5102',
        scope: { order_origin: ['counter'], fulfillment_type: ['pickup'], destination_type: ['consumer_final'] },
        description: 'Perfil base para operação de balcão.',
        is_active: true,
      },
    ]

    await page.route('**/api/v1/tax-rules**', async (route) => {
      const method = route.request().method()

      if (method === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, message: 'OK', data: rules, meta: {} }),
        })
        return
      }

      if (method === 'DELETE') {
        const uuid = route.request().url().split('/').pop()?.split('?')[0]
        rules = rules.filter((rule) => rule.uuid !== uuid)
        await route.fulfill({ status: 204, body: '' })
        return
      }

      await route.fallback()
    })

    await page.route('**/api/v1/fiscal-operation-profiles**', async (route) => {
      const method = route.request().method()

      if (method === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, message: 'OK', data: profiles, meta: {} }),
        })
        return
      }

      if (method === 'DELETE') {
        const uuid = route.request().url().split('/').pop()?.split('?')[0]
        profiles = profiles.filter((profile) => profile.uuid !== uuid)
        await route.fulfill({ status: 204, body: '' })
        return
      }

      await route.fallback()
    })

    await mockApiRoute(page, {
      path: '/fiscal-readiness',
      body: {
        status: 'attention',
        score_percent: 67,
        checks: [
          { key: 'issuer', label: 'Cadastro da empresa', status: 'ok', details: 'Emitente com CNPJ e endereço completos.' },
          { key: 'products', label: 'Produtos fiscais', status: 'warning', details: 'Ainda faltam NCM e CFOP em parte do catálogo.' },
        ],
      },
    })

    await page.goto('/configuracoes/regras-tributarias')

    await expect(page.getByRole('heading', { name: 'Regras tributárias' })).toBeVisible()
    await expect(page.getByText('Cadastre alíquotas e vigências')).toBeVisible()
    await expect(page.getByText('18%')).toBeVisible()
    await expect(page.getByText('CFOP: 5102')).toBeVisible()

    await page.getByLabel('Excluir regra ICMS').click()
    await expect(page.getByRole('dialog', { name: 'Excluir regra tributária' })).toBeVisible()
    await page.getByRole('button', { name: 'Excluir' }).click()
    await expect(page.getByLabel('Excluir regra ICMS')).toHaveCount(0)

    await page.goto('/configuracoes/perfis-fiscais')

    await expect(page.getByRole('heading', { name: 'Perfis fiscais' })).toBeVisible()
    await expect(page.getByText('Prontidão fiscal da empresa')).toBeVisible()
    await expect(page.getByText('67% dos pré-requisitos fiscais concluídos.')).toBeVisible()
    await expect(page.getByText('Produtos fiscais')).toBeVisible()
    await expect(page.getByText('Venda balcão NFC-e')).toBeVisible()
    await expect(page.getByText('CFOP 5102')).toBeVisible()

    await page.getByRole('button', { name: 'Editar perfil Venda balcão NFC-e' }).click()
    await expect(page).toHaveURL(/\/configuracoes\/perfis-fiscais\/fiscal-profile-1\/editar$/)
    await expect(page.getByLabel('Nome do perfil')).toHaveValue('Venda balcão NFC-e')
    await expect(page.getByLabel('CFOP base')).toHaveValue('5102')

    await page.goto('/configuracoes/perfis-fiscais')
    await page.getByRole('button', { name: 'Excluir perfil Venda balcão NFC-e' }).click()
    await expect(page.getByRole('dialog', { name: 'Excluir perfil fiscal' })).toBeVisible()
    await page.getByRole('button', { name: 'Excluir' }).click()
    await expect(page.getByText('Venda balcão NFC-e')).toHaveCount(0)
  })
})
