import { expect, test } from '@playwright/test'
import { readPagBankSandboxCards } from './support/pagbankCards'

const [visaCard] = readPagBankSandboxCards()

const slug = 'loja-pagamentos'
const saleUuid = 'sale-card-flow-1'
const holdUuid = 'hold-card-flow-1'
const portalToken = 'portal-token-playwright'

/** Datas relativas ao momento do teste — evita que fixtures com timestamp fixo expirem sozinhas quando o dia virar. */
function minutesFromNow(minutes: number): string {
  return new Date(Date.now() + minutes * 60_000).toISOString()
}

function mockPagSeguroSdkSource() {
  return `
    (() => {
      window.PagSeguro = {
        setUp() {},
        encryptCard(payload) {
          const encryptedByNumber = {
            '${visaCard.number}': '${visaCard.encrypted}',
          }
          return {
            hasErrors: false,
            encryptedCard: encryptedByNumber[payload.number] ?? 'ENCRYPTED_FALLBACK',
            errors: [],
          }
        },
        authenticate3DS() {
          return Promise.resolve({ status: 'AUTH_FLOW_COMPLETED', id: '3DS_AUTH_BROWSER_1' })
        },
      }
    })();
  `
}

function checkoutCartItem() {
  return {
    id: 'cart-item-1',
    ticket_type_uuid: 'ticket-type-1',
    event_product_uuid: undefined,
    name: 'Ingresso pista',
    event_name: 'Festival QA',
    event_slug: 'festival-qa',
    session_uuid: null,
    session_name: null,
    seat_uuid: null,
    seat_label: null,
    seat_sector_name: null,
    seat_kind: null,
    seat_capacity: null,
    unit_price: 120,
    image_url: null,
    quantity: 1,
    notes: null,
  }
}

async function mockAuthenticatedStorefrontCheckout(page: Parameters<typeof test>[0]['page']) {
  await page.addInitScript(
    ({ token, storeSlug, cart }) => {
      localStorage.setItem('pegaticket.portal_access_token', token)
      localStorage.setItem(`pegaticket.storefront_cart.${storeSlug}`, JSON.stringify([cart]))
      localStorage.setItem(`mk_age_verified_${storeSlug}`, 'true')
    },
    {
      token: portalToken,
      storeSlug: slug,
      cart: checkoutCartItem(),
    },
  )

  await page.route('**/assets.pagseguro.com.br/checkout-sdk-js/rc/dist/browser/pagseguro.min.js', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/javascript',
      body: mockPagSeguroSdkSource(),
    })
  })

  await page.route('**/api/v1/portal/me', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        message: 'OK',
        data: {
          uuid: 'portal-customer-1',
          name: 'Maria',
          email: 'mreisf.contato@gmail.com',
          linked_tenants: [],
        },
        meta: {},
      }),
    })
  })

  await page.route(`**/api/v1/loja/${slug}`, async (route) => {
    if (route.request().method() !== 'GET') {
      await route.fallback()
      return
    }

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        message: 'OK',
        data: {
          slug,
          name: 'Loja Pagamentos',
          logo_url: null,
          average_rating: null,
          ratings_count: 0,
          email: 'contato@loja.com',
          phone: '1133334444',
          mobile_phone: '11999998888',
          whatsapp: '11999998888',
          instagram: '@lojapagamentos',
          facebook: null,
          address: 'Rua Principal, 100',
          address_lat: null,
          address_lng: null,
          accepted_payment_methods: ['credit_card', 'debit_card', 'pix'],
          storefront_enabled: true,
          catalog_layout: 'grid',
        },
        meta: {},
      }),
    })
  })

  await page.route(`**/api/v1/loja/${slug}/eventos/festival-qa/holds`, async (route) => {
    if (route.request().method() !== 'POST') {
      await route.fallback()
      return
    }

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        message: 'OK',
        data: {
          uuid: holdUuid,
          status: 'reservado',
          expires_at: minutesFromNow(30),
          remaining_seconds: 600,
        },
        meta: {},
      }),
    })
  })

  await page.route(`**/api/v1/loja/${slug}/holds/${holdUuid}/renovar`, async (route) => {
    if (route.request().method() !== 'POST') {
      await route.fallback()
      return
    }

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        message: 'OK',
        data: {
          uuid: holdUuid,
          status: 'reservado',
          expires_at: minutesFromNow(30),
          remaining_seconds: 600,
        },
        meta: {},
      }),
    })
  })

  await page.route(`**/api/v1/rastreio/${saleUuid}`, async (route) => {
    if (route.request().method() !== 'GET') {
      await route.fallback()
      return
    }

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        message: 'OK',
        data: {
          uuid: saleUuid,
          code: '1001',
          status: 'confirmed',
          is_paid: true,
          total_amount: '120.00',
          paid_at: '2026-08-03T12:00:00-03:00',
          created_at: '2026-08-03T12:00:00-03:00',
          customer_name: 'Maria Reis',
          items: [],
        },
        meta: {},
      }),
    })
  })
}

async function fillCheckoutDetails(page: Parameters<typeof test>[0]['page'], paymentMethodLabel: string) {
  const ageConfirmationButton = page.getByRole('button', { name: 'Sim, tenho 18 anos ou mais' })
  if (await ageConfirmationButton.isVisible().catch(() => false)) {
    await ageConfirmationButton.click()
  }

  await page.getByLabel('Seu nome').fill('Maria')
  await page.getByLabel('Sobrenome').fill('Reis')
  await page.getByLabel('Telefone (com DDD)').fill('11999999999')
  await page.getByLabel('Como você pretende pagar').click()
  await page.getByRole('option', { name: paymentMethodLabel }).click()
}

test.describe('Checkout público com cartão', () => {
  test('envia a cobrança de crédito com o cartão sandbox do PagBank', async ({ page }) => {
    await mockAuthenticatedStorefrontCheckout(page)

    let checkoutPayload: Record<string, unknown> | null = null
    let paymentPayload: Record<string, unknown> | null = null

    await page.route(`**/api/v1/loja/${slug}/checkout`, async (route) => {
      checkoutPayload = route.request().postDataJSON() as Record<string, unknown>
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'Compra criada',
          data: {
            sale: {
              uuid: saleUuid,
            },
          },
          meta: {},
        }),
      })
    })

    await page.route(`**/api/v1/portal/sales/${saleUuid}/payment-checkout-config`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            provider: 'pagbank',
            available: true,
            environment: 'SANDBOX',
            public_key: 'PAGBANK_PUBLIC_KEY_SANDBOX',
            three_ds_session: 'PAGBANK_3DS_SESSION_SANDBOX',
            three_ds_session_expires_at: minutesFromNow(30),
            sdk_script_url: 'https://assets.pagseguro.com.br/checkout-sdk-js/rc/dist/browser/pagseguro.min.js',
          },
          meta: {},
        }),
      })
    })

    await page.route(`**/api/v1/portal/sales/${saleUuid}/payment-charge`, async (route) => {
      paymentPayload = route.request().postDataJSON() as Record<string, unknown>
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            uuid: 'payment-1',
            provider: 'pagbank',
            provider_charge_id: 'ORDE_CREDIT_1',
            method: 'credit_card',
            amount: '120.00',
            status: 'paid',
            paid_at: '2026-08-03T12:05:00-03:00',
            created_at: '2026-08-03T12:04:00-03:00',
            metadata: null,
          },
          meta: {},
        }),
      })
    })

    await page.goto(`/eventos/${slug}/checkout`)
    await fillCheckoutDetails(page, 'Cartão de crédito')
    await page.getByRole('button', { name: 'Confirmar compra' }).click()

    await expect(page.getByText('Pagar com cartão de crédito')).toBeVisible()

    await page.getByLabel('CPF/CNPJ do pagador').fill('12345678909')
    await page.getByLabel('Nome do titular do cartão').fill('Maria Reis')
    await page.getByLabel('CPF/CNPJ do titular').fill('12345678909')
    await page.getByLabel('Número do cartão').fill(visaCard.number)
    await page.getByLabel('Mês').fill('12')
    await page.getByLabel('Ano').fill('2030')
    await page.getByLabel('CVV').fill(visaCard.cvv)
    await page.getByLabel('Parcelas').click()
    await page.getByRole('option', { name: '1x', exact: true }).click()
    await page.getByRole('button', { name: 'Pagar com cartão' }).click()

    await expect(page).toHaveURL(new RegExp(`/rastreio/${saleUuid}$`))

    expect(checkoutPayload).toMatchObject({
      client_name: 'Maria',
      client_last_name: 'Reis',
      client_phone: '11999999999',
      payment_method: 'credit_card',
      hold_uuid: holdUuid,
    })

    expect(paymentPayload).toMatchObject({
      method: 'credit_card',
      payer_tax_id: '12345678909',
      payer_name: 'Maria Reis',
      payer_email: 'mreisf.contato@gmail.com',
      payer_phone: '11999999999',
      card: {
        encrypted: visaCard.encrypted,
        holder_name: 'Maria Reis',
        holder_tax_id: '12345678909',
        installments: 1,
      },
    })
  })

  test('envia a cobrança de débito com 3DS concluído pelo SDK do PagBank', async ({ page }) => {
    await mockAuthenticatedStorefrontCheckout(page)

    let paymentPayload: Record<string, unknown> | null = null

    await page.route(`**/api/v1/loja/${slug}/checkout`, async (route) => {
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'Compra criada',
          data: {
            sale: {
              uuid: saleUuid,
            },
          },
          meta: {},
        }),
      })
    })

    await page.route(`**/api/v1/portal/sales/${saleUuid}/payment-checkout-config`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            provider: 'pagbank',
            available: true,
            environment: 'SANDBOX',
            public_key: 'PAGBANK_PUBLIC_KEY_SANDBOX',
            three_ds_session: 'PAGBANK_3DS_SESSION_SANDBOX',
            three_ds_session_expires_at: minutesFromNow(30),
            sdk_script_url: 'https://assets.pagseguro.com.br/checkout-sdk-js/rc/dist/browser/pagseguro.min.js',
          },
          meta: {},
        }),
      })
    })

    await page.route(`**/api/v1/portal/sales/${saleUuid}/payment-charge`, async (route) => {
      paymentPayload = route.request().postDataJSON() as Record<string, unknown>
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            uuid: 'payment-2',
            provider: 'pagbank',
            provider_charge_id: 'ORDE_DEBIT_1',
            method: 'debit_card',
            amount: '120.00',
            status: 'paid',
            paid_at: '2026-08-03T12:05:00-03:00',
            created_at: '2026-08-03T12:04:00-03:00',
            metadata: null,
          },
          meta: {},
        }),
      })
    })

    await page.goto(`/eventos/${slug}/checkout`)
    await fillCheckoutDetails(page, 'Cartão de débito')
    await page.getByRole('button', { name: 'Confirmar compra' }).click()

    await expect(page.getByText('Cartão de débito')).toBeVisible()

    await page.getByLabel('CPF/CNPJ do pagador').fill('12345678909')
    await page.getByLabel('Telefone do pagador').fill('11999999999')
    await page.getByLabel('Nome do titular do cartão').fill('Maria Reis')
    await page.getByLabel('CPF/CNPJ do titular').fill('12345678909')
    await page.getByLabel('Número do cartão').fill(visaCard.number)
    await page.getByLabel('Mês').fill('12')
    await page.getByLabel('Ano').fill('2030')
    await page.getByLabel('CVV').fill(visaCard.cvv)
    await page.getByLabel('Rua / avenida').fill('Rua das Palmeiras')
    await page.getByRole('textbox', { name: 'Número', exact: true }).fill('100')
    await page.getByLabel('Bairro').fill('Centro')
    await page.getByLabel('Cidade').fill('Sao Paulo')
    await page.getByLabel('UF').fill('SP')
    await page.getByLabel('CEP').fill('01001000')
    await page.getByRole('button', { name: 'Pagar com débito' }).click()

    await expect(page).toHaveURL(new RegExp(`/rastreio/${saleUuid}$`))

    expect(paymentPayload).toMatchObject({
      method: 'debit_card',
      payer_tax_id: '12345678909',
      payer_name: 'Maria Reis',
      payer_email: 'mreisf.contato@gmail.com',
      payer_phone: '11999999999',
      card: {
        encrypted: visaCard.encrypted,
        holder_name: 'Maria Reis',
        holder_tax_id: '12345678909',
      },
      authentication_method: {
        type: 'THREEDS',
        id: '3DS_AUTH_BROWSER_1',
      },
    })
  })
})
