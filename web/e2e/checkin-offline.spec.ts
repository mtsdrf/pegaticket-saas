import { expect, test } from '@playwright/test'
import { mockApiRoute, mockAuthenticatedShell, mockPaginatedApiRoute } from './support/api'

const emptySummary = {
  filters: { event_uuid: null, event_session_uuid: null, gate_name: null },
  counters: { total: 0, granted: 0, warning: 0, blocked: 0 },
  recent: [],
}

const grantedResponse = {
  result: 'valido',
  ticket: {
    uuid: 'ticket-qa-1',
    code: 'A1B2C3D4',
    status: 'usado',
  },
  checkin: {
    uuid: 'checkin-qa-1',
    gate_name: null,
    result: 'valido',
    access_type: 'entrada',
    reason: null,
    checked_in_at: new Date().toISOString(),
  },
}

test.describe('Portaria — modo offline', () => {
  test('registra check-in na fila local quando a rede falha e sincroniza ao reconectar', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['tickets:checkin'],
      tenantFunctionalities: ['tickets'],
    })

    await mockPaginatedApiRoute(page, {
      path: '/events',
      body: [],
      pagination: { current_page: 1, per_page: 100, total: 0, last_page: 1 },
    })

    await mockApiRoute(page, { path: '/tickets/checkin/summary', body: emptySummary })

    let checkinAttempts = 0
    await page.route('**/api/v1/tickets/checkin*', async (route) => {
      checkinAttempts += 1
      if (checkinAttempts === 1) {
        await route.abort('internetdisconnected')
        return
      }
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: grantedResponse, meta: {} }),
      })
    })

    await mockApiRoute(page, { path: '/tickets/ticket-qa-1/checkins', body: [] })

    await page.goto('/portaria')

    await page.getByRole('button', { name: 'Buscar manualmente' }).click()
    await page.getByLabel('Código do ingresso').fill('A1B2C3D4')
    await page.getByRole('button', { name: 'Fazer check-in' }).click()

    await expect(
      page.getByText('Sem conexão — check-in registrado offline e será sincronizado automaticamente.'),
    ).toBeVisible()
    await expect(page.getByText('1 check-in(s) pendente(s) de sincronização.')).toBeVisible()

    const queuedRaw = await page.evaluate(() => localStorage.getItem('pegaticket.offline_checkin_queue'))
    expect(queuedRaw).toBeTruthy()
    expect(JSON.parse(queuedRaw ?? '[]')).toHaveLength(1)

    await page.getByRole('button', { name: 'Sincronizar agora' }).click()

    await expect(page.getByText('1 check-in(s) pendente(s) de sincronização.')).toHaveCount(0)

    const queuedAfterSync = await page.evaluate(() => localStorage.getItem('pegaticket.offline_checkin_queue'))
    expect(JSON.parse(queuedAfterSync ?? '[]')).toHaveLength(0)
    expect(checkinAttempts).toBe(2)
  })
})
