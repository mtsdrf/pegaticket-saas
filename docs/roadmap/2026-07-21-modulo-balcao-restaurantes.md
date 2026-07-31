# Maskats — Roadmap: Módulo de Balcão para Restaurantes (mesa/comanda, cozinha, bar, offline-first)

> Documento de arquitetura e planejamento. **Não é implementação.** Levantado em 2026-07-21 lendo o código real de `api/` e `web/`, os roadmaps anteriores (`2026-07-20-producao-pagamentos-fiscal-contabilidade.md`, `2026-07-21-checklist-implantacao-modulos-opcionais.md`) e a memória do projeto.
>
> Restrição-guia de sempre: **orçamento baixo**, construir nativo, terceiro só onde é inevitável. A parte offline-first é a de **maior risco técnico** do módulo — está marcada honestamente ao longo do texto. Pontos que dependem de teste real de navegador/hardware que não posso fazer agora estão marcados **[requer validação técnica]**.

---

## Sumário executivo

O Maskats hoje modela `Order` como um documento **fechado e transacional**: nasce (`staff` → `confirmed`, ou `storefront` → `pending_approval`), reserva estoque, é pago e entregue. Um restaurante de mesa/balcão quebra essa premissa em dois pontos que **não têm equivalente hoje**:

1. **A comanda fica aberta por horas recebendo itens incrementalmente** — não é um pedido criado de uma vez. `Order` não tem esse ciclo de vida.
2. **O status de preparo é por item, não por pedido** — a cerveja sai do bar enquanto o risoto ainda está na cozinha. `orders.status` é um único enum para o documento inteiro; `order_items` não tem estado de produção.

A recomendação central é **não estender `Order` com um terceiro `origin`**. Em vez disso: criar um agregado novo (`Table` + `Comanda` + `ComandaItem`) que vive o ciclo de mesa aberta, e **materializar um `Order` (`origin='counter'`) só no fechamento da conta** — reaproveitando aí toda a infra de pagamento (`PaymentProviderInterface`), fiscal (cadastro já mapeado) e o painel consolidado do dono. Isso mantém `Order` como a "verdade financeira/fiscal" única e evita poluir a máquina de estados atual (que já tem `staff`+`storefront` funcionando com testes).

A parte offline-first é real e cara: um app de garçom PWA precisa lançar itens sem internet e sincronizar depois, e **dois garçons offline podem tocar a mesma mesa**. Isso exige fila de comandos local (IndexedDB), sincronização por Service Worker/Background Sync com fallback de polling, e uma estratégia de resolução de conflito. A recomendação é a **mais simples que funciona**: comandos append-only por mesa + serialização no servidor + last-write-wins só nos campos escalares, evitando CRDT/vector clock completo no MVP.

**Ordem sugerida:** construir primeiro o núcleo **online** (mesa/comanda/KDS/fechamento), validar com um restaurante real, e só então investir no offline-first — que é onde o esforço explode. Ver Documento 2 (atualizado em 2026-07-22): decisão final é **não separar por subdomínio** — o módulo vive como `sistema.maskats.com/balcao`, rota isolada dentro do mesmo app/deploy já existente.

---

## 1. Domínio do negócio

### 1.1 Conceitos que NÃO existem hoje (precisam ser criados)

| Conceito | Existe? | Observação |
|---|---|---|
| Mesa (`Table`) | Não | Objeto físico do salão: número/nome, capacidade, área (salão/varanda/balcão), status (livre/ocupada/reservada/fechando). |
| Comanda (`Comanda`) | Não | A conta aberta de uma mesa (ou de um cliente no balcão). Vive horas, recebe itens. Uma mesa pode ter 1 comanda (conta única) ou N comandas (conta por pessoa). |
| Item de comanda com estado de preparo (`ComandaItem`) | Não | `order_items` hoje não tem estado. Aqui cada item tem: `queued`→`sent_to_station`→`preparing`→`ready`→`delivered_to_table`→`cancelled`. |
| Estação de produção (`Station`) | Não | Cozinha, bar, chapa, etc. Cada `Product`/categoria roteia para uma estação. É o que decide "cerveja vai pro bar, prato vai pra cozinha". |
| Taxa de serviço | Parcial | `orders` tem `discount_amount`/`delivery_fee`, mas não um campo de serviço (10%) opcional/renunciável pelo cliente. |
| Divisão de conta | Não | Dividir por pessoa (comandas separadas) ou por valor (rateio no fechamento). |
| Transferência/junção de mesa | Não | Mover comanda entre mesas; juntar mesas numa conta só. |

### 1.2 Conceitos reaproveitáveis (já existem)

| Já existe | Reaproveitamento |
|---|---|
| `Product` (com `unit`, `barcode`, preço, estoque) | O cardápio é o catálogo de produtos que já existe. Restaurante usa uma **categoria** por produto para rotear à estação. |
| `Order` + `OrderItem` | Materializados **só no fechamento** da comanda (`origin='counter'`), preservando a verdade financeira/fiscal única. |
| `PaymentProviderInterface` (`createPixChargeForOrder`, `refund`, etc.) | Fechamento da conta reusa 100% a camada de pagamento do roadmap anterior. Dinheiro/cartão/Pix. |
| `tenant_settings` | Onde ligam as flags do módulo: taxa de serviço %, serviço obrigatório ou não, imprimir na estação vs. só tela, etc. |
| Baixa de estoque de `Order` | Ao fechar/enviar item, baixa estoque de insumo — **mas** restaurante tem ficha técnica (receita), que é conceito novo e fica para fase posterior (ver 6). No MVP, baixa direto o produto vendável, como já é hoje. |
| `AuditLog` (Event/Listener) | Toda mutação de comanda auditada, seguindo o padrão do projeto. |

### 1.3 Fluxo do garçom → cozinha/bar

```
Abrir mesa/comanda
   → adicionar item (escolhe produto do cardápio, qty, observação "sem cebola")
   → item roteia por categoria/Station: bar recebe bebida, cozinha recebe prato
   → item aparece na tela da estação (KDS) e/ou imprime comanda de produção
   → estação marca item: preparing → ready
   → garçom vê "pronto", entrega na mesa → delivered_to_table
   → (repete durante horas)
   → fechar conta: soma itens + taxa de serviço 10% (opcional) → materializa Order
   → pagamento (dinheiro/cartão/Pix via PaymentProviderInterface) → baixa
   → (fiscal opcional: NFC-e via módulo fiscal já mapeado)
   → mesa volta a "livre"
```

**Regra de roteamento (o coração do integração cozinha/bar):** cada `Product` pertence a uma categoria; cada categoria mapeia para uma `Station`. Um item adicionado à comanda gera um **ticket de produção** endereçado à estação certa. Bar e cozinha veem só o que é delas. Isso é o que o dono descreveu como "bebida vai pro bar, prato vai pra cozinha".

### 1.4 Estado de preparo POR ITEM (requisito explícito)

Não modelar preparo no `orders.status`. Modelar em `comanda_items.prep_status`:

```
queued → sent_to_station → preparing → ready → delivered_to_table
                                   ↘ cancelled (com motivo, auditado)
```

Um item pode estar `ready` enquanto outro da mesma comanda está `preparing`. O KDS mostra fila por estação; o app do garçom mostra por mesa. Métrica útil de graça: tempo `sent_to_station → ready` por estação (SLA de cozinha).

### 1.5 Fechamento, taxa de serviço e divisão de conta

- **Taxa de serviço 10%**: campo em `tenant_settings` (percentual + "obrigatória/renunciável"). No fechamento, some sobre o subtotal. O cliente pode recusar (registrar quem recusou/autorizou). **[requer validação jurídica]** — a "taxa de serviço/gorjeta" tem tratamento legal e trabalhista próprio no Brasil (repasse ao garçom, incidência); não é receita pura do tenant. Marcar como parametrizável e alertar o dono a validar com contador.
- **Divisão por pessoa**: modelar como N comandas na mesma mesa. Cada pessoa fecha a sua.
- **Divisão por valor (rateio)**: no fechamento de uma comanda única, dividir o total em N partes iguais (ou valores manuais) — é só apresentação de pagamento, gera **um** `Order` com N `payments`.
- **Múltiplas formas de pagamento no mesmo fechamento**: R$50 Pix + R$30 dinheiro. Isso é idêntico ao requisito do PDV (Documento 2, Parte A) — **modelar uma vez, reaproveitar nos dois**: uma tabela `order_payments` (n pagamentos por `Order`), não o `is_paid/paid_amount` escalar de hoje.

---

## 2. Decisão de modelagem: estender `Order` vs. agregado novo

**Recomendado: agregado novo que gera `Order` no fechamento.** Justificativa concreta (não genérica):

- `Order` hoje assume nascimento único + reserva de estoque imediata (`stock_reserved` default `true`) + máquina `staff`/`storefront` com testes. Uma comanda que fica 3h aberta recebendo itens **não cabe** nesse ciclo sem quebrar invariantes existentes (ex.: `is_paid`/`paid_at`, reserva de estoque, o guard de `booted()`).
- Um terceiro `origin='counter'` em `Order` **desde a abertura da mesa** obrigaria a criar estados intermediários (`open`, `receiving_items`) na máquina que hoje é dos outros dois fluxos — risco de regressão no que funciona.
- Materializar `Order` **só no fechamento** mantém `Order` como está (nasce fechado, `origin='counter'`, `status='confirmed'`) e **dá de graça**: o pagamento (`PaymentProviderInterface`), o fiscal (cadastro fiscal por produto/tenant já mapeado), o relatório/analytics e o **painel consolidado do dono** (ele já filtra por `origin` — Documento 2, item 5). Só precisa adicionar `'counter'` ao conjunto de origens aceitas.

**Contrapartida honesta:** baixa de estoque só no fechamento significa que, durante a comanda aberta, o estoque "vendido mas não baixado" fica invisível ao controle de estoque. Para bebida em geladeira isso importa. **Mitigação:** baixar estoque no momento em que o item é **enviado à estação** (`sent_to_station`), não no fechamento — desacoplando "baixa de estoque do item" de "materialização do Order". Decisão a validar com o dono conforme a operação real. **[requer validação de operação]**

### Esboço de tabelas (proposta, seguir padrão `BaseModel`: uuid + soft delete + auditoria + `tenant_id`)

- `stations` — `tenant_id`, `name`, `type` (`kitchen`|`bar`|`grill`|...), `is_active`.
- `product_station` (ou `category_station`) — roteamento produto/categoria → estação.
- `tables` — `tenant_id`, `label`, `area`, `seats`, `status`.
- `comandas` — `tenant_id`, `table_id` (nullable p/ balcão), `label` (nome da pessoa), `status` (`open`|`closing`|`closed`|`cancelled`), `opened_by`, `opened_at`, `service_fee_percent`, `order_id` (nullable, preenchido no fechamento).
- `comanda_items` — `comanda_id`, `product_id`, `qty`, `unit_price` (congelado), `notes`, `station_id`, `prep_status`, timestamps de cada transição, `added_by`, `cancelled_reason`.
- `comanda_events` — trilha append-only (essencial para o offline, ver Seção 3).
- `order_payments` — `order_id`, `method`, `amount_cents`, `provider_charge_id`, `status`, `idempotency_key` (compartilhada com PDV).

---

## 3. Offline-first — a parte crítica

> **Aviso de risco:** esta é a parte de maior risco e maior esforço do módulo. É onde "orçamento baixo" mais tensiona com "funciona de verdade num restaurante lotado com Wi-Fi ruim". Ser conservador aqui.

### 3.1 Armazenamento local

- **IndexedDB** é a única opção nativa do navegador com capacidade suficiente (localStorage é síncrono e pequeno demais, e já é usado para JWT/carrinho). Acessar IndexedDB cru é verboso e propenso a erro.
- **Lib recomendada: `Dexie.js`** (ou `idb`, mais minimalista). Ambas são leves, gratuitas, sem custo de serviço, MIT. **Dexie** ganha por ter transações, índices e observabilidade (`liveQuery`) que combinam com a fila de comandos. **`idb`** se quiser dependência mínima. Recomendo **Dexie** pelo custo/benefício de manutenção. **[requer validação técnica]** do tamanho de bundle contra o baseline atual (o projeto já vigia `chunkSizeWarningLimit`).
- Guardar localmente: cardápio (produtos + preços + roteamento de estação, para o garçom lançar offline), estado das mesas/comandas do próprio garçom, e a **fila de comandos pendentes**.

### 3.2 Fila de comandos offline (o modelo correto)

Não sincronizar "estado" — sincronizar **comandos** (eventos append-only). Cada ação do garçom vira um comando imutável na fila local:

```
{ id: uuid, type: 'OPEN_COMANDA' | 'ADD_ITEM' | 'CANCEL_ITEM' | 'CLOSE_COMANDA',
  comanda_uuid, payload, client_ts, device_id, synced: false }
```

- O UUID é gerado **no cliente** (o projeto já usa uuid público em toda entidade — coerente). Isso permite abrir comanda e adicionar itens offline referindo-se ao mesmo `comanda_uuid` antes de o servidor conhecê-lo.
- Ao reconectar, a fila é drenada **em ordem** para um endpoint de sincronização (`POST /counter/sync` com lote de comandos + `idempotency_key` por comando — reusa o conceito de idempotência que o roadmap de pagamentos já estabeleceu).
- O servidor aplica cada comando idempotentemente (comando já visto = no-op) e devolve o estado canônico da comanda.

### 3.3 Resolução de conflito (2 dispositivos offline na mesma mesa)

Cenário real: garçom A (offline) adiciona 2 chopes à mesa 5; garçom B (offline) adiciona 1 porção à mesma mesa 5; os dois sincronizam em ordens diferentes.

- **`ADD_ITEM` é comutativo e append-only** — não há conflito real: ambos os itens entram. Este é 90% dos casos e resolve sozinho com a fila append-only. Não precisa de CRDT para isso.
- **Conflito real só existe em campos escalares mutáveis** (ex.: dois dispositivos mudam `service_fee_percent` ou fecham a mesma comanda). Para esses: **serialização por comanda no servidor** (o servidor processa comandos de uma comanda em ordem de chegada, com uma versão/lock otimista por comanda) + **last-write-wins por timestamp** no campo escalar. Simples, previsível, barato.
- **Não recomendo vector clock nem CRDT completo no MVP** — é complexidade alta para um ganho que o modelo append-only já entrega na maioria dos casos. Marcar como evolução só se a operação real mostrar conflitos escalares frequentes. **[requer validação de operação]**
- **Fechamento é o ponto sensível**: `CLOSE_COMANDA` deve ser idempotente e "trancar" a comanda no servidor; um segundo fechamento offline que chega depois recebe erro claro ("comanda já fechada") e o app do garçom reconcilia (mostra a conta fechada, não duplica pagamento). **Nunca** materializar dois `Order` para a mesma comanda — a `idempotency_key` por comanda garante isso.

### 3.4 O que a COZINHA/bar faz enquanto o garçom está offline

Ponto honesto e frequentemente ignorado: **se o garçom está offline, a cozinha não recebe o pedido** — não há mágica, o ticket não saiu do tablet dele. Duas realidades:

- **KDS (tela da cozinha) é um dispositivo fixo, normalmente com internet melhor** (cabo/Wi-Fi do balcão). O KDS deve ser **online-first com fallback**: ele consome os tickets via polling/websocket do servidor. Quando o garçom offline reconecta e sincroniza, os tickets aparecem no KDS **nesse momento** — com atraso igual ao tempo que o garçom ficou offline.
- **Consequência operacional a comunicar ao dono:** offline no garçom protege contra "perder o pedido", não contra "cozinha demorar". Se a internet do salão inteiro cai, o fluxo degrada para o modo manual (comanda de papel) — o módulo deve ter um **modo de contingência impresso** ou pelo menos não travar. Isso é aceitável e honesto; prometer "cozinha em tempo real mesmo sem internet" seria falso.
- **KDS offline** (cozinha sem internet) é escopo bem maior (o KDS teria sua própria fila) e **não recomendado no MVP** — a cozinha é um ponto fixo, resolver a rede dela é mais barato que codar offline no KDS.

### 3.5 Sincronização automática ao reconectar

- **Service Worker + Background Sync API** (`sync`/`periodicSync`) é o mecanismo nativo, gratuito, para drenar a fila quando a conexão volta — **mas** o suporte é irregular: **Chrome/Android ok; Safari/iOS não suporta Background Sync de forma confiável** (WebKit historicamente não implementa `SyncManager`). **[requer validação técnica no aparelho real do restaurante]**.
- **Fallback obrigatório (não opcional):** ouvir o evento `window.online` + um `setInterval` de reconciliação enquanto a fila tiver itens pendentes. Isso funciona em todo navegador e é o caminho principal em iOS. Background Sync vira só um "bônus" onde existe.
- O projeto **já tem Service Worker** (`vite-plugin-pwa`, `generateSW`, com `push-sw.js` via `importScripts`). Estender esse SW para incluir a lógica de sync é viável, mas cuidado: a estratégia atual é `generateSW` (Workbox gera o arquivo). Lógica customizada de Background Sync exige `importScripts` adicional (como já se faz com push) ou migrar para `injectManifest` — **decisão técnica a validar** para não quebrar o push já funcionando. **[requer validação técnica]**

---

## 4. Telas e dispositivos

| Tela | Dispositivo | Modo | Observação |
|---|---|---|---|
| **App do garçom** | Celular/tablet, PWA | Offline-first | Mesas, abrir/adicionar item, ver "pronto", fechar conta. Mobile-first é prioridade do projeto — alinhado. |
| **KDS cozinha** | TV/tablet fixo | Online-first + fallback | Fila de tickets da estação `kitchen`, marcar preparing/ready. Layout de leitura à distância (fonte grande, colunas por status). |
| **KDS bar** | Tablet fixo | Online-first + fallback | Mesma engine do KDS, filtrada por estação `bar`. Reuso total de componente. |
| **Caixa/fechamento** | Balcão | Online-first | Fecha conta, taxa de serviço, divisão, múltiplas formas de pagamento, (opcional) NFC-e. Muito próximo do PDV (Documento 2). |

Todas reaproveitam os tokens `--mk-*` e o design system. O KDS é a única tela com necessidade visual nova (leitura à distância), candidata ao `ui-ux-master`.

---

## 5. Riscos e complexidade (sem maquiar)

1. **Offline-first é o maior risco do módulo.** Sincronização, conflito e Service Worker em iOS são onde o esforço explode. Recomendação forte: **entregar o núcleo online primeiro**, validar com restaurante real, e só depois offline. Um restaurante com Wi-Fi decente talvez nem precise do offline no dia 1.
2. **Background Sync em iOS é fraco.** Se o restaurante usa iPad (comum), o caminho é o fallback de polling — funciona, mas exige teste real. **[requer validação técnica]**
3. **Taxa de serviço tem implicação trabalhista/fiscal.** Não é receita simples. **[requer validação jurídica/contábil]**
4. **Baixa de estoque de comanda aberta** (item vendido mas Order não materializado) precisa de decisão explícita (baixar no `sent_to_station`). **[requer validação de operação]**
5. **Ficha técnica/receita** (baixar insumo, não produto) é um módulo grande — **fora do MVP**, senão o escopo dobra.
6. **Concorrência de fechamento** (dois dispositivos fechando a mesma mesa) precisa de lock otimista + idempotência bem testados — é onde um bug vira "cliente pagou duas vezes".

---

## 6. Roadmap em fases

Esforço: **P** = dias · **M** = 1–2 semanas · **G** = 3+ semanas · **GG** = mês(es), risco alto.

### FASE 1 — Núcleo online de mesa/comanda — **G**
- Tabelas: `stations`, `tables`, `comandas`, `comanda_items`, roteamento produto→estação. (`BaseModel`, `tenant`-scoped, auditado.)
- App do garçom (online): abrir mesa, adicionar item, observação, cancelar item.
- Estado de preparo **por item** + KDS cozinha + KDS bar (mesma engine, filtro por estação).
- Reaproveita: `Product`/cardápio, `tenant_settings`, `AuditLog`.
- **Sem offline, sem fechamento financeiro ainda.** Valida o fluxo operacional.

### FASE 2 — Fechamento e pagamento — **M/G**
- Materializar `Order` (`origin='counter'`) no fechamento. Adicionar `'counter'` às origens aceitas.
- Taxa de serviço 10% parametrizável em `tenant_settings`. **[validação jurídica]**
- `order_payments` (múltiplas formas no mesmo fechamento) — **modelo compartilhado com o PDV**.
- Reaproveita `PaymentProviderInterface` (dinheiro/Pix/cartão) do roadmap de pagamentos.
- Divisão de conta (por pessoa = N comandas; por valor = N payments).
- **Depende de:** Fase B do roadmap anterior (camada de pagamento) para Pix/cartão reais; dinheiro funciona sem PSP.

### FASE 3 — Offline-first do app do garçom — **GG (maior risco)**
- IndexedDB via Dexie: cardápio + comandas locais + fila de comandos.
- Fila append-only de comandos + endpoint `POST /counter/sync` idempotente.
- Serialização por comanda + last-write-wins escalar (sem CRDT).
- Service Worker: Background Sync **+ fallback de polling `online`** (fallback é o caminho principal). **[validação técnica em iOS/aparelho real]**
- KDS permanece online-first (não offline no MVP).

### FASE 4 — Transferência/junção de mesa + operação avançada — **M**
- Transferir comanda entre mesas, juntar mesas.
- Reserva de mesa, mapa do salão, métricas de SLA por estação.

### FASE 5 (evolução, fora do MVP) — Ficha técnica + fiscal — **GG**
- Ficha técnica/receita (baixa de insumo). Grande — só com demanda real.
- NFC-e do fechamento via módulo fiscal já mapeado (roadmap anterior, Fase D2). **[validação fiscal por UF]**

### Dependências e o que reaproveita
```
Fase 1 (online) ──▶ Fase 2 (fechamento) ──▶ Fase 3 (offline)
                          │                      └─ mais arriscada, só após validação real
                          └─ reusa PaymentProviderInterface + cadastro fiscal (roadmap anterior)
Fase 4 e 5 são posteriores; 5 depende da Fase D (fiscal) do roadmap anterior.
```

**Reaproveitamento direto dos módulos já existentes:** `Product`/cardápio, `Order`/`OrderItem` (no fechamento), `PaymentProviderInterface`, cadastro fiscal por produto/tenant, `AuditLog`, `tenant_settings`, tokens de design, Service Worker PWA. **Conceitos genuinamente novos:** mesa, comanda, item com estado de preparo, estação/roteamento, fila offline, `order_payments` (este último compartilhado com o PDV — construir uma vez).
