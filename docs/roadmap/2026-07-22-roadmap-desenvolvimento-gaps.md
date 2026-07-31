# Roadmap de desenvolvimento — gaps de mercado (Maskats)

Reorganiza `docs/roadmap/2026-07-22-gap-analysis-dores-mercado.md` em ordem de execução: primeiro tudo que dá pra construir só com código nosso (sem depender de conta/serviço/API de terceiro), depois tudo que exige conectar com um terceiro (PSP, SEFAZ, marketplace, Meta/WhatsApp, etc.). Dentro de cada bloco, ordenado do menor pro maior esforço.

Convenção de esforço: **P** = dias · **M** = 1–2 semanas · **G** = 3+ semanas · **GG** = mês(es).

---

## FASE A — Só código nosso (sem terceiro)

Nada aqui depende de contratar serviço externo, criar conta em API paga, ou negociação comercial. Pode entrar em produção assim que o time de dev priorizar.

### A1. Ondas rápidas (P/M — dias a 1 semana cada)

1. **Endpoint `/health`** — checagem de banco/fila/storage, base pra qualquer monitoramento (interno ou externo) depois. *(dor #1)*
2. **Botão "repetir pedido"** no Portal do cliente final — UI sobre histórico de pedido que já existe. *(dor #12)*
3. **Botão "exportar meus dados"** — generaliza a exportação CSV/PDF que já existe por tela pra um pacote único por tenant. *(dor #19)*
4. **Relatório "resultado por canal" + drill-down até o pedido** — `orders.origin` já existe e é filtrável, é só tela nova. *(dor #7)*
5. **Limite percentual de desconto por perfil** — campo novo na permissão já existente (`TenantRolePermission`), sem redesenho. *(dor #14)*
6. **Tela de release notes versionada** — reaproveita o padrão de `legal_documents` (documento versionado), sem aceite obrigatório. *(dor #20)*
7. **Busca universal (`Ctrl+K`)** — UI sobre rotas/ações que já existem no `web/`. *(dor #4)*
8. **Página de preço público + simulador** no `site/` — dado já existe em `plan_prices`, é só expor. *(dor #9)*
9. **Indicador de conexão no frontend** (banner "sem conexão", bloqueio de ação que depende de servidor) — não resolve offline de verdade, mas dá visibilidade imediata ao operador. *(dor #1)*

### A2. Importação e onboarding (M — 1-2 semanas)

10. **Importação de produto por planilha (CSV)** — parser + preview antes de confirmar. Maior retorno/esforço de toda a lista de onboarding. *(dor #15)*
11. **Checklist de implantação nativo** (produto cadastrado? cliente cadastrado? loja configurada? primeira venda?) — contadores simples sobre entidades já existentes. *(dores #4/#15)*

### A3. Financeiro e estoque (M — 1-2 semanas cada)

12. **Tela de conciliação financeira no frontend** — dado (`payments`/`refunds`/`webhook_events`) já existe no backend, falta só a UI. Vale construir mesmo antes de ligar um PSP real, porque valida a UX com dado manual. *(dor #8)*
13. **CMV simples por produto** — custo médio de compra (já registrado na entrada de estoque) vs. preço de venda. Não depende de ficha técnica. *(dores #6/#7)*
14. **Telemetria de abandono de carrinho** no checkout da loja online — evento client-side + contagem. *(dor #12)*

### A4. Operação e permissão (M — 1-2 semanas cada)

15. **PIN individual por operador** no PDV/Balcão — login rápido sobre sessão de staff já aberta, já desenhado na documentação do PDV, falta construir. *(dor #14)*
16. **Ações administrativas rápidas no PWA** (bloquear produto, aprovar cancelamento) — tela/rota nova dentro do `web/` já mobile-first, reaproveita permissão/auditoria existentes. *(dor #18)*
17. **Central de chamados nativa** com botão "diagnóstico" (anexa plano, últimas ações de auditoria, status de fila/cron, versão) — reaproveita o padrão de dados já usado no módulo do contador. *(dor #2)*

### A5. Crescimento sobre dado já existente (M/G)

18. **Régua simples de reativação de cliente** (ex.: sem pedido há N dias → cupom automático + push nativo, sem precisar de e-mail/WhatsApp de terceiro) — orquestração nova sobre cupom+cliente+categoria já existentes. *(dor #10)*
19. **Feature flag por tenant individual** (hoje é por plano, não por tenant dentro do mesmo plano) — base pra liberação gradual de qualquer item futuro. *(dor #17/#20)*

### A6. Arquitetura maior, ainda sem terceiro (G/GG)

20. **API pública + webhooks de saída** — o padrão Event/Listener que já existe internamente (toda mutação de domínio dispara Event) é a base natural; só falta um listener genérico que publica numa fila e chama a URL cadastrada pelo tenant. Não decidir prazo até haver demanda real de integração madura. *(dor #17)*
21. **Hierarquia Grupo/Rede acima de `Tenant`** (multi-CNPJ sob uma marca, visão consolidada) — mudança estrutural grande, só priorizar com demanda confirmada; hoje "múltiplos locais dentro de 1 tenant" já cobre rede pequena do mesmo CNPJ. *(dor #16)*
22. **Offline-first do PDV/Balcão** (IndexedDB/Dexie + Service Worker + fallback de polling) — maior risco técnico do roadmap operacional inteiro (iOS não suporta Background Sync de forma confiável). Só código nosso, mas é o item mais arriscado tecnicamente de toda a Fase A — não subestimar o prazo. *(dor #1)*

---

## FASE B — Depende de conectar com terceiro

Cada item aqui exige conta/API/contrato com um fornecedor externo, decisão comercial do dono, e normalmente custo recorrente ou por transação. A arquitetura interna já foi desenhada pra plugar a maioria desses adapters sem redesenho (`PaymentProviderInterface`, `FiscalProviderInterface`), então o esforço de código tende a ser menor que o esforço de decisão/homologação.

Ordem sugerida por alavancagem (o que destrava mais valor primeiro):

1. **PSP real (Pix/cartão)** — Mercado Pago/Asaas/Efí. Maior alavancagem de toda a Fase B: uma vez plugado, tanto a cobrança de assinatura do plano quanto o pagamento de pedido passam a funcionar de verdade, porque a camada de abstração (`PaymentProviderInterface`, webhook com idempotência) já está pronta e testada. **[decisão do dono]** qual PSP.
2. **Observabilidade externa** — Sentry (free tier) + UptimeRobot (free) monitorando o `/health` da Fase A1. Não é caro nem complexo, mas ainda é conta em serviço de terceiro — entra aqui por definição, mesmo sendo baixo esforço/baixo custo.
3. **Backup em storage externo** (rclone/bucket tipo S3/Backblaze) — o `backup:database` nativo já roda, só falta o destino fora do servidor principal. Risco real de infraestrutura hoje, não feature comercial — priorizar cedo dentro desta fase, independente de venda.
4. **Emissão fiscal real (NF-e/NFC-e/NFS-e)** — maior risco técnico e legal de todo o roadmap. Caminho recomendado: começar pela NFS-e da própria Maskats (baixo volume, justifica serviço pago por nota tipo Focus NFe/PlugNotas/NFe.io) antes de NF-e/NFC-e dos tenants. **[decisão do dono]** serviço pago vs. biblioteca nativa (`sped-nfe`) — trade-off é custo por nota vs. risco de rodar certificado A1 em hospedagem compartilhada (validar se a Hostinger suporta antes de decidir).
5. **WhatsApp Business API oficial (Meta)** — custo por conversa + homologação. Caminho intermediário de custo zero enquanto não decide: manter o link de WhatsApp levando direto pra loja online já existente, em vez de pedido em texto solto.
6. **Campanha multicanal / CRM completo** (e-mail em massa, WhatsApp de reengajamento) — depende de um provedor de envio de e-mail (SES/Mailgun/Resend) e, se for além do que a régua nativa da Fase A5 cobre, da API do WhatsApp (item 5 acima).
7. **Integração de marketplace** (iFood/Rappi) — cada canal é um projeto próprio (API, auth, webhook próprios). Não construir sem demanda confirmada do segmento certo (bares/restaurantes futuros, não o atacado/distribuidora de hoje). Arquitetura recomendada quando decidir: `MarketplaceProviderInterface` (mesmo padrão de `PaymentProviderInterface`) + Centro de Integrações nativo, reaproveitando `orders.origin` já existente (só adicionar `ifood`/`rappi` como novos valores).
8. **App mobile nativo** — só quando o PWA deixar de ser suficiente. Envolve conta de desenvolvedor Apple/Google (revisão de terceiro) e, pra push confiável, FCM/APNs.
9. **Sair de hospedagem compartilhada** — não é bem "integração", mas é dependência de infraestrutura de terceiro (provedor de VPS/cloud) que pode virar necessária se qualquer item acima (certificado A1 fiscal, fila real pra offline) esbarrar em limitação confirmada da Hostinger.

---

## Como usar este roadmap

- Fase A não tem bloqueio de decisão comercial — pode virar backlog de sprint já.
- Fase B precisa de 1 decisão do dono por item antes de qualquer código: qual fornecedor, qual orçamento, qual prazo de homologação — nenhum desses itens deve entrar no discurso de venda como "pronto" ou "em breve" até a decisão comercial acontecer e o adapter ser testado de ponta a ponta.
- Dentro da Fase B, o PSP (item 1) é o único que também destrava discurso comercial da Fase A (cobrança de assinatura, item A1.8) — priorizar ele primeiro se houver orçamento pra só 1 integração agora.
