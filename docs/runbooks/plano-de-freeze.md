# Runbook — Plano de freeze de deploy

Critérios objetivos de quando congelar deploys e como reverter. Documentação operacional — não altera pipeline de CI/CD automaticamente; é um procedimento humano a seguir.

## Quando ativar o freeze

Ativar freeze de deploy (nenhum merge/deploy em `main` além de correção de incidente crítico) quando **qualquer** um dos critérios abaixo se aplicar:

1. **Evento de alta demanda com `high_demand_mode=true`** — freeze começa **4 horas antes** do horário programado de abertura de vendas e termina **2 horas depois** do pico inicial normalizar (ver `virtual_queue.waiting` estabilizando em `GET /reports/operation-snapshot`, ou o próprio `high_demand_mode` sendo desativado no evento, conforme `alta-demanda-evento-grande.md`).
   - Essa janela (4h antes / 2h depois) é um default técnico operacional, **não validado com o usuário** — ajustar conforme o histórico real do tenant conforme mais eventos grandes acontecerem.
2. **Incidente ativo de pagamento** (PagBank ou Mercado Pago instável, ver `contingencia-integracoes-pagamento.md`) — freeze até o PSP normalizar e a fila de reconciliação pendente voltar a zero.
3. **`composer test` ou `npm run build`/`npm run lint` quebrados em `main`** — freeze de fato já existe (não é possível fazer deploy com CI vermelho), mas vale tratar como sinal de freeze também para novos PRs até a causa raiz ser corrigida.
4. **Checkout error rate anormalmente alto** (`checkout.error_rate_percent` do snapshot muito acima do baseline do tenant, fora de uma janela de alta demanda conhecida) — pode ser sintoma de regressão recente; freeze até identificar se é deploy recente ou causa externa.

## O que "freeze" significa na prática

- Nenhum merge em `main` que não seja:
  - Correção do incidente que motivou o freeze.
  - Hotfix de segurança crítica.
- Nenhum deploy manual/automático adicional durante a janela, mesmo que o PR já esteja pronto — aguardar o fim do freeze.
- PRs prontos continuam podendo ser revisados/aprovados normalmente — só o merge/deploy fica bloqueado.

## Como reverter/encerrar o freeze

1. Confirmar que o motivo do freeze não está mais ativo:
   - Evento de alta demanda: pico normalizado (ver critério acima).
   - Incidente de pagamento: PSP normalizado, fila de reconciliação zerada.
   - CI quebrado: `composer test`, `npm run build`, `npm run lint` voltaram a fechar verde em `main`.
   - Checkout error rate: voltou ao baseline esperado do tenant.
2. Comunicar o fim do freeze para quem estava com PR pendente.
3. Retomar deploys normalmente — sem necessidade de nenhuma ação técnica adicional (o freeze é um acordo operacional da equipe, não um mecanismo de bloqueio automatizado no pipeline).

## O que este runbook NÃO cobre

- Bloqueio automático de merge via CI/GitHub branch protection amarrado a essas condições — não implementado nesta rodada (exigiria decisão de infraestrutura/permissões do usuário, ex: quem pode dar override). Hoje o freeze é um procedimento humano, comunicado pela equipe.
