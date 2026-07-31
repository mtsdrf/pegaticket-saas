# Roadmap de descoberta e implementação — integração iFood

Data de referência: **25 de julho de 2026**

Este documento inicia oficialmente a frente de integração com o **iFood**, usando como base:

- o agente [delivery-integration-specialist.md](/home/mtsdrf/workspace/maskats-saas/.claude/agents/delivery-integration-specialist.md);
- o estado real atual do projeto Maskats;
- a documentação oficial pública do iFood Developer localizada em `developer.ifood.com.br`;
- e os pontos que ainda dependem de **portal autenticado, credenciais homologadas e contrato**.

## Objetivo desta fase

Começar a integração do iFood sem inventar contrato técnico. O foco inicial não é "ligar tudo de uma vez", e sim:

1. levantar o contrato oficial disponível;
2. preparar a arquitetura interna;
3. criar a fundação persistente e operacional;
4. deixar o projeto pronto para receber a primeira loja em homologação;
5. só então implementar pedido, ações e catálogo.

---

## 1. Estado atual do Maskats

### O que já existe e pode ser reaproveitado

- `orders.origin` já existe e hoje suporta canais internos (`staff`, `storefront`, `pdv`, `counter`).
- domínio de pedidos, itens, pagamento, entrega, cancelamento e operação já existe.
- existe fila, jobs, auditoria e padrões de service/repository/DTO.
- existe gestão de `API keys` e `webhook subscriptions` para integrações externas genéricas.
- existe catálogo próprio, loja pública, horários, taxa de entrega, cupom, cashback e operação de loja.
- existe trilha de auditoria e permissões por tenant.
- existe base de UI para uma futura **Central de Integrações**.

### O que não existe hoje

- nenhum `MarketplaceProviderInterface`;
- nenhum adapter de marketplace;
- nenhum conector iFood;
- nenhuma tabela de eventos de marketplace;
- nenhuma tabela de ações de marketplace;
- nenhuma tabela de merchants/lojas externas;
- nenhuma máquina de estados externa por parceiro;
- nenhuma conciliação de pedido externo;
- nenhum fluxo de homologação operacional de marketplace.

Conclusão: o Maskats tem **fundação boa**, mas a integração iFood começa praticamente do zero na camada de marketplace.

---

## 2. Fatos confirmados pela documentação oficial pública do iFood

Os itens abaixo foram confirmados a partir das páginas públicas do portal oficial `developer.ifood.com.br` localizadas em **25 de julho de 2026**.

### Fontes oficiais localizadas

- Referências gerais: https://developer.ifood.com.br/en-US/docs/references
- Event polling overview: https://developer.ifood.com.br/en-US/docs/guides/modules/events/polling-overview
- Order events: https://developer.ifood.com.br/en-US/docs/guides/modules/order/events
- Webhook overview: https://developer.ifood.com.br/en-US/docs/guides/modules/events/webhook-overview
- Merchant workflow: https://developer.ifood.com.br/docs/guides/modules/merchant/workflow
- Merchant operations: https://developer.ifood.com.br/docs/guides/modules/merchant/operations
- Generate test order: https://developer.ifood.com.br/en-US/docs/getting-started/first-steps/generate-test-order
- Presence: https://developer.ifood.com.br/en-US/docs/guides/modules/events/presence/

### Fatos confirmados

- o iFood possui módulo de **Events**;
- o iFood possui **polling de eventos**;
- o iFood possui **webhook**;
- o iFood possui **eventos de pedido**;
- o iFood possui conceito de **merchant/workflow**;
- o iFood possui documentação para **presence**;
- o iFood possui fluxo de **generate test order** no portal;
- a Order API é apresentada no portal como separada em três núcleos:
  - Events
  - Details
  - Actions

### Inferências seguras a partir desses fatos

- a integração do iFood no Maskats deve nascer com suporte estrutural para:
  - recebimento de eventos;
  - consulta de detalhes do pedido;
  - execução de ações no pedido;
  - vínculo de merchants;
  - testes/homologação.

Essas inferências são derivadas da organização do portal oficial e não de contrato privado.

---

## 3. Pontos ainda não confirmados e que dependem do portal autenticado

Os itens abaixo **não devem ser inventados** nesta etapa:

- endpoint final exato de autenticação;
- tipo de autenticação efetivamente habilitado para a aplicação da Maskats;
- detalhes de escopos/liberações por app;
- formato exato da assinatura do webhook;
- headers obrigatórios do webhook;
- política oficial de retry do webhook;
- contrato completo do payload de polling;
- contrato completo dos eventos por versão;
- rate limits exatos;
- timeout e SLA recomendados;
- critérios formais de homologação exigidos para a aplicação da Maskats;
- recursos de catálogo efetivamente liberados;
- recursos de disponibilidade/presença efetivamente liberados;
- recursos logísticos efetivamente liberados;
- regras de cancelamento por tipo de loja;
- recursos financeiros/repasses efetivamente expostos ao integrador.

Regra ativa para esta frente:

> Toda decisão de implementação que dependa de um desses pontos deve aguardar confirmação no portal autenticado, contrato ou credenciais de homologação do iFood.

---

## 4. Estratégia recomendada para o iFood no Maskats

### Abordagem de rollout

1. **Read-only técnico**
   - conectar merchant;
   - receber evento;
   - persistir payload;
   - normalizar pedido;
   - não executar ação externa ainda.

2. **Shadow mode operacional**
   - importar pedido do iFood;
   - comparar com fluxo interno;
   - medir duplicidade, atraso e consistência;
   - ainda sem aceitar/rejeitar pelo Maskats.

3. **Pilot controlado**
   - 1 tenant;
   - 1 merchant;
   - 1 loja;
   - horário controlado;
   - monitoramento em tempo real.

4. **Gradual rollout**
   - ampliar por loja;
   - ampliar por tenant;
   - ampliar por capacidade: pedidos, depois ações, depois catálogo, depois presença.

### Sequência técnica recomendada

1. eventos;
2. pedido canônico;
3. ações de pedido;
4. merchants e health;
5. catálogo;
6. disponibilidade/presença;
7. conciliação e operação avançada.

Não recomendo começar por catálogo antes do fluxo de pedidos.

---

## 5. Arquitetura alvo para o iFood

### Camada de domínio e integração

Criar um módulo dedicado em Laravel, mantendo o domínio central desacoplado:

```text
api/app/
  Domain/
    Marketplace/
  Application/
    Marketplace/
  Infrastructure/
    Marketplace/
      Ifood/
  Http/
    Controllers/
      Marketplace/
      Webhooks/
  Jobs/
    Marketplace/
```

### Contrato base

Criar uma interface interna no mesmo espírito de `PaymentProviderInterface`:

```text
MarketplaceProviderInterface
```

Primeira implementação:

```text
IfoodMarketplaceProvider
```

### Capacidades

O provider do iFood deve expor capacidades, por exemplo:

- suporta webhook?
- suporta polling?
- suporta catálogo?
- suporta disponibilidade?
- suporta ações de pedido?
- suporta logística?
- suporta conciliação?

Assim o Maskats não acopla o fluxo inteiro a uma suposição fixa.

---

## 6. Modelo de dados inicial recomendado

### Tabelas mínimas da Fase 1

1. `marketplace_integrations`
   - tenant_id
   - platform (`ifood`)
   - environment
   - status
   - merchant_count
   - last_authenticated_at
   - last_event_received_at
   - last_order_received_at
   - metadata

2. `marketplace_credentials`
   - integration_id
   - credential_type
   - encrypted_payload
   - expires_at
   - last_refresh_at

3. `marketplace_merchants`
   - integration_id
   - tenant_id
   - external_merchant_id
   - external_name
   - status
   - is_active
   - metadata

4. `marketplace_events`
   - tenant_id
   - integration_id
   - merchant_id
   - platform
   - external_event_id
   - external_order_id
   - event_type
   - payload
   - payload_hash
   - received_at
   - processed_at
   - acknowledged_at
   - status
   - attempts
   - error

5. `marketplace_actions`
   - tenant_id
   - integration_id
   - order_id
   - platform
   - action
   - idempotency_key
   - request_payload
   - response_payload
   - external_http_status
   - status
   - attempt_count
   - next_retry_at
   - started_at
   - completed_at
   - error_code
   - error_message
   - correlation_id

6. `marketplace_orders`
   - tenant_id
   - integration_id
   - merchant_id
   - order_id interno
   - platform
   - external_order_id
   - external_display_id
   - external_status
   - internal_status
   - raw_snapshot

### Restrições mínimas

- unique por `platform + integration_id + external_event_id`
- unique por `platform + merchant_id + external_order_id`
- unique por `platform + integration_id + idempotency_key`

---

## 7. Roadmap completo por fase

## Fase 1 — Descoberta e fundação

Objetivo:
- fechar o contrato técnico mínimo e preparar a fundação persistente.

Entregas:
- documento de descoberta do iFood;
- mapa de capacidades confirmadas x pendentes;
- primeiras migrations;
- enums e value objects de marketplace;
- `MarketplaceProviderInterface`;
- `IfoodMarketplaceProvider` stub;
- central interna de status de integração no backend.

Critério de saída:
- projeto consegue representar uma integração iFood sem ainda operar pedido real.

## Fase 2 — Credenciamento e vínculo de merchant

Objetivo:
- conectar a conta/aplicação da Maskats ao iFood de forma segura.

Entregas:
- fluxo de autenticação suportado pelo iFood confirmado no portal;
- armazenamento seguro de credenciais;
- refresh/rotação se aplicável;
- descoberta de merchants;
- vínculo merchant ↔ tenant;
- health check de conexão;
- primeira tela da Central de Integrações.

Critério de saída:
- 1 merchant do iFood vinculado a 1 tenant da Maskats em homologação.

## Fase 3 — Eventos de entrada

Objetivo:
- receber eventos sem perder, duplicar ou confirmar cedo demais.

Entregas:
- endpoint de webhook iFood;
- validação de assinatura oficial, se aplicável;
- polling oficial, se necessário ou recomendado;
- persistência bruta do evento;
- job de processamento;
- retry, dead-letter e locks por pedido/evento;
- dashboard técnico mínimo de eventos.

Critério de saída:
- eventos do iFood entram com idempotência e trilha operacional.

## Fase 4 — Normalização de pedido

Objetivo:
- converter pedido do iFood no pedido canônico do Maskats.

Entregas:
- DTO externo do iFood;
- normalizador iFood → pedido canônico;
- mapeamento de cliente, endereço, itens, adicionais, taxas e pagamento;
- persistência do vínculo `pedido interno ↔ external_order_id`;
- adição de `ifood` ao ecossistema de origem do pedido.

Critério de saída:
- pedido do iFood nasce corretamente dentro do fluxo interno, sem duplicidade.

## Fase 5 — Ações no pedido

Objetivo:
- permitir operação de aceite/rejeição e evolução de status via Maskats.

Entregas:
- aceitar pedido;
- rejeitar pedido;
- marcar preparo;
- marcar pronto;
- despachar, se aplicável;
- log completo de ações;
- idempotency key;
- recuperação de timeout indeterminado.

Critério de saída:
- o operador consegue atuar no pedido do iFood sem acessar outro sistema.

## Fase 6 — Catálogo

Objetivo:
- publicar catálogo da Maskats no iFood com rastreabilidade.

Entregas:
- mappings de categoria/produto/complemento;
- sync full e incremental;
- checksum e diff;
- relatório de sync;
- preview;
- falha por item;
- rollback lógico.

Critério de saída:
- catálogo mínimo consistente no iFood, com reprocessamento seguro.

## Fase 7 — Disponibilidade e presença

Objetivo:
- controlar abertura/pausa/presença da loja no iFood.

Entregas:
- status externo da loja;
- pausa/reabertura;
- presence, se exigida;
- reconciliação entre estado interno e externo;
- alertas de loja silenciosa/offline.

Critério de saída:
- a operação consegue confiar que a loja está online no iFood quando o painel disser que está.

## Fase 8 — Observabilidade e operação

Objetivo:
- permitir sustentação real da integração.

Entregas:
- central operacional do iFood;
- backlog de eventos;
- retry manual;
- dead letters;
- correlação de pedidos;
- health checks;
- alertas;
- runbook de incidente.

Critério de saída:
- suporte consegue diagnosticar falha sem entrar no banco na maioria dos casos.

## Fase 9 — Homologação e rollout

Objetivo:
- homologar oficialmente e liberar com baixo risco.

Entregas:
- matriz de homologação por cenário;
- evidências;
- ambiente de teste estável;
- pilot com 1 tenant;
- rollout gradual;
- rollback definido.

Critério de saída:
- primeira integração iFood oficialmente homologada e operando em produção controlada.

---

## 8. Backlog sugerido da primeira sprint técnica

### Sprint 1 — fundação

1. Criar `MarketplaceProviderInterface`.
2. Criar enums `MarketplacePlatform`, `MarketplaceIntegrationStatus`, `MarketplaceEventStatus`, `MarketplaceActionStatus`.
3. Criar migrations:
   - `marketplace_integrations`
   - `marketplace_credentials`
   - `marketplace_merchants`
   - `marketplace_events`
   - `marketplace_actions`
4. Criar models, repositories e resources mínimos.
5. Criar `IfoodMarketplaceProvider` stub.
6. Criar documentação de campos pendentes por confirmação oficial.
7. Criar testes unitários das entidades e regras de unicidade/idempotência.

### Sprint 2 — integração configurável

1. Criar CRUD/backend da Central de Integrações.
2. Criar tela web inicial de integrações de marketplace.
3. Criar health check interno por integração.
4. Criar estrutura de armazenamento de credenciais seguras.
5. Preparar endpoint de callback e recepção de webhook.

---

## 9. Decisões já tomadas para evitar retrabalho

- o primeiro parceiro será o **iFood**;
- a implementação deve seguir **documentação oficial primeiro**;
- não vamos assumir contrato privado a partir de memória;
- o rollout deve começar por **pedido/evento**, não por catálogo;
- a operação deve nascer com trilha e reprocessamento, não só com endpoint "funcionando".

---

## 10. Decisões ainda pendentes

Estas decisões precisam ser fechadas antes da implementação real do adapter iFood:

1. a aplicação da Maskats já está cadastrada no portal do iFood?
2. já existem credenciais de homologação?
3. já existe merchant/loja de teste disponível?
4. o fluxo oficial para a aplicação será webhook, polling ou ambos?
5. catálogo entra na V1 ou só pedidos e ações?
6. aceite automático será permitido/necessário em algum tenant?
7. a homologação inicial será com uma operação real já definida?

---

## 11. Critério de pronto para a V1 iFood

A V1 iFood só pode ser tratada como pronta quando houver:

- integração autenticada;
- merchant vinculado;
- eventos recebidos com idempotência;
- pedido criado sem duplicidade;
- ações mínimas operacionais funcionando;
- observabilidade mínima ativa;
- testes automatizados;
- homologação formal do parceiro;
- pilot concluído com sucesso.

Até lá, qualquer discurso deve ser:

> integração iFood em implantação controlada / em homologação, e não pronta em produção ampla.

