# Maskats — Gap Analysis: dores de mercado (delivery/PDV/ERP) × produto real

> Cruza a pesquisa de mercado do dono (20 categorias de dor, reclamações públicas/reviews/guias do setor) com o estado **real e verificado** do Maskats em 2026-07-22. Fonte factual: `docs/apresentacao/detalhamento-funcionalidades.md`, `.claude/memory/architecture-decisions.md` (Ondas 1/2 do roadmap nativo, PDV Fase 1, Balcão Fases 1+2, auditoria de segurança 2026-07-22) e os 3 roadmaps de 2026-07-20/21. Nenhuma funcionalidade citada como "já existe" foi inferida pelo nome — só o que está confirmado implementado e testado.
>
> Convenção de esforço: **P** = dias · **M** = 1–2 semanas · **G** = 3+ semanas · **GG** = mês(es)/orçamento real. Itens que dependem de contratação paga ou decisão de investimento (PSP, operadora fiscal, servidor dedicado, app nativo) estão marcados **[decisão do dono]** — não foram decididos aqui, só apresentados com trade-off.

---

## 1. Sistema parar no horário de pico

**Já resolve:** rate limiting (`throttle:`) nas rotas sensíveis; isolamento de erro por camada (Request→Controller→Service→Repository); CI roda `composer test` antes de cada deploy (gate de qualidade, não deixa subir quebrado); `idempotency_key` já é padrão em pagamento de pedido/webhook (evita duplicar cobrança em retry).

**Falta:** nenhum indicador de conexão no frontend; nenhuma fila local/offline no PDV nem no Balcão (Balcão Fase 3 — offline — está **no roadmap, não implementada**; PDV também assume internet); sem monitoramento de erro em produção (Sentry/equivalente não instalado); atualização de deploy não tem liberação gradual (é um `rsync` só, tudo de uma vez, sem canário); sem endpoint `/health` nem uptime monitor externo.

**Recomendação:**
- **Baixo esforço/alto impacto (P/M):** endpoint `/health` + UptimeRobot (free) monitorando; Sentry free tier (5k eventos/mês) para erro em produção — sem isso hoje uma falha em pico é invisível até o cliente reclamar.
- **Médio prazo (M):** indicador de conexão no frontend (banner "sem conexão" + bloqueio de ações que exigem servidor, já que quase tudo hoje é `throttle`d e síncrono).
- **Investimento maior (GG):** fila offline real do PDV/Balcão (Fase 3 do roadmap Balcão) — IndexedDB/Dexie + Service Worker + fallback de polling. Já desenhado, ainda não construído; é o item de maior risco técnico do roadmap todo (iOS não suporta Background Sync de forma confiável).

---

## 2. Suporte lento quando a operação está parada

**Já resolve:** nada de suporte-produto hoje — o sistema não tem canal de chamado embutido.

**Falta:** triagem por gravidade P0-P3, diagnóstico automático anexado ao chamado, canal emergencial. Isso é 100% gap — não existe nem esqueleto.

**Recomendação:**
- **Baixo esforço (P/M):** canal simples (e-mail/WhatsApp dedicado) + runbook de incidente interno documentado (já apontado como pendência no roadmap de produção). Não precisa ser produto ainda.
- **Médio prazo (M/G):** central de chamados nativa dentro do painel (reaproveita o padrão de "central de pendências" já desenhado para o módulo do contador — é o mesmo conceito: anexo, prazo, prioridade, status), com botão "diagnóstico" que anexa automaticamente: plano do tenant, últimas ações de auditoria, status de fila/cron, versão do sistema. Isso transforma o mesmo padrão de dados (AuditLog) já existente em valor de suporte sem reconstruir nada do zero.
- **[decisão do dono]** contratar ferramenta de helpdesk pronta (Zendesk/Crisp) vs. construir nativo — trade-off é custo recorrente vs. tempo de desenvolvimento; para poucos tenants no início, WhatsApp + runbook interno já resolve 80% a custo zero.

---

## 3. Integrações que existem no papel mas falham na prática (marketplace)

**Já resolve:** nada — **não existe integração de marketplace nenhuma hoje** (nem iFood, nem Rappi, nem nenhum canal externo). É importante ser honesto aqui: como não existe a promessa, também não existe a frustração de "existe no papel e falha" — o discurso de venda não pode citar isso como resolvido nem prometê-lo como pronto.

**Falta:** tudo. Não há Centro de Integrações, não há adapter de marketplace, não há reconciliação de pedido duplicado/sumido entre canais.

**Recomendação:**
- **Não é baixo esforço em nenhum caso** — cada marketplace tem API própria, autenticação própria, webhook próprio. Não fazer promessa de prazo curto aqui.
- **Arquitetura recomendada quando decidir entrar:** seguir o mesmo padrão já usado em `PaymentProviderInterface`/`FiscalProviderInterface` — um `MarketplaceProviderInterface` com adapter por canal, e uma tela de "Centro de Integrações" nativa (status/erro/reprocessamento) que já reaproveita `orders.origin` (já existe e já é filtrável — `staff`/`storefront`/`pdv`/`counter`; bastaria adicionar `ifood`/`rappi` como novos valores de origem, o painel consolidado do dono já os mostraria automaticamente).
- **[decisão do dono]** qual marketplace priorizar primeiro depende do público-alvo real (atacadista/distribuidora hoje tem pouca aderência a iFood; bares/restaurantes futuros têm muita) — não construir especulativamente antes de confirmar demanda do segmento certo.
- Discurso de venda honesto no curto prazo: "canal próprio forte" (loja online + Portal + cashback) para reduzir a *necessidade* de marketplace, não para fingir que a integração existe.

---

## 4. Sistema completo mas difícil de aprender

**Já resolve:** permissão granular por functionality+action já esconde do menu o que o plano/perfil não libera (reduz ruído visual por si só); planos Prata/Ouro/Diamante já segmentam a complexidade exposta ao tenant conforme o que ele contratou.

**Falta:** modo simplificado por cargo (hoje a tela é a mesma para todos os perfis que têm a permissão, só variando o que aparece no menu); checklist de implantação guiado dentro do produto; busca universal de comando (command palette).

**Recomendação:**
- **Baixo esforço/alto impacto (M):** busca universal (`Cmd+K`/`Ctrl+K`) sobre as rotas e ações já existentes — não precisa de dado novo, é UI sobre o que já existe (React 19 Master + UI UX Master).
- **Médio prazo (M):** checklist de implantação nativo (ver item 15, mesmo gap).
- Não é P0/P1 crítico isoladamente, mas alimenta direto o item 15 (implantação) e o item 18 (app do dono) — priorizar junto.

---

## 5. Módulos não conversam de verdade

**Já resolve:** este é hoje um dos pontos **mais fortes** do Maskats — venda baixa estoque de fato (reserva automática + movimentação com histórico, não "some" registro); cancelamento de pedido pago **não** apaga pagamento, gera `Refund` corretamente (Onda 2A, testado); fechamento de caixa do PDV concilia sangria+suprimento+venda por forma de pagamento com teste dedicado (`PdvTest`, split soma bate/não bate); fechamento de comanda do Balcão idem.

**Falta:** o motor de cálculo automático de imposto sobre pedido ainda não existe (`tax_rules` é só cadastro versionado, sem aplicação automática no pedido); CMV real por produto (ver item 6).

**Recomendação:** este é ponto de venda real, não gap — mas **não prometer** cálculo fiscal automático ainda (isso é gap do item 13). Priorizar deixar essa integração completa e coerente entre estoque/financeiro/caixa **visível no discurso comercial**, é diferencial verificável hoje.

---

## 6. Estoque impreciso

**Já resolve:** estoque nunca fica "negativo sem explicação" — toda movimentação (entrada/saída/ajuste/transferência/bloqueio) tem motivo e histórico completo, reserva automática evita vender o mesmo item duas vezes antes de confirmar; alerta de estoque mínimo configurável.

**Falta:** ficha técnica/receita (baixa de insumo, não do produto vendável) — confirmado como fora do MVP tanto no roadmap do PDV quanto no do Balcão; CMV automático por produto; simulação de margem antes de mudar preço.

**Recomendação:**
- **Médio prazo (M/G):** CMV simples = custo de compra médio por produto (se cadastrado) vs. preço de venda — não depende de ficha técnica, é aritmética sobre dado que majoritariamente já existe (entrada de estoque com valor). Alto impacto em discurso de "lucratividade por produto" citado nas dores 6 e 7.
- **Investimento maior (GG):** ficha técnica completa (produto composto por insumos com baixa proporcional) — só faz sentido priorizar quando o público de bares/restaurantes (Fase 8 do roadmap geral) entrar, é onde a dor é mais aguda; para atacado/distribuidora de bebidas o produto vendável já é a unidade de estoque real na maioria dos casos.

---

## 7. Relatórios bonitos que não ajudam a decidir

**Já resolve:** relatório de pedidos filtrável/exportável, recebíveis (entrou vs. em aberto), analytics avançado no plano Ouro+, dashboard com métricas do dia a dia; `orders.origin` já permite filtrar/comparar por canal (staff/storefront/pdv/counter) — a base para "qual canal é mais lucrativo" **já existe no dado**, falta só a visualização.

**Falta:** detalhamento até a venda original a partir de um relatório agregado (drill-down); relatório específico "margem por canal"; nenhum indício de números divergentes entre relatórios ter sido auditado formalmente (não é gap confirmado, é risco não verificado).

**Recomendação:**
- **Baixo esforço/alto impacto (M):** relatório "resultado por canal" (`origin`) e drill-down do relatório de pedidos até o pedido original — é UI + query sobre dado que já existe, sem mudança de schema.
- **P1 antes de vender relatório como diferencial:** auditar consistência entre os relatórios existentes (dashboard vs. relatório de pedidos vs. recebíveis) para garantir que os números batem entre si — a dor de mercado é exatamente "números divergentes", então isso merece um QA dedicado antes do discurso comercial usar "relatórios confiáveis".

---

## 8. Financeiro e caixa não fecham

**Já resolve:** este é outro ponto forte real hoje — sessão de caixa com abertura/sangria/suprimento/fechamento e conferência declarado-vs-esperado (testado); múltiplas formas de pagamento no mesmo fechamento (`order_payments`, compartilhado entre PDV e Balcão); Pix do pedido tem fluxo de conciliação por `idempotency_key`/status `divergent` quando o valor não bate (a "tela de conciliação clara" já existe **no dado**, no nível de API).

**Falta:** taxa de maquininha/marketplace não é conciliada (não existe cartão real nem marketplace ainda, então não há taxa real a conciliar); Pix real não está conectado a PSP nenhum (`ManualPaymentProvider` é no-op, webhook sempre 501) — então "Pix conciliado" hoje é infraestrutura pronta, não realidade em produção; tela de conciliação dedicada no frontend (o dado existe no backend, a UI de conciliação como tela própria não foi confirmada).

**Recomendação:**
- **Não é baixo esforço:** ligar um PSP real (Pix) é decisão de investimento e regulatória — mas a base nativa (assinatura/fatura/pagamento/webhook com idempotência) já está pronta e testada, só falta o adapter real. Isso é o item de **maior alavancagem** do roadmap: uma vez plugado um PSP, tanto a cobrança de plano quanto o pagamento de pedido passam a funcionar de verdade, porque a camada de abstração já foi desenhada para isso desde o início.
- **[decisão do dono]** escolha do PSP (Mercado Pago/Asaas/Efí — ver roadmap `2026-07-20`, seção 2.7) é decisão comercial, não técnica; a arquitetura já não depende dessa escolha.
- **Baixo esforço (M):** tela de conciliação no frontend usando o dado que já existe no backend (`payments`/`refunds`/`webhook_events`) — vale construir mesmo antes do PSP real, porque valida a UX com dado manual/simulado.

---

## 9. Cobrança difícil de entender, custo cresce depois

**Já resolve:** os 3 planos (Prata/Ouro/Diamante) já são feature-gate transparente (nunca aparece no menu o que o plano não libera); preço por ciclo já é modelado com desconto congelado (`plan_prices`, nunca reajusta retroativamente o que já foi contratado); cancelamento sem multa + direito de arrependimento de 7 dias já implementados e testados; histórico de fatura por tenant existe.

**Falta:** simulador de preço público antes de contratar (não confirmado no site institucional); cobrança automática real (assinatura ainda não é ligada ao signup — tenant novo não vira assinante automaticamente, é decisão pendente registrada na memória).

**Recomendação:**
- **Baixo esforço (M), site institucional (`site/`):** página de preço pública + simulador simples (mensal/trimestral/anual com desconto já visível) — dado já existe em `plan_prices`, é só expor.
- **Depende do item 8** (PSP real) para a cobrança automática de fato acontecer — mas a transparência de preço/ciclo/cancelamento já pode virar discurso de venda **hoje**, mesmo antes do PSP, porque a política em si (sem multa, arrependimento, preço congelado) já é real.

---

## 10. Dependência de marketplace, perda de margem

**Já resolve:** canal próprio já existe e é forte relativamente ao gap de mercado — loja online própria (`/loja/:slug`), Portal do cliente final com histórico/favoritos/endereços, cashback configurável e cupom/promoção já funcionais.

**Falta:** CRM real (segmentação de cliente para campanha, régua de reativação); nenhuma automação de marketing (e-mail/WhatsApp de reengajamento); "categoria de cliente" existe mas é usada só para preço, não para campanha.

**Recomendação:**
- **Médio prazo (M/G):** régua simples de reativação (ex.: cliente sem pedido há N dias recebe cupom automático) — reaproveita cupom+cliente+categoria já existentes, é orquestração nova sobre dado existente, não schema novo.
- **Estratégico (G/GG):** CRM completo com segmentação avançada e campanha multicanal — maior investimento, mas parte da fundação (cliente, categoria, cashback, cupom) já está pronta; o gap real é a camada de automação/régua, não o cadastro.

---

## 11. WhatsApp vende mas vira caos

**Já resolve:** link/handle de WhatsApp cadastrado na loja pública (redes sociais); envio de link de rastreio por WhatsApp já é uma configuração existente em `tenant_settings`.

**Falta:** atendimento híbrido bot+humano, carrinho visual dentro do WhatsApp, pedido não entra automaticamente no PDV a partir de mensagem — é 100% gap, não existe integração de mensageria conversacional.

**Recomendação:**
- **Não é baixo esforço** — exige API oficial do WhatsApp Business (Meta), que tem custo por conversa e homologação própria. **[decisão do dono]** sobre investir nisso vs. reforçar a loja online (que já resolve boa parte do mesmo problema sem depender de terceiro).
- Caminho intermediário de baixo custo: manter o link direto de WhatsApp levando para a **loja online já existente** (catálogo/checkout completo) em vez de manter o pedido só em texto solto — já é uma melhoria de processo sem nenhuma linha de integração nova, só ajuste de material de divulgação/CTA.

---

## 12. Cardápio digital pouco flexível

**Já resolve:** promoção pontual com data de início/fim sem mexer no preço "normal"; preço por categoria de cliente e preço de atacado por quantidade mínima já existem; loja pública já tem catálogo completo.

**Falta:** "repetir pedido anterior" — não confirmado como existente hoje (o Portal tem histórico e favoritos, mas não um botão explícito de "repetir"); métricas de abandono de carrinho; checkout longo não foi auditado quanto a etapas.

**Recomendação:**
- **Baixo esforço/alto impacto (P/M):** botão "repetir pedido" no Portal/histórico — é UI sobre dado que já existe (histórico de pedidos + itens), sem schema novo.
- **Médio prazo (M):** telemetria simples de abandono de carrinho no checkout da loja online (evento client-side + contagem) — baixo custo de construção, alto valor de decisão pro tenant.

---

## 13. Fiscal complexo, erro aparece tarde

**Já resolve:** cadastro fiscal completo do tenant/produto/cliente (CNPJ, regime tributário, NCM, CFOP, CSOSN/CST); `tax_rules` versionadas por vigência (nunca hardcoded); `fiscal_documents` já modelado com estados (`pending`/`authorized`/`rejected`/`denied`/`canceled`) prontos para receber emissão real; ambiente fiscal nunca começa em produção por padrão (protege contra emitir nota real por engano).

**Falta:** emissão real de NF-e/NFC-e/NFS-e (não existe comunicação com SEFAZ/prefeitura ainda — `ManualFiscalProvider` só marca `pending`, nunca autoriza); catálogo de rejeições com tradução amigável (não existe porque não há rejeição real ainda); certificado digital A1 (não há suporte a upload/gestão de certificado ainda); motor de cálculo automático de imposto sobre o pedido (regras existem cadastradas, mas não são aplicadas automaticamente ainda).

**Recomendação:**
- **Este é o maior risco de todo o roadmap** (confirmado nos 3 documentos de roadmap) — não subestimar nem prometer prazo curto no discurso de venda.
- **Caminho recomendado quando o dono decidir investir:** começar pela NFS-e da própria Maskats (baixo volume, justifica serviço pago por nota) antes de NF-e/NFC-e dos tenants (alto volume, mais barato migrar para biblioteca nativa `sped-nfe` só quando o volume justificar).
- **[decisão do dono]** contratar serviço de emissão pago (Focus NFe/PlugNotas/NFe.io) vs. biblioteca nativa — trade-off é custo por nota/mensalidade vs. risco técnico de rodar SOAP/certificado A1 em hospedagem compartilhada (Hostinger pode não suportar bem `.pfx`/openssl — **requer validação de infra antes de decidir**).
- Enquanto isso, o cadastro fiscal já pronto **é** discurso de venda válido: "sua empresa já está com o cadastro fiscal pronto, a emissão liga quando você decidir o app fiscal" — sem prometer emissão real hoje.

---

## 14. Falta de confiança nas permissões de funcionário

**Já resolve:** este é um ponto forte real e verificado — permissão granular por functionality+action, sem exceção, controlada por tela dedicada sem mexer em código; perfis por empresa configuráveis (Vendedor/Caixa/Gerente); auditoria de toda mutação relevante (quem fez, quando, o quê mudou), auditada por security-specialist em 2026-07-22 sem IDOR encontrado; cancelamento de item de comanda já exige motivo e é auditado; fechamento de caixa concilia contra o esperado (evita "sumiço" de dinheiro sem rastro).

**Falta:** PIN individual por operador dentro de uma sessão de staff compartilhada (hoje o login é por usuário completo, não há "login rápido por PIN" sobre uma sessão já aberta — mencionado como UX a definir na Fase 1 do PDV/Balcão, ainda não implementado); limite de desconto por perfil (permissão controla "pode aplicar desconto" como ação binária, não um teto percentual configurável); fluxo de aprovação explícito para cancelamento/desconto acima de um limite (auditoria registra o que aconteceu, mas não há um "pedido de aprovação" antes do ato).

**Recomendação:**
- **Baixo esforço/alto impacto (M):** limite percentual de desconto por perfil (campo novo em `TenantRolePermission` ou similar) — reaproveita a estrutura de permissão já existente, é extensão, não redesenho.
- **Médio prazo (M):** PIN individual por operador no PDV/Balcão (login rápido sobre sessão de staff já aberta) — já está desenhado na documentação do PDV, falta construir a UI + endpoint leve de verificação de PIN.
- **Este item já é discurso de venda forte hoje** mesmo sem os dois gaps acima — auditoria completa e permissão granular já resolvem a maior parte da dor de mercado #14.

---

## 15. Implantação longa e cadastro inicial cansativo

**Já resolve:** autoatendimento de dados da empresa (nome+logo); carga de demonstração ("10k Atacadista") já usada internamente como referência de todas as features de catálogo — prova que o modelo de dados suporta carga em volume.

**Falta:** onboarding guiado dentro do produto (checklist "faça isso, depois isso"); importação de produto por planilha/imagem; simulação de venda antes de "ir ao ar" (ambiente de teste guiado).

**Recomendação:**
- **Baixo esforço/alto impacto (M):** importação de produto por CSV/planilha — é o item de maior retorno em relação ao esforço de toda a lista de onboarding: reduz a fricção #1 relatada no mercado (cadastro inicial cansativo) sem exigir OCR nem IA, só um parser de planilha com preview antes de confirmar.
- **Médio prazo (M):** checklist de implantação nativo no painel (produto cadastrado? cliente cadastrado? loja configurada? primeira venda?) — reaproveita dados que já existem (contadores simples de cada entidade), é UI + query, sem schema novo relevante.
- Importação por imagem (OCR de nota/catálogo) fica como evolução futura, não é baixo esforço e depende de serviço de IA externo — **[decisão do dono]**.

---

## 16. Multiunidade prometida mas gestão fragmentada

**Já resolve:** múltiplos locais de estoque (depósito/filial) por tenant já existem, com saldo por produto e local e transferência entre locais.

**Falta:** hierarquia grupo/marca/unidade — hoje **1 tenant = 1 empresa**, não existe o conceito de "rede com várias lojas/tenants sob uma marca" com produtos compartilhados e comparação entre unidades; publicação em lote (mudar preço/produto em várias lojas de uma vez, porque não há "várias lojas" no sentido de vários tenants ligados).

**Recomendação:**
- Isso é uma decisão de arquitetura grande, não uma feature isolada — hoje multi-loja já funciona **dentro** de um tenant (múltiplos locais de estoque), mas multi-**tenant** hierárquico (uma marca com N CNPJs/tenants) é uma mudança estrutural.
- **Estratégico (GG):** modelar uma entidade "Grupo/Rede" acima de `Tenant` (N:1) só quando houver demanda real confirmada de cliente com múltiplos CNPJs querendo visão consolidada — não construir especulativamente; o modelo atual de "múltiplos locais dentro de 1 tenant" já cobre a maioria dos casos de rede pequena (2-3 lojas do mesmo CNPJ).

---

## 17. Personalização limitada

**Já resolve:** feature-gate por plano já é uma forma de "feature flag por cliente" no nível de plano comercial; `tenant_settings` já parametriza um bom volume de comportamento por tenant sem precisar de código novo (forma de pagamento, taxa de entrega, taxa de serviço, chave Pix, etc.).

**Falta:** API pública documentada para terceiro; webhooks de saída (o sistema recebe webhook de pagamento, mas não emite webhook de evento de domínio para um terceiro assinar); automação "quando X faça Y" configurável pelo próprio tenant; feature flag por tenant individual (hoje é por plano, não por tenant específico dentro do mesmo plano).

**Recomendação:**
- **Estratégico (G/GG):** API pública com webhooks de saída — o padrão de Event/Listener já existente internamente (toda mutação de domínio já dispara Event) é a base natural para expor webhooks de saída sem redesenhar nada, só adicionar um listener genérico que publica em fila e chama a URL cadastrada pelo tenant.
- Não é prioridade de curto prazo — só faz sentido quando houver demanda de integração de terceiro madura o suficiente para justificar o esforço de documentação/segurança (assinatura de payload, retry, etc.).

---

## 18. App mobile do dono incompleto

**Já resolve:** PWA web responsivo, mobile-first por decisão de projeto (confirmado: "uso majoritário é via celular"); dashboard com métricas em tempo real (via API, não atrasado por natureza, já que não há cache agressivo entre o dado e a tela).

**Falta:** app nativo (não existe, `app/` está reservado mas não iniciado); ação direta pelo celular tipo "bloquear produto"/"aprovar cancelamento" a partir de uma notificação push — push já existe para outros fluxos (cashback, delivery), mas ação administrativa por push não foi confirmada.

**Recomendação:**
- **Baixo esforço (M):** ações administrativas rápidas dentro do PWA já existente (bloquear produto, aprovar cancelamento) — não depende de app nativo, é tela/rota nova dentro do `web/` já mobile-first, reaproveitando permissão/auditoria já existentes.
- **[decisão do dono] investimento maior (GG):** app nativo de verdade (`app/`) — só faz sentido quando o volume de uso mobile justificar recursos que só nativo dá (push mais confiável, ícone na tela inicial sem instrução manual, performance). PWA já cobre a maior parte da dor relatada hoje.

---

## 19. Dados presos no fornecedor

**Já resolve:** exportação CSV/PDF já existe em várias listagens (clientes, pedidos); backup automatizado nativo (`backup:database`, mysqldump+gzip, retenção configurável) já implementado — reduz o medo de "perder tudo", ainda que não resolva "portar para outro sistema".

**Falta:** backup sob demanda pelo próprio tenant (hoje o backup é operado pela Maskats, não auto-serviço do cliente); exportação completa/portátil de todos os dados do tenant (hoje é exportação parcial por tela, não um "exportar tudo" único); cópia do backup fora do servidor principal (confirmado como pendência real — hoje só fica no próprio storage).

**Recomendação:**
- **Baixo esforço (M):** botão "exportar meus dados" que gera um pacote (CSV por entidade) sob demanda para o próprio tenant — reduz diretamente o medo relatado no mercado, sem exigir arquitetura nova (mesma lógica de exportação já usada em Clientes/Pedidos, generalizada).
- **P0 real de infraestrutura (não é feature, é risco):** cópia do backup para storage externo ao servidor principal — hoje o backup existe mas fica no mesmo servidor, o que não protege contra perda total do servidor. Baixo esforço técnico (rclone/bucket barato), alto risco se ignorado.

---

## 20. Atualização que muda o funcionamento sem preparação

**Já resolve:** CI já bloqueia deploy se os testes falharem (gate de qualidade); migrations não são aplicadas em produção automaticamente hoje (aplicação manual confirmada como regra do projeto) — isso é, na prática, um freio manual contra mudança inesperada, mesmo não sendo pensado como feature de produto.

**Falta:** release notes dentro da tela do produto; ambiente de teste acessível ao próprio tenant (existe ambiente de homologação fiscal, mas não um "modo teste" geral do produto); liberação gradual (feature flag por tenant, ver item 17).

**Recomendação:**
- **Baixo esforço/alto impacto (P/M):** tela/painel simples de "novidades" (release notes) dentro do produto, versionada — reaproveita o mesmo padrão já usado para `legal_documents` (documento versionado com aceite), sem inventar mecanismo novo, só um novo tipo de documento sem exigir aceite.
- Liberação gradual de verdade depende do item 17 (feature flag por tenant) — não é prioridade isolada, entra junto quando aquele investimento acontecer.

---

## Ranking final combinado (dor de mercado × esforço real no Maskats)

### Ataque rápido (semanas, baixo esforço, já reaproveita dado/infra existente)
1. **Endpoint `/health` + Sentry free tier + UptimeRobot** (item 1/2) — visibilidade de erro em produção, hoje inexistente.
2. **Botão "repetir pedido"** no Portal (item 12) — UI sobre dado que já existe.
3. **Importação de produto por planilha (CSV)** (item 15) — maior retorno/esforço de toda a lista de onboarding.
4. **Relatório "resultado por canal" + drill-down até o pedido** (item 7) — dado já existe em `orders.origin`, falta só visualização.
5. **Cópia de backup para storage externo** (item 19) — risco real de infraestrutura, baixo esforço técnico.
6. **Limite percentual de desconto por perfil** (item 14) — extensão da permissão granular já existente.
7. **Botão "exportar meus dados"** (item 19) — generaliza exportação já existente.
8. **Tela de release notes versionada** (item 20) — reaproveita o padrão de `legal_documents`.
9. **Busca universal (`Ctrl+K`)** (item 4) — UI sobre rotas/ações já existentes.
10. **Página de preço público + simulador** no `site/` (item 9) — dado já existe em `plan_prices`.

### Médio prazo (meses, exige desenho novo mas reaproveita fundação existente)
- PIN individual por operador no PDV/Balcão (item 14).
- Régua simples de reativação de cliente (item 10).
- CMV simples por produto (item 6).
- Checklist de implantação guiado (item 4/15).
- Ações administrativas rápidas no PWA (aprovar cancelamento, bloquear produto) (item 18).
- Tela de conciliação financeira no frontend (item 8) — dado já existe no backend.
- Telemetria de abandono de carrinho (item 12).
- Central de chamados nativa com diagnóstico anexado (item 2).

### Só faz sentido com investimento real / decisão do dono **[decisão do dono]**
- **PSP real (Pix/cartão)** ligado à cobrança de planos e ao pagamento de pedidos (itens 8/9) — a arquitetura já está pronta (`PaymentProviderInterface`, webhook com idempotência), falta a decisão comercial + custo por transação.
- **Emissão fiscal real (NF-e/NFC-e/NFS-e)** (item 13) — maior risco técnico e legal de todo o roadmap; cadastro já pronto, emissão depende de serviço pago ou biblioteca nativa + validação de infraestrutura (certificado A1 em hospedagem compartilhada).
- **Integração de marketplace** (item 3) — cada canal é um projeto próprio, não construir sem demanda confirmada do segmento certo.
- **WhatsApp Business API oficial** (item 11) — custo por conversa + homologação Meta.
- **App mobile nativo** (item 18) — só quando o PWA deixar de ser suficiente.
- **Hierarquia de grupo/marca multi-CNPJ** (item 16) — mudança estrutural, só com demanda real.
- **API pública + webhooks de saída** (item 17) — só quando houver integração de terceiro madura o suficiente.
- **Offline-first do PDV/Balcão** (item 1) — maior risco técnico do roadmap operacional, Fase 3 já desenhada mas não construída.
- **Sair de hospedagem compartilhada** — vira necessário se qualquer um dos itens acima (fiscal com certificado A1, fila real para offline) esbarrar em limitação técnica da Hostinger confirmada na prática.

---

## Discurso de venda (ancorado só no que já existe hoje)

1. "No Maskats, quando você vende, o estoque baixa de verdade, o caixa fecha batendo e o cancelamento nunca some com um pagamento — cada movimentação fica registrada e auditada, sempre com motivo."
2. "Cada funcionário só vê e faz o que o seu perfil permite — configurável por tela, sem depender de mexer em código — e toda ação fica no histórico, com data, hora e responsável."
3. "Seu preço, seu ciclo de cobrança e sua política de cancelamento são transparentes desde o primeiro dia: sem multa, com 7 dias de arrependimento e sem cobrança escondida depois que você já assinou."
4. "Sua empresa já sai com o cadastro fiscal pronto — CNPJ, regime tributário, produtos com NCM/CFOP — para quando decidir ligar a emissão de nota, sem recomeçar do zero."
5. "Um sistema só para pedido, PDV e comanda de mesa/balcão: o mesmo pedido que veio da loja online aparece no mesmo painel que o pedido do caixa físico — sem exportar planilha entre sistemas diferentes."

(Diferencial que passa a existir assim que os itens de "ataque rápido" saírem: monitoramento de erro ativo, relatório de canal mais lucrativo, importação de catálogo por planilha e exportação de dados sob demanda — vale planejar o discurso já pensando nesse próximo degrau.)
