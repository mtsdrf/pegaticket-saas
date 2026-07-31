# PegaTicket — Roadmap: Módulo PDV completo + Arquitetura por URL (path-based, um único domínio)

> Documento de arquitetura e planejamento. **Não é implementação.** Levantado em 2026-07-21, **atualizado em 2026-07-22** após decisão do dono: em vez de subdomínios (`pdv.pegaticket.com`, `balcao.pegaticket.com`...), os módulos vivem por **caminho de URL sob o mesmo domínio**: `sistema.pegaticket.com/pdv`, `/loja`, `/balcao`, `/contador`. PDV e a organização por URL estão no mesmo documento porque **uma decisão afeta a outra**.
>
> Restrição-guia: **orçamento baixo**, uma pessoa desenvolvendo por vez, hospedagem compartilhada (Hostinger), um único pipeline de CI. Pontos que dependem de teste real de hardware/navegador estão marcados **[requer validação técnica]**.

---

## Sumário executivo

**Parte A (PDV):** o PegaTicket já tem quase tudo o que um PDV precisa — produtos com `barcode`/`sku`/`unit`, estoque, clientes, e o `PaymentProviderInterface` (com `createPixChargeForOrder`) já scaffoldado. O que **falta** e não existe hoje é: (1) **caixa** — abertura/fechamento, sangria, suprimento, conferência (conceito zero no sistema atual); (2) **múltiplas formas de pagamento no mesmo fechamento** (hoje `orders.is_paid`/`paid_amount` é escalar — um pagamento só); (3) UX de venda rápida (busca por barcode, atalhos de teclado); (4) integração com hardware (leitor, balança, impressora térmica) via APIs nativas do navegador. PDV é **por natureza um ponto físico único**, então o offline dele é **muito mais simples** que o do balcão (um caixa, não N garçons móveis).

**Parte B (arquitetura por URL):** decisão do dono (2026-07-22): `sistema.pegaticket.com/pdv`, `/loja`, `/balcao`, `/contador` — **um único domínio, caminhos separados**, não subdomínios. Isso muda a recomendação de arquitetura pra melhor: **elimina de vez o problema de autenticação entre origens** que dominava a versão anterior deste documento. Mesmo domínio = mesma origem = `localStorage` já é compartilhado nativamente pelo browser, sem precisar migrar nada de token/cookie.

Recomendação revisada, mais simples que a anterior: **não criar monorepo de apps Vite separados agora.** Cada módulo (`/pdv`, `/balcao`) entra como uma **nova árvore de rotas isolada dentro do `web/` atual** — exatamente o padrão já usado e validado nesta mesma sessão para `/portal/*` (cliente final) e `/contador/*` (contador): pasta própria em `web/src/pages/{Modulo}/`, layout/contexto próprio quando fizer sentido, lazy-loaded via `React.lazy` (o projeto já faz isso, o build já gera chunks separados por vendor/rota), tudo dentro do **mesmo** `npm run build` e do **mesmo** deploy que já existe hoje. Zero infraestrutura nova.

**Recomendação de ordem:** **PDV antes do Balcão** (menor risco, reaproveita mais, valida a infra de caixa/pagamento que o Balcão vai herdar) — isso não mudou.

---

# Parte A — PDV (Ponto de Venda) completo

## A.1 Estado atual verificado (o que já serve)

- `products.barcode`, `products.sku`, `products.unit` **existem** (migration `2026_07_09_100000_add_stock_fields_to_products_table.php`). Busca por código de barras é uma query — não precisa de estrutura nova, só endpoint/índice.
- `Order` (`origin='staff'`, `status='confirmed'`) já é exatamente o "vendeu e levou" — PDV materializa um `Order` normal, sem ciclo de comanda. **PDV é mais simples que o Balcão** por isso.
- `PaymentProviderInterface::createPixChargeForOrder(Order)` já existe (hoje `ManualPaymentProvider`, no-op). Pix do PDV reusa isso.
- Estoque baixa como já baixa em `Order`. Cliente é opcional (venda ao consumidor sem cadastro).

## A.2 O que falta (não existe hoje)

### A.2.1 Caixa (abertura/fechamento/sangria/suprimento) — conceito NOVO
Nada disso existe no sistema. Modelar (proposta, `BaseModel` + `tenant`-scoped + auditado):

- `cash_registers` — o caixa físico/lógico: `tenant_id`, `name`, `stock_location_id`.
- `cash_sessions` — a **sessão** aberta por um operador: `cash_register_id`, `opened_by`, `opened_at`, `opening_amount` (fundo de troco), `closed_by`, `closed_at`, `closing_amount_declared`, `closing_amount_expected`, `difference`, `status` (`open`|`closed`).
- `cash_movements` — cada movimento não-venda: `cash_session_id`, `type` (`supply`=suprimento/`withdrawal`=sangria), `amount`, `reason`, `created_by`. **Sangria** (retirar dinheiro do caixa) e **suprimento** (colocar troco) são só dois tipos de movimento.
- **Conferência de caixa** = no fechamento, comparar `opening_amount + vendas em dinheiro + suprimentos − sangrias` (esperado) contra o valor declarado pelo operador (contado). A diferença é registrada e auditada. Toda venda do PDV amarra à `cash_session_id` aberta.

**Regra dura:** não permitir venda no PDV sem sessão de caixa aberta (ou permitir com flag, mas registrar). Fechamento de caixa gera relatório (o "Z" informal) por forma de pagamento.

### A.2.2 Múltiplas formas de pagamento num fechamento — NOVO
Hoje `orders.is_paid`/`paid_amount`/`paid_at` representa **um** pagamento. Dividir "R$50 Pix + R$30 dinheiro" exige `order_payments` (n pagamentos por `Order`, cada um com `method`, `amount_cents`, `provider_charge_id`, `status`, `idempotency_key`). **Este é exatamente o mesmo `order_payments` do Documento 1 (Balcão) — modelar uma vez, usar nos dois.** Manter `is_paid`/`paid_amount` como agregados derivados para compatibilidade com o que já lê esses campos.

### A.2.3 UX de venda rápida — NOVO (frontend)
- Busca instantânea por `barcode` (foco no campo, "bipou → adicionou"), por nome, por `sku`.
- **Atalhos de teclado**: adicionar item, alterar qty, aplicar desconto, finalizar (F-keys ou teclas configuráveis). Operação de balcão vive de teclado, não de mouse. Isso é UI (`react-19-master` + `ui-ux-master`), sem mudança de API.
- Tela desenhada para **desktop/balcão** (teclado + leitor), **exceção** ao mobile-first geral do projeto — registrar essa exceção consciente.

## A.3 Impressão de cupom (fiscal vs. não-fiscal)

- **Não-fiscal** (recibo de venda): gerar HTML/PDF e imprimir via `window.print()` numa impressora térmica configurada no SO. **Simples, sem custo, funciona hoje.** É o MVP.
- **Fiscal (NFC-e)**: depende **inteiramente** do módulo fiscal já mapeado (roadmap `2026-07-20`, Fase D2 — NFC-e com CSC, QR Code, DANFE-NFC-e, certificado A1, regras por UF). **Não** reimplementar aqui; o PDV só chama o `FiscalProvider` quando existir. **[requer validação fiscal por UF]**.

## A.4 Integração com hardware (sem SDK proprietário)

Objetivo: usar APIs nativas do navegador, sem pagar SDK. Realismo sobre suporte:

| Hardware | API nativa | Viabilidade | Observação |
|---|---|---|---|
| **Leitor de código de barras** (USB) | Nenhuma necessária | **Alta** | Leitor USB comum age como **teclado** (HID keyboard) — "bipa" e digita o código + Enter. Basta um `<input>` focado. **Não precisa de Web HID.** Caminho recomendado. |
| **Leitor via Web HID / Web Serial** | `WebHID` / `Web Serial` | Média | Só necessário para leitores em modo não-teclado. Chrome/Edge suportam; **Firefox/Safari não**. Evitar depender disso — o modo teclado cobre 95%. **[requer validação técnica]** |
| **Balança** (peso do produto) | `Web Serial API` | Média/baixa | Balanças mandam peso por serial. Chrome/Edge only, exige HTTPS + permissão do usuário por sessão. Viável **[requer validação técnica com a balança real]**; fallback = digitar o peso manualmente. |
| **Impressora térmica** | `window.print()` (via SO) ou `WebUSB` | Alta (via SO) / Média (WebUSB) | Caminho barato: impressora instalada no SO + `window.print()` com CSS de cupom. `WebUSB` (mandar ESC/POS direto) é Chrome/Edge only e mais frágil — **não recomendado no MVP**. |

**Recomendação:** leitor em **modo teclado** + impressora via **`window.print()`** cobrem o PDV real sem nenhuma API experimental. Web Serial (balança) e WebUSB só entram se um cliente específico exigir, sempre com fallback manual. **Nada de SDK pago.**

## A.5 Modo offline do PDV (mais simples que o Balcão)

PDV é **um ponto físico único** — não há "dois garçons offline na mesma mesa". A complexidade de conflito do Balcão (Documento 1, Seção 3) **não se aplica**. O offline do PDV é:
- Cache do cardápio/produtos localmente (IndexedDB/Dexie, mesma lib do Balcão) para vender sem internet.
- Fila local de vendas concluídas para sincronizar ao reconectar — **append-only, sem conflito**, porque só um dispositivo opera aquele caixa.
- **Mas** cuidado: **Pix e cartão exigem internet** (o PSP confirma online). Offline, o PDV só fecha venda em **dinheiro**; Pix/cartão ficam indisponíveis até reconectar. Comunicar isso claramente. NFC-e offline tem regime de **contingência** próprio (roadmap fiscal) — não improvisar.
- Recomendação: offline do PDV é **Fase posterior**; o MVP pode assumir internet (um balcão fixo costuma ter rede melhor que um garçom móvel).

## A.6 Roadmap PDV

Esforço: **P**/**M**/**G**/**GG**.

### FASE PDV-1 — Caixa + venda rápida (online) — **M/G**
- `cash_registers`/`cash_sessions`/`cash_movements` (abertura, sangria, suprimento, fechamento com conferência).
- `order_payments` (múltiplas formas) — **compartilhado com o Balcão**.
- Tela de venda rápida: busca por barcode/nome/sku, atalhos de teclado, leitor em modo teclado.
- Cupom não-fiscal via `window.print()`.
- Reaproveita: `Product`, estoque, `Order`, cliente opcional.

### FASE PDV-2 — Pagamento eletrônico — **M**
- Pix no PDV via `PaymentProviderInterface` (depende da Fase B do roadmap anterior + PSP real).
- Cartão (maquininha própria do tenant no MVP = registrar como forma de pagamento; integração TEF é escopo grande e **fora do MVP**).

### FASE PDV-3 — Hardware avançado + offline — **G**
- Web Serial (balança) e/ou WebUSB (impressora ESC/POS) **com fallback manual**. **[validação técnica]**
- Offline append-only (só dinheiro offline).

### FASE PDV-4 — Fiscal — **GG**
- NFC-e via módulo fiscal (roadmap anterior, Fase D2). **[validação fiscal por UF]**

---

# Parte B — Arquitetura por URL (path-based, um único domínio) — DECISÃO FINAL (2026-07-22)

Decisão do dono: **não subdomínios.** `sistema.pegaticket.com/pdv`, `/loja`, `/balcao`, `/contador` — tudo sob o **mesmo domínio**, na **mesma API única**, com o dono vendo **tudo num painel central**. Esta seção substitui a análise anterior de subdomínios (mantida no histórico do arquivo via git, não repetida aqui).

## B.1 Um único app Vite, rotas isoladas por módulo (não monorepo de apps)

**Por que isso é mais simples que a versão anterior deste documento:** o problema que mais pesava na análise de subdomínios era autenticação entre origens diferentes (`localStorage` isolado por origem). **Com path-based routing sob um único domínio, esse problema não existe** — `sistema.pegaticket.com/pdv` e `sistema.pegaticket.com/balcao` são a **mesma origem**, então o `localStorage` já é compartilhado nativamente pelo browser. Login único, sem migrar nada de auth.

**Recomendação: continuar com um único app `web/` (não criar `web/apps/*`, não introduzir npm workspaces).** Cada módulo novo entra como mais uma **árvore de rotas isolada**, exatamente o padrão **já construído e validado duas vezes nesta mesma sessão**:
- `/portal/*` (Portal do cliente final) — `PortalAuthContext`/`portalApiClient.ts`/`PortalProtectedRoute`/`PortalShell.tsx`, provider isolado só naquela subárvore de `AppRoutes.tsx`.
- `/contador/*` (módulo do contador) — mesmo padrão, `AccountingAuthContext`/`accountingApiClient.ts`/`AccountingProtectedRoute`/`AccountingShell.tsx`.

`/pdv` e `/balcao` seguem o mesmo molde estrutural (pasta própria em `web/src/pages/Pdv/`/`web/src/pages/Balcao/`, layout/shell próprio, rotas lazy-loaded via `React.lazy` — já é como o projeto carrega rota hoje), com uma diferença importante: **PDV e Balcão são operados por STAFF do próprio tenant** (caixa, garçom), não por uma identidade externa nova como Portal/Contador. Não precisam de um novo sistema de JWT/login separado — reaproveitam a sessão de staff já autenticada (`apiClient.ts`, `AuthContext.tsx`), possivelmente com uma **camada de login rápido por PIN/operador** por cima da sessão de staff já aberta no dispositivo fixo do caixa/tablet do garçom (detalhar na Fase 1 de cada módulo — é UX, não arquitetura de auth nova).

`/loja` (hoje `Storefront`, já em `/loja/:slug`) **não muda de lugar nem de identidade** — já é sua própria árvore de rotas isolada (JWT de `FinalCustomer`), só passa a ser tratada como "o módulo delivery" na documentação/organização de pastas, sem mover URL.

Reorganização de pastas sugerida dentro do `web/src/pages/` atual (só nomenclatura/agrupamento, zero mudança de tooling):
```
web/src/pages/
  Storefront/   (delivery — já existe, já é /loja/:slug)
  Portal/       (cliente final — já existe, /portal/*)
  Accounting/   (contador — já existe, /contador/*)
  Pdv/          (novo, /pdv/*)
  Balcao/       (novo, /balcao/*)
  ...           (o resto do painel = "sistema", sem prefixo — /clientes, /produtos, /pedidos etc.)
```

## B.2 Compartilhamento de código

Como tudo roda no **mesmo bundle/app**, o compartilhamento já é automático — não existe o problema de "manter 4 pacotes sincronizados" que a versão anterior deste documento levantava. `apiClient.ts`, tipos de `types/api.ts`, tokens `--pt-*` e tema já são únicos por natureza (é um só projeto). Continua valendo a disciplina normal do projeto: componentes muito específicos de um módulo (tela de caixa do PDV, KDS do Balcão) ficam na pasta do módulo, sem forçar reuso prematuro com o resto do painel.

## B.3 Autenticação — problema eliminado pela decisão de URL

Não há nada a decidir aqui: mesma origem = mesmo `localStorage` = sessão de staff já funciona em `/pdv` e `/balcao` sem qualquer mudança de estratégia de auth. As identidades isoladas de propósito (Portal, Contador) continuam isoladas exatamente como estão hoje — a decisão de URL não afeta essas duas, que já são isolamento **lógico** (contexto/JWT próprio), não de origem.

## B.4 Deploy

**Nenhuma mudança de infraestrutura necessária.** `deploy.yml` continua fazendo **um** `npm ci` + **um** `npm run build` + **um** rsync de `web/dist/` — `/pdv` e `/balcao` são só mais rotas dentro do mesmo `dist/`, servidas pelo mesmo `.htaccess` de fallback SPA que já existe. Zero subdomínio novo pra configurar no painel da Hostinger, zero vhost, zero runner adicional.

## B.5 "O proprietário vendo tudo num único lugar"

**Verificado:** `orders.origin` existe (migration `2026_07_16_100000`), default `'staff'`, com valores reais em uso `'staff'` e `'storefront'` (`StorefrontCheckoutService` grava `origin: 'storefront'`; `OrderService` já **filtra** por `origin`). `orders.status` também existe (`'confirmed'`, `'pending_approval'`).

**Isso já resolve o requisito do dono** com uma extensão pequena: adicionar os valores `'pdv'` e `'table'` (Balcão — ver Documento 1, que materializa `Order origin='table'` no fechamento). Como tudo é o **mesmo app, mesma API, mesmo banco**, o dono vê **todos** os pedidos de qualquer módulo automaticamente — a decisão de URL nem precisa desse argumento pra funcionar (já funcionaria mesmo com subdomínios), mas fica mais simples ainda de garantir com um único domínio.

- **Enum de `origin` a estabelecer:** `staff` (balcão B2B/manual), `storefront` (loja/delivery), `pdv` (venda rápida balcão), `table` (restaurante). Padronizar os nomes **antes** de construir, para não ter que migrar dado depois.
- **Tela consolidada:** a listagem de pedidos atual + filtro/coluna por `origin` (badge colorido por canal) + métricas por canal no dashboard (o dashboard já agrega pedidos). Custo baixo — é UI sobre dado que já existe.
- **Navegação:** com tudo sob `/sistema` (implícito, sem prefixo) + `/pdv` + `/balcao` + `/loja` + `/contador` na mesma barra de endereço, a sidebar do painel principal pode linkar direto pros módulos operacionais (`/pdv`, `/balcao`) como mais um item de menu — não precisa trocar de aba/domínio pra "ver como está o caixa" ou "abrir o KDS".

---

## Ordem recomendada e conclusão

**PDV antes do Balcão.** Motivos concretos:
1. PDV reaproveita mais do que já existe (barcode, Order `staff`, pagamento) e **não** tem a complexidade de conflito offline do Balcão.
2. PDV **cria a infra de caixa** (`cash_sessions`, sangria/suprimento) e o `order_payments` (múltiplas formas) que o **Balcão vai herdar** no fechamento. Construir essa fundação no módulo mais simples reduz risco.
3. Validar caixa + pagamento no PDV dá confiança antes de enfrentar o offline-first do Balcão (o maior risco dos dois documentos).

**Arquitetura por URL, decidida e mais simples que o plano anterior.** `/pdv`, `/balcao`, `/loja`, `/contador` sob `sistema.pegaticket.com`, todos no mesmo app `web/`, mesmo deploy, mesma origem — sem migração de auth, sem monorepo de apps, sem configuração de subdomínio/vhost. Isso remove do caminho crítico o item que era o maior risco/custo da versão anterior deste documento (autenticação entre origens).

```
Sequência sugerida:
  PDV-1/2 (caixa + order_payments + venda rápida)   ──▶ fundação compartilhada
       └─ Balcão Fase 1/2 herda order_payments/caixa
  em paralelo: web/src/pages/Pdv/ e web/src/pages/Balcao/, mesmo app, mesmo deploy
  loja/portal/contador continuam onde estão (já são rotas isoladas, só reorganiza a documentação/pastas)
```

**Decisões que exigem escolha do dono / validação:**
- Enum final de `origin` (`staff`/`storefront`/`pdv`/`table`) — decidir antes de codar.
- Login rápido por PIN/operador em cima da sessão de staff no PDV/Balcão (tablet/caixa fixo) — desenho de UX a definir na Fase 1 de cada módulo, não é mudança de arquitetura de auth.
- Hardware do PDV (leitor teclado + `window.print()` cobrem o MVP; Web Serial/WebUSB só sob demanda). **[validação técnica]**
