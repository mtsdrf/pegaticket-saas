# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: storefront-sales.spec.ts >> Vendas online >> aprova ou rejeita solicitações de cancelamento com reflexo operacional na fila
- Location: e2e/storefront-sales.spec.ts:222:3

# Error details

```
Error: locator.click: Test ended.
Call log:
  - waiting for getByRole('button', { name: 'Só cancelamento pendente' })

```

# Test source

```ts
  320 |         body: JSON.stringify({
  321 |           success: true,
  322 |           message: 'OK',
  323 |           data: storefrontOrders.find((item) => item.uuid === 'storefront-cancel-approve-1'),
  324 |           meta: {},
  325 |         }),
  326 |       })
  327 |     })
  328 | 
  329 |     await page.route('**/api/v1/storefront-sales/storefront-cancel-reject-1*', async (route) => {
  330 |       if (route.request().method() !== 'GET') {
  331 |         await route.fallback()
  332 |         return
  333 |       }
  334 | 
  335 |       await route.fulfill({
  336 |         status: 200,
  337 |         contentType: 'application/json',
  338 |         body: JSON.stringify({
  339 |           success: true,
  340 |           message: 'OK',
  341 |           data: storefrontOrders.find((item) => item.uuid === 'storefront-cancel-reject-1'),
  342 |           meta: {},
  343 |         }),
  344 |       })
  345 |     })
  346 | 
  347 |     await page.route('**/api/v1/storefront-sales*', async (route) => {
  348 |       const url = route.request().url()
  349 |       const method = route.request().method()
  350 | 
  351 |       if (method === 'GET' && /\/api\/v1\/storefront-sales(\?.*)?$/.test(url)) {
  352 |         const query = new URL(url).searchParams
  353 |         const filteredOrders = query.get('status') === 'cancellation_requested'
  354 |           ? storefrontOrders.filter((order) => order.status === 'cancellation_requested')
  355 |           : storefrontOrders
  356 | 
  357 |         await route.fulfill({
  358 |           status: 200,
  359 |           contentType: 'application/json',
  360 |           body: JSON.stringify({
  361 |             success: true,
  362 |             message: 'OK',
  363 |             data: filteredOrders,
  364 |             meta: {
  365 |               pagination: {
  366 |                 current_page: 1,
  367 |                 per_page: 50,
  368 |                 total: filteredOrders.length,
  369 |                 last_page: 1,
  370 |               },
  371 |             },
  372 |           }),
  373 |         })
  374 |         return
  375 |       }
  376 | 
  377 |       await route.fallback()
  378 |     })
  379 | 
  380 |     await page.goto('/vendas-online')
  381 | 
  382 |     await expect(page.getByRole('gridcell', { name: 'Cliente Aprovar Cancelamento', exact: true })).toBeVisible()
  383 |     await expect(page.getByRole('gridcell', { name: 'Cliente Rejeitar Cancelamento', exact: true })).toBeVisible()
  384 | 
  385 |     await page.getByRole('button', { name: /Gerenciar venda do cliente Cliente Aprovar Cancelamento/ }).click()
  386 |     await expect(page.getByText('O cliente solicitou o cancelamento desta venda: "Cliente desistiu da compra"')).toBeVisible()
  387 |     await page.getByRole('button', { name: 'Aprovar cancelamento' }).click()
  388 |     await expect(page.getByText('Ao aprovar, a venda é cancelada de verdade agora')).toBeVisible()
  389 |     const approveResponse = page.waitForResponse(
  390 |       (response) =>
  391 |         response.url().includes('/sales/storefront-cancel-approve-1/approve-cancellation')
  392 |         && response.request().method() === 'POST',
  393 |     )
  394 |     await page.getByRole('button', { name: 'Confirmar' }).click()
  395 |     await approveResponse
  396 |     await page.getByRole('button', { name: 'Fechar' }).click()
  397 | 
  398 |     await expect(page.getByRole('gridcell', { name: 'Cliente Aprovar Cancelamento', exact: true })).toHaveCount(0)
  399 |     await expect(page.locator('div[draggable="true"]').filter({ hasText: '2201' })).toHaveCount(0)
  400 | 
  401 |     await page.getByRole('button', { name: /Gerenciar venda do cliente Cliente Rejeitar Cancelamento/ }).click()
  402 |     await expect(page.getByText('O cliente solicitou o cancelamento desta venda: "Vai buscar mais tarde"')).toBeVisible()
  403 |     const rejectResponse = page.waitForResponse(
  404 |       (response) =>
  405 |         response.url().includes('/sales/storefront-cancel-reject-1/reject-cancellation')
  406 |         && response.request().method() === 'POST',
  407 |     )
  408 |     await page.getByRole('button', { name: 'Rejeitar cancelamento' }).click()
  409 |     await page.getByRole('button', { name: 'Confirmar' }).click()
  410 |     await rejectResponse
  411 | 
  412 |     await expect(page.getByText('Cancelamento solicitado', { exact: true })).toHaveCount(0)
  413 |     await expect(page.getByRole('button', { name: 'Cancelar venda' })).toBeVisible()
  414 |     await page.mouse.click(10, 10)
  415 |     await expect(page.getByRole('dialog')).toHaveCount(0)
  416 | 
  417 |     await expect(page.getByRole('gridcell', { name: 'Cliente Rejeitar Cancelamento', exact: true })).toBeVisible()
  418 |     await expect(page.getByRole('gridcell', { name: 'Cancelamento solicitado', exact: true })).toHaveCount(0)
  419 | 
> 420 |     await page.getByRole('button', { name: 'Só cancelamento pendente' }).click()
      |                                                                          ^ Error: locator.click: Test ended.
  421 |     await expect(page.getByText('Nenhum cancelamento pendente no momento')).toBeVisible()
  422 |   })
  423 | })
  424 | 
```