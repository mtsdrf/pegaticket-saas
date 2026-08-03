# PegaTicket — Mapeamento Global Atual e Roadmap de Desenvolvimento

Data de referência: **2 de agosto de 2026**

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

Estado validado nesta leitura:

- backend com **249 rotas** ativas;
- backend com **546 testes** passando na execução compacta atual;
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
- **Vendas**: criação manual, vendas da loja, parcelas, cancelamento, refund manual estruturado, timeline de workflow.
- **Pagamentos base**: Mercado Pago, Pix, webhooks, reconciliação e camada de payment issues.
- **Tickets**: emissão, listagem, QR/token, check-in básico e histórico de check-in.
- **Portal do comprador**: login OTP, perfil, favoritos, vouchers, minhas vendas.
- **Analytics inicial**: overview, produtos/adicionais, locais, sazonalidade, clientes, atrasos.
- **Financeiro inicial**: reconciliação e visão administrativa de problemas de pagamento.
- **Administração global**: usuários, grupos, funcionalidades, planos, tenants, auditoria.
- **Assinatura SaaS**: planos, subscription, invoice, cobrança recorrente e telas administrativas/operacionais.
- **Suporte básico**: `help requests` e tela de tickets de suporte.

### 3.2 Blocos existentes mas parciais

Estão implementados parcialmente, porém ainda abaixo da especificação:

- **Identidade**: existe cadastro/login/recuperação, mas faltam passkeys, MFA robusto, gestão de dispositivos e KYC/KYB real.
- **Organizações e permissões**: existe base forte de RBAC, mas faltam aprovações em múltiplas etapas, segregação de funções madura e escopo fino por evento/portaria.
- **Locais e mapa**: venues, seats e map versions existem, mas ainda não entregam toda a experiência de mapa interativo e operação de capacidade/portaria exigida.
- **Eventos**: CRUD existe, mas fluxo editorial, aprovação, publicação programada, adiamento e operação documental ainda estão incompletos.
- **Inventário**: ticket types, batches e holds existem, mas faltam virada automática de lote, lista de espera, melhor assento, assentos contíguos, combos e upgrade real.
- **Checkout**: existe fluxo público consistente, mas faltam guest checkout maduro, formulários por ingresso, split de pagamento, retentativas completas e jornada de alta demanda.
- **Pagamentos**: Pix e integração real existem, mas faltam múltiplos gateways ativos, roteamento inteligente, antifraude e split/payout financeiro real.
- **Vendas/Vendas**: o domínio base existe, mas exportações, documentos operacionais, importação e busca operacional avançada ainda estão incompletos.
- **Tickets pós-compra**: emissão e check-in existem, porém faltam wallet passes, transferência, titularidade, revenda oficial e QR rotativo.
- **Promoções**: cupom existe, mas links promocionais, cortesias estruturadas, listas de convidados e campanhas condicionais ainda não.
- **Comunicação**: já há eventos e alguma mensageria operacional, mas falta hub transacional completo com templates, fallback e tracking unificado.
- **Check-in**: base funcional entregue, mas offline forte, sincronização entre dispositivos, reentrada, dispositivos e controle por zona ainda precisam maturidade.
- **Financeiro/Fiscal**: existem refund, conciliação e parte da fundação fiscal, mas falta o núcleo de repasses, recebíveis, ledger, borderô e ERP.
- **API e integrações**: API versionada existe, assim como webhooks internos de pagamento, mas falta a superfície pública madura para terceiros.

## 4. Delta contra a especificação

### 4.1 Cobertura forte hoje

Estas áreas da especificação já têm fundação pronta suficiente para continuar evoluindo sem recomeçar:

- Seções **4, 6, 8, 9, 11, 12, 13, 14, 19, 25, 29, 31, 36, 37, 42 e 43** em nível básico a intermediário.

Resumo:

- multi-tenant;
- permissões;
- eventos;
- inventário base;
- checkout base;
- pagamentos base;
- vendas/vendas;
- tickets;
- check-in;
- suporte;
- analytics inicial;
- administração global;
- LGPD, segurança, CI/CD e testes iniciais.

### 4.2 Cobertura parcial relevante

Estas áreas já começaram, mas ainda não cumprem o nível esperado da especificação:

- **Seção 5**: identidade;
- **Seção 7**: locais, mapa e capacidade;
- **Seção 10**: marketplace e descoberta;
- **Seção 15**: promoções e cortesias;
- **Seção 16**: afiliados e canais;
- **Seção 17**: CRM e automação;
- **Seção 18**: comunicação transacional;
- **Seção 20**: bilheteria/POS;
- **Seções 26, 27 e 28**: cancelamentos, financeiro profundo e fiscal;
- **Seção 32**: API pública e ecossistema de integrações;
- **Seções 38 a 41**: resiliência, observabilidade, concorrência e alta demanda.

### 4.3 Grandes blocos ainda ausentes

Os seguintes grupos estão essencialmente **não implementados** ou só aparecem como intenção futura:

- **Credenciamento corporativo completo** (seção 21).
- **Eventos online e híbridos** (seção 22).
- **Cashless/consumo interno** (seção 24).
- **Revenda oficial, cadeia de custódia e transferência segura completas** (seção 14 avançada).
- **Afiliados/comissionamento de verdade** (seção 16).
- **CRM, campanhas, automações e remarketing reais** (seção 17).
- **Fila virtual enterprise e anti-bot** (seção 30 e seção 44).
- **Múltiplos gateways com roteamento e redundância operacional** (seção 12 avançada).
- **Repasses, ledger, recebíveis e previsão de caixa de verdade** (seção 27).
- **Documentos fiscais e integração ERP completas** (seção 28).
- **Marketplace de integrações, portal do desenvolvedor e sandbox** (seção 32).
- **Migração/importação estruturada de dados legados** (seção 33).
- **Internacionalização completa** (seção 34).
- **BI / data warehouse / recomendações preditivas** (seção 29 avançada).

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
- referências a **PDV/Balcão/comandas/restaurante** se não forem reaproveitadas como bilheteria presencial;
- resíduos de **estoque físico/logística de delivery**;
- materiais que descrevem o produto como SaaS de comércio geral em vez de ticketing.

### 5.3 Blocos que devem ser congelados, não expandidos

- `site/` institucional, se a decisão continuar sendo operar sem frente pública separada;
- qualquer camada antiga ligada a marketplace de delivery;
- módulos de CRM genérico que não estejam ligados diretamente a comprador, evento e recompra.

## 6. Leitura estratégica: em que fase estamos de verdade

Hoje o PegaTicket está entre a **Fase 1 funcional** e a **pré-Fase 2** da especificação.

Em termos práticos:

- o **núcleo transacional básico** já existe;
- o **núcleo operacional de ticketing** já começou;
- o sistema **ainda não é competitivo nacionalmente** contra plataformas maduras;
- o produto **ainda não está pronto para alta demanda séria**;
- o discurso comercial e documental **ainda precisa ser consolidado** para o domínio final.

Diagnóstico objetivo:

- **Pronto para continuar construção do produto**: sim.
- **Pronto para ser tratado como plataforma enterprise de eventos**: ainda não.
- **Pronto para vendas críticas de grande porte**: não.

## 7. Roadmap recomendado

### Fase 0 — Saneamento e alinhamento do produto

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

Critério de saída:

- o organizador entende claramente receita, taxas, saldo, repasse e pendências.

### Fase 6 — Growth, afiliados e CRM

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

Mas a especificação completa descreve uma plataforma significativamente maior do que o produto entregue hoje. O caminho correto não é sair implementando tudo ao mesmo tempo.

A recomendação é:

1. **sanear contexto e escopo**;
2. **fechar o núcleo operacional de ticketing**;
3. **concluir bilheteria/acesso/assentos**;
4. **entrar em financeiro, CRM e antifraude só depois do núcleo estar firme**.

Se seguirmos essa ordem, o roadmap sai de “documento aspiracional” e vira um plano executável, com risco controlado e evolução coerente do produto.
