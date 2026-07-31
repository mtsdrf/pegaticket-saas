import { expect, test } from '@playwright/test'
import { mockApiRoute, mockAuthenticatedShell, mockPaginatedApiRoute } from './support/api'

const createdClient = {
  uuid: 'client-created-1',
  name: 'Cliente QA Automação',
  phone_primary: '11999887766',
  phone_secondary: '1133334444',
  cpf_cnpj: '52998224725',
  ie: '123456789',
  ie_indicator: 'contribuinte',
  notes: 'Cliente criado pela suíte E2E',
  is_trusted: true,
  is_active: true,
  endereco: {
    uuid: 'address-client-created-1',
    logradouro: 'Rua das Flores',
    numero: '123',
    complemento: 'Sala 2',
    cep: '01001000',
    estado_uuid: 'state-sp',
    estado_name: 'São Paulo',
    cidade_uuid: 'city-sp',
    cidade_name: 'São Paulo',
    bairro_uuid: 'district-centro',
    bairro_name: 'Centro',
    lat: null,
    lng: null,
  },
  created_at: '2026-07-28T12:00:00Z',
}

async function mockClientFormDependencies(page: Parameters<typeof test>[0]['page']) {
  await mockApiRoute(page, {
    path: '/estados',
    body: [
      {
        uuid: 'state-sp',
        name: 'São Paulo',
        uf: 'SP',
        is_active: true,
        created_at: '2026-07-28T12:00:00Z',
      },
    ],
  })

  await mockApiRoute(page, {
    path: '/cidades',
    body: [
      {
        uuid: 'city-sp',
        name: 'São Paulo',
        is_active: true,
        estado_uuid: 'state-sp',
        estado_name: 'São Paulo',
        created_at: '2026-07-28T12:00:00Z',
      },
    ],
  })

  await mockApiRoute(page, {
    path: '/bairros',
    body: [
      {
        uuid: 'district-centro',
        name: 'Centro',
        is_active: true,
        cidade_uuid: 'city-sp',
        cidade_name: 'São Paulo',
        created_at: '2026-07-28T12:00:00Z',
      },
    ],
  })
}

test.describe('Clientes', () => {
  test('valida os campos obrigatórios antes de enviar um novo cliente', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['clients:read', 'clients:create'],
      tenantFunctionalities: ['clients'],
    })
    await mockClientFormDependencies(page)

    let postCalls = 0
    await page.route('**/api/v1/clients', async (route) => {
      if (route.request().method() !== 'POST') {
        await route.fallback()
        return
      }

      postCalls += 1
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: createdClient,
          meta: {},
        }),
      })
    })

    await page.goto('/clientes/novo')

    await expect(page.getByRole('heading', { name: 'Novo cliente' })).toBeVisible()

    await page.getByRole('button', { name: 'Salvar' }).click()

    await expect(page.getByText('Informe pelo menos 2 caracteres.')).toBeVisible()
    await expect(page.getByText('Informe o CPF ou CNPJ do cliente.')).toBeVisible()
    await expect(page.getByText('Campo obrigatório.')).toHaveCount(4)
    await expect.poll(() => postCalls).toBe(0)
  })

  test('cria um novo cliente e retorna para a listagem', async ({ page }) => {
    test.slow()

    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['clients:read', 'clients:create'],
      tenantFunctionalities: ['clients'],
    })
    await mockClientFormDependencies(page)

    await mockPaginatedApiRoute(page, {
      path: '/clients',
      body: [createdClient],
      pagination: {
        current_page: 1,
        per_page: 20,
        total: 1,
        last_page: 1,
      },
    })

    let submittedPayload: Record<string, unknown> | null = null
    await page.route('**/api/v1/clients', async (route) => {
      if (route.request().method() === 'GET') {
        await route.fallback()
        return
      }
      if (route.request().method() !== 'POST') {
        await route.fallback()
        return
      }

      submittedPayload = route.request().postDataJSON() as Record<string, unknown>
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'Cliente criado com sucesso.',
          data: createdClient,
          meta: {},
        }),
      })
    })

    await page.goto('/clientes/novo')

    await page.getByLabel('Nome').fill('Cliente QA Automação')
    await page.getByLabel('Telefone principal').fill('11999887766')
    await page.getByLabel('Telefone secundário').fill('1133334444')
    await page.getByLabel('CPF/CNPJ').fill('52998224725')
    await page.getByRole('textbox', { name: 'Inscrição estadual', exact: true }).fill('123456789')
    await page.getByLabel('Observação').fill('Cliente criado pela suíte E2E')

    await page.getByRole('combobox', { name: 'Estado' }).click()
    await page.getByRole('option', { name: 'São Paulo (SP)' }).click()

    await page.getByRole('combobox', { name: 'Cidade' }).click()
    await page.getByRole('option', { name: 'São Paulo' }).click()

    await page.getByRole('combobox', { name: 'Bairro' }).click()
    await page.getByRole('option', { name: 'Centro' }).click()

    await page.getByLabel('Logradouro').fill('Rua das Flores')
    await page.getByLabel('Número').fill('123')
    await page.getByLabel('CEP').fill('01001000')
    await page.getByLabel('Complemento').fill('Sala 2')

    await page.getByRole('button', { name: 'Salvar' }).click()

    await expect.poll(() => (submittedPayload ? 'submitted' : 'pending')).toBe('submitted')
    await expect(page).toHaveURL(/\/clientes$/)
    await expect(page.getByRole('heading', { name: 'Clientes' })).toBeVisible()
    await expect(page.getByRole('gridcell', { name: 'Cliente QA Automação', exact: true })).toBeVisible()
    await expect(page.getByRole('gridcell', { name: 'São Paulo', exact: true })).toBeVisible()

    expect(submittedPayload).toEqual({
      name: 'Cliente QA Automação',
      phone_primary: '11999887766',
      phone_secondary: '1133334444',
      cpf_cnpj: '52998224725',
      ie: '123456789',
      ie_indicator: 'nao_contribuinte',
      notes: 'Cliente criado pela suíte E2E',
      is_trusted: true,
      is_active: true,
      logradouro: 'Rua das Flores',
      numero: '123',
      complemento: 'Sala 2',
      cep: '01001000',
      estado_uuid: 'state-sp',
      cidade_uuid: 'city-sp',
      bairro_uuid: 'district-centro',
    })
  })
})
