# Runbook — Testes de carga (como rodar, não executado nesta rodada)

Este documento **não** contém resultado de teste de carga real — este ambiente não tem acesso a uma infraestrutura de staging isolada para gerar carga sem afetar dados reais. É o guia de **como** alguém com acesso a um ambiente de staging deve rodar, como próximo passo depois da Fase 7.

## Ferramenta sugerida

[k6](https://k6.io/) — CLI simples, scripts em JavaScript, boa aderência a APIs REST como as deste projeto (`api/routes/api.php`). Alternativa equivalente: [Artillery](https://www.artillery.io/) (Node.js, YAML de cenário) se a equipe preferir um ecossistema JS mais próximo do `web/`.

Não instalar/configurar nenhuma delas neste ambiente agora — depende de decidir onde a carga vai rodar (nunca contra produção, nunca contra o mesmo banco de dados usado pelos testes automatizados/dev, ver `project_shared_db_is_intentional` na memória do projeto — mesmo aviso vale em dobro para carga sintética).

## Cenários mínimos a cobrir

### 1. Criação de hold concorrente
Alvo: `POST /loja/{slug}/eventos/{eventSlug}/holds`.

- Simular N usuários virtuais tentando reservar o mesmo `ticket_type`/lote simultaneamente, com estoque menor que N.
- Validar: nenhuma reserva além da disponibilidade real (`quantity_available - quantity_sold`), sem overselling — a lógica de `lockForUpdate()` em `StorefrontHoldService` é o que deveria garantir isso sob concorrência real de banco (SQLite em memória dos testes Feature não exercita lock de verdade da mesma forma que MySQL sob carga).
- Métrica: taxa de erro esperada (422 `insufficient_availability`) vs. taxa de sucesso, tempo de resposta p95/p99.

### 2. Checkout concorrente
Alvo: `POST /loja/{slug}/checkout` e `POST /bilheteria/{slug}/checkout`.

- Simular N compradores com hold válido finalizando checkout ao mesmo tempo.
- Validar: nenhuma venda duplicada por hold, tempo de resposta sob carga, comportamento do banco (MySQL) sob lock concorrente em `sales`/`sale_items`/`inventory_holds`.
- Cruzar com `checkout.error_rate_percent` do `GET /reports/operation-snapshot` durante o teste — deveria refletir a taxa real observada.

### 3. Fila virtual sob carga
Alvo: `GET /loja/{slug}/eventos/{eventSlug}/fila` (polling) + `storefront:admit-virtual-queue-entries` (comando agendado).

- Simular milhares de `session_token` diferentes entrando na fila de um evento com `high_demand_mode=true` em um curto intervalo.
- Validar: `position`/`waiting_ahead` corretos sob concorrência de escrita (a lógica de posição em `VirtualQueueService::enterOrStatus` usa `MAX(position)+1` dentro de uma transação com `lockForUpdate()` na linha existente, mas **não** trava a tabela inteira contra inserções concorrentes de tokens diferentes — sob volume real isso pode gerar contenção ou, em cenário extremo, corrida na atribuição de posição; validar com carga real antes de confiar cegamente no número em produção).
- Validar: `storefront:admit-virtual-queue-entries` rodando a cada minuto consegue processar o volume de entradas `waiting` dentro do próprio minuto sem acumular atraso.

## O que registrar depois de rodar

- p50/p95/p99 de cada endpoint sob carga.
- Taxa de erro por cenário.
- Se apareceu overselling ou posição de fila incorreta sob concorrência (bug real a corrigir, não só métrica).
- Se o `virtual_queue_admission_batch_size` default (50) e a janela de admissão (`VirtualQueueService::ADMISSION_WINDOW_MINUTES`, 20min) se mostraram adequados ou precisam de ajuste — ambos são defaults técnicos não validados, e teste de carga real é a forma correta de validá-los.

## Fora de escopo desta rodada

- Execução real de teste de carga.
- Provisionamento de ambiente de staging dedicado.
- Regras adaptativas de throttle dinâmico (rate limit que se ajusta à carga observada em tempo real) — depende de decisão de arquitetura própria, fora de escopo de código nesta rodada.
