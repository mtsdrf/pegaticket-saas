# PegaTicket — Mapa Atual do Produto

Data de referência: **3 de agosto de 2026**

## Objetivo

Este documento resume o que o PegaTicket entrega hoje no repositório e quais frentes ainda estão parciais ou pendentes. Ele é a referência curta do produto ativo.

## 1. O que o produto é hoje

O PegaTicket é uma plataforma SaaS multiempresa de ticketing com:

- área autenticada para organizadores e operadores;
- catálogo público de eventos;
- checkout com hold de inventário;
- portal do comprador;
- emissão, transferência e gestão básica de ingressos;
- portaria com validação manual, leitura de QR e fila offline local;
- administração global, assinatura SaaS e analytics inicial.

## 2. Módulos prontos ou bem encaminhados

### Núcleo de plataforma

- multiempresa
- papéis e permissões
- onboarding self-service
- aceite legal e privacidade operacional
- auditoria
- planos e assinatura SaaS

### Catálogo e evento

- categorias de evento
- eventos
- imagens de evento
- venues
- mapa visual básico de venue
- assentos
- alocação automática de melhor assento
- seleção contígua de assentos
- acessibilidade por assento na loja pública
- sessões
- lotes
- tipos de ingresso
- adicionais simples por evento

### Venda e checkout

- catálogo público
- detalhe do evento
- favoritos
- hold de inventário
- checkout público
- rastreio público
- vendas manuais
- vendas online
- fila operacional de vendas online pendentes de aprovação
- caixa operacional (`cash session`)

### Pagamentos

- cobrança Pix
- cobrança por cartão no fluxo de vendas
- integração Mercado Pago para a trilha já existente de assinatura
- integração PagBank para a trilha de venda
- configuração por tenant para PagBank direto na conta da empresa (primeira fatia)
- reconciliação
- webhooks
- log dedicado das transações PagBank
- payment issues

### Ticketing e acesso

- emissão de tickets
- listagem de tickets
- QR/token de validação
- transferência de titularidade
- check-in manual
- leitura de QR na portaria
- histórico de check-in
- fila offline local de sincronização
- bloqueio de uso após estorno

### Comprador

- portal com OTP
- perfil
- favoritos
- vouchers
- minhas vendas
- listagem de ingressos por venda
- solicitação de cancelamento
- avaliação da venda
- pagamento da própria venda no portal

### Operação e suporte

- links individuais de convite/guest list
- listas de convidados
- analytics inicial
- insights de check-in
- reconciliação financeira inicial
- suporte básico
- administração global

## 3. Módulos parciais

- identidade forte e MFA
- workflow editorial e governança de evento
- mapa interativo avançado e capacidade/portaria refinada
- pós-compra avançado
- financeiro profundo
- fiscal e ERP
- API pública madura
- reentrada, zonas e supervisão operacional avançada
- comunicação transacional estruturada
- growth, campanhas e rastreamento comercial

Observação da Fase 5 em 4 de agosto de 2026:

- o desenho financeiro saiu do estado "não iniciado" e entrou em kickoff;
- o modelo principal aprovado passou a ser **PagBank Split com custódia**;
- a plataforma ficará como recebedor primário e o organizador como secundário;
- o repasse padrão aprovado será **D+1 pós-evento**;
- taxa global fixa da plataforma já ganhou configuração base;
- `receivables` e `settlements` locais já começaram a ser gerados automaticamente;
- a liberação externa da custódia e a baixa efetiva do repasse já entraram em implementação;
- a reconciliação pós-liberação também já entrou em implementação;
- o tratamento inicial de exceções por estorno também já entrou em implementação;
- o tratamento inicial de contestações do PSP também já entrou em implementação em 4 de agosto de 2026;
- o workflow operacional de `pending_recovery` e `pending_review` já entrou em implementação em 4 de agosto de 2026;
- os ajustes manuais auditáveis e a reconciliação estrutural inicial também já entraram em implementação em 4 de agosto de 2026;
- o fechamento financeiro inicial por evento e o borderô CSV inicial também já entraram em implementação em 4 de agosto de 2026;
- o painel financeiro tenant-scoped, a lista de recebíveis e a lista de repasses também já entraram em implementação em 4 de agosto de 2026;
- a superfície administrativa global do financeiro também já entrou em implementação em 4 de agosto de 2026;
- a UI operacional inicial do financeiro também já entrou em implementação em 4 de agosto de 2026;
- o próximo passo passa a ser amadurecimento final da reconciliação/exportação e refinamento visual/fluxos restantes.

## 4. Módulos ainda não implementados

- revenda oficial verificada
- wallet pass
- fila virtual
- anti-bot robusto
- afiliados e comissões
- CRM e automações
- credenciamento corporativo
- eventos online e híbridos
- cashless
- BI / warehouse
- portal do desenvolvedor
- sandbox de integrações
- internacionalização completa

## 5. Observações importantes

- Transferência de ingresso **já existe**, mas a cadeia completa de custódia, revenda oficial e regras avançadas de marketplace secundário **ainda não**.
- O editor visual de venue **já existe em nível básico**, e melhor assento/contiguidade já foram entregues; o que continua parcial é o mapa interativo avançado e regras mais ricas de ocupação.
- O check-in já cobre operação básica com QR, contingência local e sincronização operacional por polling; o que ainda precisa evoluir é reentrada, controle por zona e supervisão mais fina.

## 6. O que não deve guiar decisões de produto

Não usar como referência principal:

- documentação antiga de delivery/comércio genérico;
- histórico de iFood/marketplace alimentar;
- resíduos de estoque físico/logística;
- módulos herdados que não convergem com ticketing.

## 7. Fonte de verdade para o roadmap

Os documentos prioritários para evolução do produto são:

- [pegaticket_especificacao_completa.md](/home/mtsdrf/workspace/pegaticket-saas/pegaticket_especificacao_completa.md)
- [docs/roadmap/2026-08-02-pegaticket-global-gap-roadmap.md](/home/mtsdrf/workspace/pegaticket-saas/docs/roadmap/2026-08-02-pegaticket-global-gap-roadmap.md)
