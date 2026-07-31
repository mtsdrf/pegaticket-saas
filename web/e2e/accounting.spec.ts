import { expect, test } from '@playwright/test'
import { mockAccountingShell } from './support/api'

test.describe('Portal do contador', () => {
  test('cobre empresas, solicitação de acesso, relatórios, dados fiscais e pendências do contador', async ({ page }) => {
    const approvedLink = {
      uuid: 'accounting-link-approved',
      status: 'approved' as const,
      scopes: ['financial.read', 'fiscal.read', 'fiscal.write', 'reports.read'] as const,
      requested_at: '2026-07-20T10:00:00Z',
      approved_at: '2026-07-21T10:00:00Z',
      revoked_at: null,
      tenant: {
        uuid: 'tenant-qa-1',
        name: 'Padaria Centro',
        cnpj: '11222333000144',
      },
    }

    const pendingLink = {
      uuid: 'accounting-link-pending',
      status: 'pending' as const,
      scopes: [] as const,
      requested_at: '2026-07-28T09:00:00Z',
      approved_at: null,
      revoked_at: null,
      tenant: {
        uuid: 'tenant-qa-2',
        name: 'Mercado Bairro',
        cnpj: '22333444000155',
      },
    }

    let links = [approvedLink, pendingLink]
    let sentMessages = [
      {
        uuid: 'accounting-message-1',
        sender_type: 'tenant',
        body: 'Precisamos revisar o cadastro fiscal dos clientes premium.',
        due_date: '2026-08-02',
        status: 'open',
        attachment_name: null,
        attachment_url: null,
        created_at: '2026-07-28T10:00:00Z',
      },
    ]

    let products = [
      {
        uuid: 'product-qa-1',
        name: 'Bolo de milho',
        type_name: 'Padaria',
        category_name: 'Doces',
        ncm: null,
        cest: null,
        origin: null,
        default_cfop: null,
        csosn_cst: null,
      },
    ]

    let clients = [
      {
        uuid: 'client-qa-1',
        name: 'Cliente Centro',
        phone_primary: '11999998888',
        cpf_cnpj: null,
        ie: null,
        ie_indicator: 'nao_contribuinte',
        endereco: { cidade_name: 'São Paulo' },
      },
    ]

    let taxRules = [
      {
        uuid: 'tax-rule-qa-1',
        tax_type: 'pis',
        rate_percent: 1.65,
        valid_from: '2026-01-01',
        valid_to: null,
        scope: { ncm: ['19059090'] },
        is_active: true,
        created_at: '2026-07-28T10:00:00Z',
      },
    ]

    await mockAccountingShell(page, { links })

    await page.route('**/api/v1/accounting/access-requests*', async (route) => {
      const method = route.request().method()

      if (method === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, message: 'OK', data: links, meta: {} }),
        })
        return
      }

      if (method === 'POST') {
        links = [approvedLink, pendingLink]
        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, message: 'OK', data: pendingLink, meta: {} }),
        })
        return
      }

      await route.fallback()
    })

    await page.route('**/api/v1/accounting/tenants/tenant-qa-1/reports/sales*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            from: '2025-08-01',
            to: '2026-07-31',
            total_orders: 12,
            total_revenue: '3240.80',
            items: [{ order_uuid: 'order-1', client_name: 'Cliente Centro', created_at: '2026-07-20', total_amount: '240.80', is_paid: true, is_delivered: true }],
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/accounting/tenants/tenant-qa-1/reports/cash-flow*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            from: '2025-08-01',
            to: '2026-07-31',
            total_in: '2710.20',
            entries: [{ date: '2026-07-20', order_uuid: 'order-1', client_name: 'Cliente Centro', amount: '240.80' }],
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/accounting/tenants/tenant-qa-1/reports/dre*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            from: '2025-08-01',
            to: '2026-07-31',
            revenue: '3240.80',
            product_cost: '1180.10',
            gross_profit: '2060.70',
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/accounting/tenants/tenant-qa-1/products**', async (route) => {
      const method = route.request().method()

      if (method === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            message: 'OK',
            data: products,
            meta: { pagination: { current_page: 1, per_page: 20, total: products.length, last_page: 1 } },
          }),
        })
        return
      }

      if (method === 'PUT') {
        products = products.map((item) =>
          item.uuid === 'product-qa-1'
            ? { ...item, ncm: '19059090', default_cfop: '5102', origin: '0', csosn_cst: '102' }
            : item,
        )
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, message: 'OK', data: products[0], meta: {} }),
        })
        return
      }

      await route.fallback()
    })

    await page.route('**/api/v1/accounting/tenants/tenant-qa-1/clients**', async (route) => {
      const method = route.request().method()

      if (method === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            message: 'OK',
            data: clients,
            meta: { pagination: { current_page: 1, per_page: 20, total: clients.length, last_page: 1 } },
          }),
        })
        return
      }

      if (method === 'PUT') {
        clients = clients.map((item) =>
          item.uuid === 'client-qa-1'
            ? { ...item, cpf_cnpj: '12345678901', ie: '123456789', ie_indicator: 'contribuinte' }
            : item,
        )
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, message: 'OK', data: clients[0], meta: {} }),
        })
        return
      }

      await route.fallback()
    })

    await page.route('**/api/v1/accounting/tenants/tenant-qa-1/tax-rules*', async (route) => {
      const method = route.request().method()

      if (method === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, message: 'OK', data: taxRules, meta: {} }),
        })
        return
      }

      if (method === 'POST') {
        taxRules = [
          {
            uuid: 'tax-rule-qa-2',
            tax_type: 'cofins',
            rate_percent: 7.6,
            valid_from: '2026-07-01',
            valid_to: null,
            scope: { cfop: ['6102'] },
            is_active: true,
            created_at: '2026-07-28T12:00:00Z',
          },
          ...taxRules,
        ]
        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, message: 'OK', data: taxRules[0], meta: {} }),
        })
        return
      }

      await route.fallback()
    })

    await page.route('**/api/v1/accounting/tenants/tenant-qa-1/messages*', async (route) => {
      const method = route.request().method()

      if (method === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, message: 'OK', data: sentMessages, meta: {} }),
        })
        return
      }

      if (method === 'POST') {
        sentMessages = [
          ...sentMessages,
          {
            uuid: 'accounting-message-2',
            sender_type: 'accounting_office',
            body: 'Tudo certo. Vou ajustar ainda hoje.',
            due_date: null,
            status: 'answered',
            attachment_name: null,
            attachment_url: null,
            created_at: '2026-07-28T15:00:00Z',
          },
        ]
        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, message: 'OK', data: sentMessages[sentMessages.length - 1], meta: {} }),
        })
        return
      }

      await route.fallback()
    })

    await page.goto('/contador/solicitar-acesso')

    await expect(page.getByLabel('CNPJ da empresa')).toBeVisible()
    await page.getByLabel('CNPJ da empresa').fill('22.333.444/0001-55')
    await page.getByRole('button', { name: 'Solicitar' }).click()
    await expect(page.getByText('Solicitação enviada. A empresa precisa aprovar antes de você ver os dados.')).toBeVisible()
    await expect(page.getByText('Mercado Bairro')).toBeVisible()

    await page.goto('/contador/empresas')

    await expect(page.getByText('Empresas').last()).toBeVisible()
    await expect(page.getByText('Padaria Centro')).toBeVisible()
    await page.getByText('Padaria Centro').click()

    await expect(page.getByText('Padaria Centro').last()).toBeVisible()
    await expect(page.getByRole('tab', { name: 'Relatórios' })).toBeVisible()
    await expect(page.getByText('Vendas')).toBeVisible()
    await expect(page.getByText('R$ 3.240,80').first()).toBeVisible()
    await expect(page.getByText('DRE simplificado')).toBeVisible()

    await page.getByRole('tab', { name: 'Produtos fiscais' }).click()
    await expect(page.getByText('Bolo de milho')).toBeVisible()
    await page.getByRole('button', { name: /Editar dados fiscais/ }).click()
    await expect(page.getByRole('dialog', { name: 'Dados fiscais do produto' })).toBeVisible()
    await page.getByLabel('NCM').fill('19059090')
    await page.getByLabel('CFOP padrão').fill('5102')
    await page.getByLabel('CSOSN / CST').fill('102')
    await page.getByRole('button', { name: 'Salvar' }).click()
    await expect(page.getByText('CFOP: 5102')).toBeVisible()

    await page.getByRole('tab', { name: 'Clientes fiscais' }).click()
    await expect(page.getByText('Cliente Centro')).toBeVisible()
    await page.getByRole('button', { name: /Editar dados fiscais/ }).click()
    await expect(page.getByRole('dialog', { name: 'Dados fiscais do cliente' })).toBeVisible()
    await page.getByLabel('CPF/CNPJ').fill('123.456.789-01')
    await page.getByLabel('Inscrição estadual').fill('123456789')
    await page.getByLabel('Indicador da IE').click()
    await page.getByRole('option', { name: 'Contribuinte', exact: true }).click()
    await page.getByRole('button', { name: 'Salvar' }).click()
    await expect(page.getByText('123.456.789-01')).toBeVisible()

    await page.getByRole('tab', { name: 'Regras tributárias' }).click()
    await expect(page.getByText('Nenhuma regra tributária cadastrada ainda')).toHaveCount(0)
    await expect(page.getByText('PIS')).toBeVisible()
    await page.getByRole('button', { name: 'Nova regra' }).click()
    await expect(page.getByRole('dialog', { name: 'Nova regra tributária' })).toBeVisible()
    await page.getByLabel('Tributo').click()
    await page.getByRole('option', { name: 'COFINS' }).click()
    await page.getByLabel('Alíquota (%)').fill('7.6')
    await page.getByLabel('CFOPs').fill('6102')
    await page.getByRole('button', { name: 'Salvar' }).click()
    await expect(page.getByText('COFINS')).toBeVisible()

    await page.getByRole('tab', { name: 'Pendências' }).click()
    await expect(page.getByText('Precisamos revisar o cadastro fiscal dos clientes premium.')).toBeVisible()
    await page.getByLabel('Nova mensagem').fill('Tudo certo. Vou ajustar ainda hoje.')
    await page.getByRole('button', { name: 'Enviar' }).click()
    await expect(page.getByText('Tudo certo. Vou ajustar ainda hoje.')).toBeVisible()
  })
})
