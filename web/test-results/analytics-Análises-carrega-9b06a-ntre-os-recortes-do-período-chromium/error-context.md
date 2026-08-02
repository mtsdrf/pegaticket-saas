# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: analytics.spec.ts >> Análises >> carrega as abas principais com dados reais de analytics e navega entre os recortes do período
- Location: e2e/analytics.spec.ts:5:3

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByText('Pedidos em atraso')
Expected: visible
Timeout: 10000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" with timeout 10000ms
  - waiting for getByText('Pedidos em atraso')

```

```yaml
- banner:
  - img "PegaTicket"
  - text: PegaTicket
  - button "Buscar telas e páginas"
  - button "Ver novidades da plataforma"
  - button "Abrir menu da conta": UQ
- list:
  - link "Visão geral":
    - /url: /
  - button "Relatórios" [expanded]
  - link "Análises":
    - /url: /analises
  - link "Resultado por canal":
    - /url: /relatorios/canais
  - link "Relatório de vendas":
    - /url: /relatorios/vendas
  - link "Configurações":
    - /url: /configuracoes
- main:
  - heading "Análises" [level=1]
  - paragraph: Explore vendas, produtos, locais, clientes e atrasos da operação.
  - text: Período
  - combobox "Período": Últimos 12 meses
  - text: De
  - textbox "De": 2025-09-01
  - text: Até
  - textbox "Até": 2026-08-02
  - tablist "Abas de análise":
    - tab "Financeiro"
    - tab "Produtos"
    - tab "Locais"
    - tab "Sazonalidade"
    - tab "Clientes"
    - tab "Atrasos" [selected]
  - tabpanel "Atrasos":
    - paragraph: Vendas em atraso
    - paragraph: Pagamentos vencidos e entregas atrasadas no período — uma venda atrasada nos dois aparece uma vez por tipo.
    - table:
      - rowgroup:
        - row "Cliente Tipo Valor em aberto Dias em atraso":
          - columnheader "Cliente"
          - columnheader "Tipo"
          - columnheader "Valor em aberto"
          - columnheader "Dias em atraso"
      - rowgroup:
        - row "Cliente Horizonte Pagamento R$ 210,40 12 dias":
          - cell "Cliente Horizonte"
          - cell "Pagamento"
          - cell "R$ 210,40"
          - cell "12 dias"
        - row "Cliente Aurora Entrega R$ 98,00 33 dias":
          - cell "Cliente Aurora"
          - cell "Entrega"
          - cell "R$ 98,00"
          - cell "33 dias"
    - navigation "pagination navigation":
      - list:
        - listitem:
          - button "Go to previous page" [disabled]
        - listitem:
          - button "page 1": "1"
        - listitem:
          - button "Go to page 2": "2"
        - listitem:
          - button "Go to next page"
```

# Test source

```ts
  260 |           meta: {},
  261 |         }),
  262 |       })
  263 |     })
  264 | 
  265 |     await page.route('**/api/v1/reports/analytics/payment-delays*', async (route) => {
  266 |       await route.fulfill({
  267 |         status: 200,
  268 |         contentType: 'application/json',
  269 |         body: JSON.stringify({
  270 |           success: true,
  271 |           message: 'OK',
  272 |           data: [
  273 |             { client_name: 'Cliente Horizonte', avg_days_to_pay: 2, paid_sales_count: 5 },
  274 |             { client_name: 'Cliente Aurora', avg_days_to_pay: 7, paid_sales_count: 3 },
  275 |           ],
  276 |           meta: {},
  277 |         }),
  278 |       })
  279 |     })
  280 | 
  281 |     await page.route('**/api/v1/reports/analytics/overdue-sales*', async (route) => {
  282 |       const url = new URL(route.request().url())
  283 |       const pageNumber = Number(url.searchParams.get('page') ?? '1')
  284 |       const rows =
  285 |         pageNumber === 1
  286 |           ? [
  287 |               {
  288 |                 sale_uuid: 'order-1',
  289 |                 client_name: 'Cliente Horizonte',
  290 |                 open_amount: 210.4,
  291 |                 days_overdue: 12,
  292 |                 type: 'pagamento',
  293 |               },
  294 |               {
  295 |                 sale_uuid: 'order-2',
  296 |                 client_name: 'Cliente Aurora',
  297 |                 open_amount: 98,
  298 |                 days_overdue: 33,
  299 |                 type: 'entrega',
  300 |               },
  301 |             ]
  302 |           : [
  303 |               {
  304 |                 sale_uuid: 'order-3',
  305 |                 client_name: 'Cliente Horizonte Sul',
  306 |                 open_amount: 55,
  307 |                 days_overdue: 4,
  308 |                 type: 'pagamento',
  309 |               },
  310 |             ]
  311 | 
  312 |       await route.fulfill({
  313 |         status: 200,
  314 |         contentType: 'application/json',
  315 |         body: JSON.stringify({
  316 |           success: true,
  317 |           message: 'OK',
  318 |           data: rows,
  319 |           meta: {
  320 |             pagination: {
  321 |               current_page: pageNumber,
  322 |               per_page: 15,
  323 |               total: 3,
  324 |               last_page: 2,
  325 |             },
  326 |           },
  327 |         }),
  328 |       })
  329 |     })
  330 | 
  331 |     await page.goto('/analises')
  332 | 
  333 |     await expect(page.getByRole('heading', { name: 'Análises' })).toBeVisible()
  334 |     await expect(page.getByText('Margem bruta (aprox.)')).toBeVisible()
  335 |     await expect(page.getByText('59,99%')).toBeVisible()
  336 | 
  337 |     await page.getByRole('tab', { name: 'Produtos' }).click()
  338 |     await expect(page.getByText('Produtos mais vendidos')).toBeVisible()
  339 |     await expect(page.getByText('Pizza Calabresa').first()).toBeVisible()
  340 |     await expect(page.getByText('Curva ABC de produtos')).toBeVisible()
  341 | 
  342 |     await page.getByRole('tab', { name: 'Locais' }).click()
  343 |     await expect(page.getByText('Vendas por cidade')).toBeVisible()
  344 |     await expect(page.getByText('São Paulo')).toBeVisible()
  345 |     await expect(page.getByText('Vendas por bairro')).toBeVisible()
  346 |     await expect(page.getByText('Mooca')).toBeVisible()
  347 | 
  348 |     await page.getByRole('tab', { name: 'Sazonalidade' }).click()
  349 |     await expect(page.getByText('Movimento por dia e hora')).toBeVisible()
  350 |     await expect(page.getByText('Sazonalidade').last()).toBeVisible()
  351 |     await expect(page.getByText('2025')).toBeVisible()
  352 |     await expect(page.getByText('2026')).toBeVisible()
  353 | 
  354 |     await page.getByRole('tab', { name: 'Clientes' }).click()
  355 |     await expect(page.getByText('Melhores clientes')).toBeVisible()
  356 |     await expect(page.getByText('Cliente Horizonte').first()).toBeVisible()
  357 |     await expect(page.getByText('Atrasos de pagamento')).toBeVisible()
  358 | 
  359 |     await page.getByRole('tab', { name: 'Atrasos' }).click()
> 360 |     await expect(page.getByText('Pedidos em atraso')).toBeVisible()
      |                                                       ^ Error: expect(locator).toBeVisible() failed
  361 |     await expect(page.getByRole('cell', { name: 'Cliente Aurora' })).toBeVisible()
  362 |     await expect(page.getByText('33 dias')).toBeVisible()
  363 | 
  364 |     await page.getByRole('button', { name: 'Go to page 2' }).click()
  365 |     await expect(page.getByRole('cell', { name: 'Cliente Horizonte Sul' })).toBeVisible()
  366 |     await expect(page.getByText('4 dias')).toBeVisible()
  367 |   })
  368 | })
  369 | 
```