# PegaTicket — Mapeamento Global Atual e Roadmap de Desenvolvimento

Data de referência: **2 de agosto de 2026**

> **Nota de revisão — 3 de agosto de 2026:** documento revisado em sessão Claude Code, cruzando o texto abaixo com o código real (`api/app`, rotas ativas, suíte de testes). Vários itens listados como pendentes/parciais já estavam entregues e foram movidos para as seções de "entregue"; números de rotas e testes atualizados. Ver detalhes nas seções 3, 4 e 6.

Documento-base analisado: [pegaticket_especificacao_completa.md](/home/mtsdrf/workspace/pegaticket-saas/pegaticket_especificacao_completa.md)

## 1. Objetivo deste diagnóstico

Este documento consolida:

- o estado real do produto hoje no repositório;
- o delta entre o código atual e a especificação completa;
- o que já está pronto;
- o que está parcial;
- o que ainda não existe;
- o que precisa ser corrigido ou removido por desalinhamento de contexto;
- a ordem recomendada de desenvolvimento para as próximas fases.

Ele substitui qualquer leitura otimista baseada apenas em documentação antiga ou memória operacional desatualizada.

## 2. Metodologia usada

O mapeamento foi feito cruzando:

- a especificação completa com 55 seções;
- o inventário real de backend em `api/app`;
- o inventário real de páginas em `web/src/pages`;
- as rotas Laravel ativas em `php artisan route:list`;
- a suíte de testes atual.

Estado validado nesta leitura (atualizado 2026-08-03):

- backend com **260 rotas** ativas (era 249);
- backend com **612 testes passando** (**2155 assertions**) após estabilização dos testes de pagamento e idempotência em **3 de agosto de 2026**;
- frontend com páginas reais para administração, eventos, checkout, portal, tickets, analytics, reconciliação, suporte e operação;
- suíte E2E web já instalada e funcional.

## 3. Estado real do produto hoje

### 3.1 Núcleo já existente e utilizável

Os seguintes blocos já possuem implementação concreta no backend e/ou frontend:

- **Multiempresa básico**: tenant, tenant user, tenant role, overrides de feature, troca de contexto e isolamento por organização.
- **Autenticação base**: login por senha, recuperação de senha, sessão autenticada, portal do cliente com OTP.
- **RBAC base**: grupos globais, papéis do tenant e permissões por funcionalidade/ação.
- **Onboarding base**: cadastro self-service da empresa, aceite legal, ativação inicial.
- **Jurídico/LGPD operacional básico**: documentos legais, aceite versionado, exportação e solicitações de privacidade.
- **Eventos**: categorias, eventos, sessões, tipos de ingresso, lotes, adicionais simples (`event_products`), venues e assentos.
- **Storefront público**: catálogo de eventos, detalhe do evento, favoritos, carrinho, hold de inventário, checkout e rastreio público.
- **Alocação de assentos** *(entregue — atualizado 2026-08-03)*: melhor assento disponível e assentos contíguos automáticos na loja pública via `SeatAllocationService` (`api/app/Services/Storefront/SeatAllocationService.php`), respeitando contiguidade de fileira e expostos por setor+quantidade sem exigir escolha assento a assento; acessibilidade por assento (`is_accessible`) já indicada/filtrável no mapa da loja; bloqueio administrativo de assento via CRUD de `Seat` (`status: bloqueado/indisponivel`), inclusive edição em lote no editor de mapa.
- **Vendas**: criação manual, vendas da loja, parcelas, cancelamento, refund manual estruturado, timeline de workflow.
- **Promoções básicas** *(entregue — atualizado 2026-08-03)*: cupom, incluindo cortesia via cupom com `type: percentage, value: 100`.
- **Pagamentos base** *(atualizado 2026-08-03)*: Mercado Pago, Pix e **cartão de crédito/débito via PagBank** (tokenização `card.encrypted`, `createCardCharge()`, `CREDIT_CARD`/`DEBIT_CARD`, parcelamento via `installments`, validação de titular/CPF-CNPJ — interface própria, sem checkout transparente de terceiro, decisão confirmada com o usuário), webhooks, reconciliação e camada de payment issues.
- **Tickets**: emissão, listagem, QR/token, **transferência de titularidade** (`TicketService::transfer()`, rotaciona code/qr_token, endpoint no Portal do comprador), check-in básico e histórico de check-in.
- **Guest list / cortesias estruturadas** *(entregue — atualizado 2026-08-03)*: domínio `GuestList`/`GuestListEntry` (`api/app/Models/GuestList/`), com link individual de autoatendimento por convidado (rota pública `/convite-ingresso/:token`), reaproveitando o pipeline normal de Sale/Ticket.
- **Portal do comprador**: login OTP, perfil, favoritos, vouchers, minhas vendas.
- **Caixa / bilheteria presencial** *(entregue — atualizado 2026-08-03)*: domínio `CashSession` (`api/app/Models/CashSession/`), abertura/fechamento de caixa com reconciliação contra vendas em dinheiro; comprovante de venda imprimível.
- **Check-in** *(atualizado 2026-08-03)*: validação manual, leitura de QR, histórico, modo offline com fila local de sincronização, e sincronização entre dispositivos via polling compartilhado de 15s no resumo operacional (`checkinOperationalSummary`) — suficiente para o nível atual de operação, sem necessidade de WebSocket; dashboard operacional em tempo quase real.
- **Inventário — virada de lote** *(entregue — atualizado 2026-08-03)*: virada automática de lote (`auto_advance`) implementada e testada em `StorefrontHoldService`.
- **Analytics inicial**: overview, produtos/adicionais, locais, sazonalidade, clientes, atrasos.
- **Financeiro inicial**: reconciliação e visão administrativa de problemas de pagamento.
- **Administração global**: usuários, grupos, funcionalidades, planos, tenants, auditoria.
- **Assinatura SaaS**: planos, subscription, invoice, cobrança recorrente e telas administrativas/operacionais.
- **Suporte básico**: `help requests` e tela de tickets de suporte.

### 3.2 Blocos existentes mas parciais

Estão implementados parcialmente, porém ainda abaixo da especificação:

- **Identidade**: existe cadastro/login/recuperação, mas faltam passkeys, MFA robusto, gestão de dispositivos e KYC/KYB real.
- **Organizações e permissões**: existe base forte de RBAC, mas faltam aprovações em múltiplas etapas, segregação de funções madura e escopo fino por evento/portaria.
- **Locais e mapa** *(reduzido — atualizado 2026-08-03)*: venues, seats e map versions existem; melhor assento, assentos contíguos, acessibilidade por assento na loja pública e bloqueio administrativo já foram entregues (ver 3.1). Ainda faltam experiência de mapa interativo mais rica e operação de capacidade/portaria mais madura.
- **Eventos**: CRUD existe, mas fluxo editorial, aprovação, publicação programada, adiamento e operação documental ainda estão incompletos.
- **Inventário** *(reduzido — atualizado 2026-08-03)*: ticket types, batches e holds existem, incluindo virada automática de lote (ver 3.1); ainda faltam lista de espera, cotas de inventário por canal (adiado, precisa de decisão de produto sobre o que define um "canal"), combos e upgrade real.
- **Checkout**: existe fluxo público consistente, mas faltam guest checkout maduro, formulários por ingresso, split de pagamento, retentativas completas e jornada de alta demanda.
- **Pagamentos** *(atualizado 2026-08-03)*: Pix e cartão (crédito/débito, tokenização, parcelamento) via PagBank já existem; falta múltiplos gateways ativos, roteamento inteligente, antifraude e split/payout financeiro real.
- **Vendas/Vendas**: o domínio base existe, mas exportações, documentos operacionais, importação e busca operacional avançada ainda estão incompletos.
- **Tickets pós-compra** *(reduzido — atualizado 2026-08-03)*: emissão, check-in e transferência de titularidade já existem (ver 3.1); ainda faltam wallet passes (adiado — sem credenciais Apple/Google ainda), revenda oficial e QR rotativo avançado.
- **Promoções**: cupom e cortesia via cupom 100% e guest list já existem (ver 3.1); ainda faltam links promocionais, afiliados e campanhas condicionais.
- **Comunicação**: já há eventos e alguma mensageria operacional, mas falta hub transacional completo com templates, fallback e tracking unificado.
- **Check-in** *(reduzido — atualizado 2026-08-03)*: base funcional entregue, com offline forte e sincronização entre dispositivos via polling de 15s (ver 3.1); ainda faltam reentrada, controle por zona mais fino e supervisão operacional avançada.
- **Financeiro/Fiscal**: existem refund, conciliação e parte da fundação fiscal; a **Fase 5 foi iniciada** no desenho e já ganhou fundação de taxa global, recebíveis/ledger iniciais e o primeiro payload real de **PagBank Split + Custódia** (conta primária da plataforma + conta secundária do organizador, `D+1` pós-evento). Ainda faltam fechamento operacional, liberação de custódia, borderô, ERP e política completa de estorno/chargeback.
- **API e integrações**: API versionada existe, assim como webhooks internos de pagamento, mas falta a superfície pública madura para terceiros.

## 4. Delta contra a especificação

### 4.1 Cobertura forte hoje

Estas áreas da especificação já têm fundação pronta suficiente para continuar evoluindo sem recomeçar:

- Seções **4, 6, 8, 9, 11, 12, 13, 14, 19, 25, 29, 31, 36, 37, 42 e 43** em nível básico a intermediário.

Resumo:

- multi-tenant;
- permissões;
- eventos;
- inventário base (incluindo virada automática de lote);
- checkout base;
- pagamentos base (Pix e cartão via PagBank, com parcelamento e tokenização);
- vendas/vendas;
- tickets (incluindo transferência de titularidade);
- guest list / cortesias estruturadas;
- alocação de assentos (melhor assento, contíguos, acessibilidade na loja pública);
- caixa / bilheteria presencial (`CashSession`);
- check-in (incluindo offline e sincronização por polling entre dispositivos);
- suporte;
- analytics inicial;
- administração global;
- LGPD, segurança, CI/CD e testes iniciais.

*(atualizado 2026-08-03: cartão/parcelamento PagBank, transferência de titularidade de ingresso, guest list, alocação de assentos e caixa presencial confirmados como entregues e movidos para esta lista — antes apareciam como pendentes/parciais)*

### 4.2 Cobertura parcial relevante

Estas áreas já começaram, mas ainda não cumprem o nível esperado da especificação:

- **Seção 5**: identidade;
- **Seção 7**: locais, mapa e capacidade *(atualizado 2026-08-03: melhor assento, assentos contíguos e acessibilidade por assento na loja pública já entregues — ver 3.1/4.1; falta mapa interativo mais rico e portaria/capacidade mais madura)*;
- **Seção 10**: marketplace e descoberta;
- **Seção 15**: promoções e cortesias *(atualizado 2026-08-03: cortesia via cupom 100% e guest list já entregues; faltam links promocionais, afiliados e campanhas condicionais)*;
- **Seção 16**: afiliados e canais;
- **Seção 17**: CRM e automação;
- **Seção 18**: comunicação transacional;
- **Seção 20**: bilheteria/POS *(atualizado 2026-08-03: caixa presencial com `CashSession` e comprovante imprimível já entregues; falta portarias/zonas e supervisão operacional mais madura)*;
- **Seções 26, 27 e 28**: cancelamentos, financeiro profundo e fiscal;
- **Seção 32**: API pública e ecossistema de integrações;
- **Seções 38 a 41**: resiliência, observabilidade, concorrência e alta demanda.

### 4.3 Grandes blocos ainda ausentes

Os seguintes grupos estão essencialmente **não implementados** ou só aparecem como intenção futura:

- **Credenciamento corporativo completo** (seção 21) — Fase 8, não iniciada.
- **Eventos online e híbridos** (seção 22) — Fase 8, não iniciada.
- **Cashless/consumo interno** (seção 24).
- **Revenda oficial e cadeia de custódia avançada** (seção 14 avançada) — *(atualizado 2026-08-03: transferência básica de titularidade já existe via `TicketService::transfer()`; o que falta é especificamente revenda oficial verificada e QR rotativo avançado)*.
- **Wallet pass (Apple/Google)** — adiado por decisão do usuário, sem credenciais ainda.
- **Afiliados/comissionamento de verdade** (seção 16) — Fase 6, não iniciada.
- **CRM, campanhas, automações e remarketing reais** (seção 17) — Fase 6, não iniciada.
- **Fila virtual enterprise e anti-bot** (seção 30 e seção 44) — Fase 7, não iniciada.
- **Múltiplos gateways com roteamento e redundância operacional** (seção 12 avançada).
- **Repasses, ledger, recebíveis e previsão de caixa de verdade** (seção 27) — Fase 5 em kickoff ativo: modelo aprovado, fundação local criada e payload inicial de split/custódia PagBank implementado; ainda faltam execução operacional de repasse e fechamento financeiro completo.
- **Documentos fiscais e integração ERP completas** (seção 28) — Fase 5.
- **Marketplace de integrações, portal do desenvolvedor e sandbox** (seção 32) — Fase 8.
- **Migração/importação estruturada de dados legados** (seção 33).
- **Internacionalização completa** (seção 34) — Fase 8.
- **BI / data warehouse / recomendações preditivas** (seção 29 avançada) — Fase 8.
- **Cotas de inventário por canal** (loja online vs. presencial) — adiado, precisa de decisão de produto sobre o que define um "canal".

## 5. Ajustes e remoções recomendados

### 5.1 Itens que precisam de correção imediata de contexto

- **README atual está desalinhado** com o produto:
  - ainda fala em clientes, produtos, estoque e operação comercial genérica;
  - ainda descreve `site/` e até `app/` como partes ativas do ecossistema;
  - precisa ser reescrito para o recorte real do PegaTicket.
- **Memórias internas em `.claude/memory` estão parcialmente desatualizadas**:
  - várias ainda descrevem PDV, balcão, iFood, delivery, estoque, catálogo alimentar e rotas antigas;
  - precisam ser separadas entre histórico legado e contexto ativo.
- **Roadmaps antigos ainda misturam migração de Maskats com produto final**:
  - hoje servem como histórico técnico;
  - não devem ser tratados como roadmap principal do PegaTicket.

### 5.2 Blocos candidatos a remoção de escopo do produto atual

Se o foco agora é uma plataforma de ingressos e eventos, os seguintes blocos devem ser tratados como **fora de fase** ou removidos do discurso ativo:

- referências a **iFood/marketplace de food**;
- referências a **PDV/Balcão/comandas/restaurante** se não forem reaproveitadas como bilheteria presencial — o domínio `CashSession` (caixa) já é a reaproveitação confirmada para bilheteria presencial, entregue (ver 3.1/4.1);
- resíduos de **estoque físico/logística de delivery**;
- materiais que descrevem o produto como SaaS de comércio geral em vez de ticketing.

### 5.3 Blocos que devem ser congelados, não expandidos

- `site/` institucional, se a decisão continuar sendo operar sem frente pública separada;
- qualquer camada antiga ligada a marketplace de delivery;
- módulos de CRM genérico que não estejam ligados diretamente a comprador, evento e recompra.

## 6. Leitura estratégica: em que fase estamos de verdade

**Atualizado 2026-08-03**: o PegaTicket avançou de "entre Fase 1 funcional e pré-Fase 2" para **Fases 0 a 4 essencialmente fechadas**, com a **Fase 5 (financeiro)** agora em **kickoff ativo**: o modelo de produto foi destravado e a primeira fatia técnica já começou, mas o desenho financeiro profundo ainda está só no início.

Em termos práticos:

- o **núcleo transacional** já existe, incluindo pagamento com cartão parcelado via PagBank;
- o **núcleo operacional de ticketing** já está consolidado (transferência de titularidade, guest list/cortesias, alocação de assentos, caixa presencial, sincronização de check-in);
- o sistema **ainda não é competitivo nacionalmente** contra plataformas maduras, mas fechou boa parte do núcleo Must Have;
- o produto **ainda não está pronto para alta demanda séria** (Fase 7 não iniciada);
- **Fase 5 (financeiro/repasses/ledger/comissões)** já começou em modo de discovery + fundação técnica, mas ainda está longe de concluída;
- o discurso comercial e documental **ainda precisa ser consolidado** para o domínio final.

Diagnóstico objetivo:

- **Pronto para continuar construção do produto**: sim.
- **Pronto para operar eventos pequenos e médios com bilheteria presencial, assentos e pós-compra básico**: sim.
- **Pronto para ser tratado como plataforma enterprise de eventos**: ainda não.
- **Pronto para vendas críticas de grande porte**: não.

## 7. Roadmap recomendado

### Fase 0 — Saneamento e alinhamento do produto

**Status (2026-08-03): parte técnica essencialmente coberta pelo núcleo entregue; documentação/memória ainda pedem revisão pontual (fora do escopo desta atualização de roadmap).**

Objetivo: eliminar ambiguidade entre legado, documentação e escopo atual.

Entregas:

- reescrever `README.md` para o produto real;
- criar memória operacional nova do PegaTicket atual;
- classificar documentos antigos em `histórico` vs `ativo`;
- remover ou congelar referências de delivery/food/estoque antigo;
- revisar nomenclatura remanescente de domínio nas rotas, comentários e contratos.

Critério de saída:

- qualquer pessoa nova entende o sistema como plataforma de ingressos, não como fork de comércio genérico.

### Fase 1 — Fechamento do núcleo comercial do ticketing

**Status (2026-08-03): essencialmente fechada** — lifecycle de venda/pagamento (incluindo cartão PagBank), holds/lotes com virada automática, emissão e transferência de ticket, e reembolso/cancelamento básico já entregues (ver seção 3.1).

Objetivo: concluir o núcleo Must Have da especificação.

Entregas:

- completar lifecycle de evento e publicação;
- completar lifecycle de venda e pagamento;
- endurecer holds, lotes e regras de disponibilidade;
- fechar emissão de ticket, reemissão e rastreabilidade;
- ampliar check-in com zonas, reentrada, motivos e métricas básicas;
- fechar reembolso/cancelamento básico ponta a ponta;
- consolidar comunicação transacional mínima.

Critério de saída:

- evento presencial simples pode ser criado, vendido, operado e encerrado sem intervenção técnica manual.

### Fase 2 — Bilheteria presencial e operação de acesso robusta

**Status (2026-08-03): essencialmente fechada** — caixa presencial (`CashSession`), comprovante imprimível, modo offline de check-in e sincronização entre dispositivos (polling 15s) e dashboard operacional em tempo quase real já entregues (ver seção 3.1). Falta amadurecer portarias/zonas e supervisão operacional mais fina.

Objetivo: transformar o núcleo atual em operação real de evento.

Entregas:

- bilheteria presencial real;
- caixa e estações de venda;
- impressão/comprovantes operacionais;
- modo offline controlado para acesso;
- sincronização entre dispositivos de check-in;
- portarias, zonas e supervisão operacional;
- dashboards operacionais em tempo quase real.

Critério de saída:

- evento pequeno e médio pode operar compra presencial e acesso com contingência.

### Fase 3 — Assentos, mapas e inventário avançado

**Status (2026-08-03): essencialmente fechada** — melhor assento disponível, assentos contíguos, acessibilidade por assento na loja pública, virada automática de lote e bloqueio administrativo de assento já entregues (ver seção 3.1). Falta mapa interativo mais rico, lista de espera e cotas por canal (adiado).

Objetivo: sair de evento simples e ir para evento com controle fino de ocupação.

Entregas:

- mapa interativo de assentos;
- melhor assento disponível;
- assentos contíguos;
- acessibilidade por assento;
- virada automática de lote;
- inventário compartilhado e cotas por canal;
- reservas administrativas e bloqueios operacionais.

Critério de saída:

- o produto suporta teatro, cinema, arena setorizada e mesas/camarotes com coerência operacional.

### Fase 4 — Pós-compra competitivo

**Status (2026-08-03): essencialmente fechada** — titularidade/transferência de ingresso e cortesias/convidados/convites (guest list + cupom 100%) já entregues (ver seção 3.1). Falta wallet pass (adiado, sem credenciais), reemissão assistida e revenda oficial.

Objetivo: diminuir atrito do comprador e reduzir dependência de atendimento manual.

Entregas:

- wallet pass;
- titularidade e transferência;
- reemissão assistida;
- central do comprador ampliada;
- autosserviço de cancelamento elegível;
- cupons, cortesias, convidados e convites bem estruturados;
- políticas versionadas por compra.

Critério de saída:

- comprador consegue administrar seu ingresso sem acionar suporte na maioria dos casos.

### Fase 5 — Financeiro, repasses e fiscal

**Status (2026-08-04): iniciada (kickoff + primeira fatia técnica já expandida para operação de exceções).** A decisão de produto agora é:

- a taxa da plataforma será um **valor fixo em BRL configurado pelo administrador do sistema**, igual para todas as empresas, podendo ser `R$ 0,00` ou mais;
- o modelo principal aprovado para a Fase 5 é **PagBank Split com a plataforma como recebedor primário e o organizador como recebedor secundário**;
- a **taxa fixa global** será retida no próprio split;
- a parcela do organizador começará em **custódia**;
- o **repasse padrão será D+1 após o fim do evento**;
- **não haverá reserva extra no primeiro marco**, além da custódia.

Objetivo: fechar o ciclo financeiro do organizador.

Entregas:

- recebíveis;
- repasses e agenda de liquidação;
- reservas de risco;
- comissões;
- conciliação avançada;
- ledger/livro razão simplificado;
- fechamento financeiro por evento;
- emissão fiscal e integração ERP em escopo viável.

Atualização em **4 de agosto de 2026**:

- configuração global da taxa fixa já implementada;
- `receivables`, `settlements` e baixa com custódia PagBank já implementados no primeiro nível operacional;
- exceções de `refund`, `chargeback` e `fraud review` já geram `settlement_adjustments` e `ledger_entries`;
- resolução operacional de `pending_recovery` e `pending_review` já entrou em implementação;
- ajustes manuais auditáveis já entraram em implementação;
- reconciliação estrutural inicial do financeiro já entrou em implementação;
- fechamento financeiro inicial por evento e borderô CSV inicial já entraram em implementação em 4 de agosto de 2026;
- painel financeiro tenant-scoped, lista de recebíveis e lista de repasses já entraram em implementação em 4 de agosto de 2026;
- superfície administrativa global inicial do financeiro também já entrou em implementação em 4 de agosto de 2026;
- UI operacional inicial do financeiro também já entrou em implementação em 4 de agosto de 2026;
- principal lacuna restante da Fase 5: amadurecimento final da reconciliação/exportação e refinamento visual/fluxos restantes.

Critério de saída:

- o organizador entende claramente receita, taxas, saldo, repasse e pendências.

### Fase 6 — Growth, afiliados e CRM

**Status (2026-08-03): não iniciada.**

Objetivo: tornar a plataforma mais competitiva comercialmente.

Entregas:

- afiliados/promotores;
- links e códigos rastreáveis;
- atribuição e comissão;
- CRM do comprador;
- segmentação e audiências;
- automações transacionais e de recompra;
- pixels, UTM e campanhas.

Critério de saída:

- o organizador consegue vender melhor e medir melhor sem depender de ferramentas externas em tudo.

### Fase 7 — Risco, antifraude e alta demanda

**Status (2026-08-03): não iniciada.**

Objetivo: preparar o produto para vendas críticas.

Entregas:

- motor de risco;
- proteção anti-bot;
- fila virtual;
- regras adaptativas;
- observabilidade operacional de venda crítica;
- testes de carga e runbooks;
- contingência de integrações e plano de freeze.

Critério de saída:

- o sistema deixa de ser “funcional” e passa a ser “resiliente para evento grande”.

### Fase 8 — Plataforma avançada e diferenciação

**Status (2026-08-03): não iniciada.**

Objetivo: abrir as frentes de maior diferencial contra concorrentes.

Entregas:

- revenda oficial verificada;
- eventos online/híbridos;
- credenciamento corporativo;
- APIs públicas maduras;
- sandbox e portal do desenvolvedor;
- integrações nativas;
- BI e data warehouse;
- internacionalização e white-label enterprise.

Critério de saída:

- PegaTicket passa a competir como plataforma completa, não só como emissor.

## 8. Priorização executiva recomendada

**Nota (2026-08-03): como as Fases 0-4 estão essencialmente fechadas, a prioridade máxima real hoje é iniciar o desenho de produto da Fase 5 (financeiro/repasses) e amadurecer as lacunas finas remanescentes de Fase 2/3/4 (portarias/zonas, lista de espera, wallet pass) — a priorização abaixo reflete o plano original e permanece válida como ordem de fundo.**

### Prioridade máxima agora

- Fase 0
- Fase 1
- Fase 2

Essas fases fecham o núcleo real do produto.

### Prioridade alta na sequência

- Fase 3
- Fase 4
- Fase 5

Essas fases tornam o produto comercializável com muito mais força.

### Prioridade posterior

- Fase 6
- Fase 7
- Fase 8

Essas fases elevam competitividade e escala.

## 9. Backlog de remoção e alinhamento documental

Antes de expandir features, vale abrir um épico só de alinhamento:

1. Atualizar `README.md`.
2. Criar um “mapa do produto atual” canônico em `docs/`.
3. Arquivar roadmaps de migração e memórias de delivery fora do fluxo principal.
4. Revisar `.claude/memory/project-summary.md`.
5. Revisar documentação comercial e de arquitetura para vocabulário consistente de ticketing.

## 10. Conclusão executiva

O projeto **não está no zero**. A base transacional é boa e a arquitetura já tem bastante material reaproveitável.

**Atualizado 2026-08-03**: as Fases 0 a 4 estão essencialmente fechadas (núcleo transacional, ticketing, bilheteria presencial, assentos/inventário e pós-compra). A especificação completa ainda descreve uma plataforma significativamente maior do que o produto entregue hoje, mas o gap relevante agora está concentrado em Fase 5 em diante — financeiro/repasses (agora com modelo aprovado de split custodial), CRM/growth, antifraude/alta demanda e diferenciação de plataforma.

A recomendação é:

1. **sanear contexto e escopo** (documentação/README/memória, ainda pendente);
2. **fechar o núcleo operacional de ticketing** — feito;
3. **concluir bilheteria/acesso/assentos** — feito, com lacunas finas (portarias/zonas, lista de espera);
4. **entrar em financeiro, CRM e antifraude só depois do núcleo estar firme** — núcleo está firme; financeiro (Fase 5) já teve o modelo principal aprovado e entra agora em implementação.

Se seguirmos essa ordem, o roadmap sai de “documento aspiracional” e vira um plano executável, com risco controlado e evolução coerente do produto.
