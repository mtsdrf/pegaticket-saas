# PegaTicket — Kickoff da Fase 5 (Financeiro, Repasses e Fiscal)

Data de referência: **3 de agosto de 2026**

## Objetivo

Abrir a Fase 5 com um escopo executável, reduzindo a ambiguidade entre:

- o que é fundação financeira obrigatória;
- o que depende de decisão de produto;
- o que deve esperar fases posteriores.

Este documento assume o estado atual já validado no roadmap global: Fases 1 a 4 essencialmente fechadas, com o núcleo transacional e operacional de ticketing pronto para sustentar a expansão financeira.

## 1. Princípio de sequência

A Fase 5 não deve começar por ERP, nota fiscal ou comissionamento avançado.

A ordem recomendada é:

1. definir o modelo de repasse;
2. materializar recebíveis e agenda de liquidação;
3. criar ledger simplificado e fechamento por evento;
4. só então expandir para fiscal e integrações externas.

## 2. Decisões de produto já fechadas nesta abertura

1. **Taxa da plataforma**
   - será um valor fixo em BRL;
   - configurado pelo administrador global do sistema;
   - igual para todas as empresas;
   - pode ser `R$ 0,00` ou maior.

2. **Modelo aprovado de recebimento e repasse**
   - a plataforma será o **recebedor primário** da transação;
   - o organizador será **recebedor secundário**;
   - a taxa da plataforma será retida no próprio split;
   - o modelo operacional padrão será **split com custódia**.

3. **Estratégia de integração**
   - o caminho alvo é **PagBank Split + Custódia**;
   - a evolução de credenciamento/onboarding seguirá para **PagBank Connect**;
   - o experimento de credencial direta por tenant deixa de ser o desenho principal da Fase 5.

4. **Prazo padrão de repasse**
   - o repasse padrão será **D+1 após o fim do evento**.

5. **Reserva de risco**
   - não haverá reserva extra no primeiro marco;
   - o bloqueio inicial será apenas a **custódia** do split.

## 3. Decisões de produto que continuam em aberto

Sem estas definições, a implementação financeira fica frágil ou retrabalhável:

1. **Granularidade da liberação**
   - liberação por venda;
   - liberação consolidada por evento;
   - eventual consolidação por tenant em fases seguintes.

2. **Responsabilidade sobre custos**
   - quem absorve custo do PSP;
   - quem absorve estorno/refund;
   - quem absorve divergência operacional.

3. **Granularidade do fechamento**
   - por tenant;
   - por evento;
   - por sessão;
   - por canal, se esse conceito vier a existir.

## 4. Escopo técnico do primeiro pacote

Pacote inicial recomendado da Fase 5:

- base para **split PagBank** com plataforma primária e organizador secundário;
- suporte a **custódia** na parte do organizador;
- agenda de **liberação D+1 pós-evento**;
- recebíveis e ledger ficam na sequência, não antes disso;

- `receivables`: previsão do que o tenant tem a receber por venda/evento;
- `settlements`: agenda e execução de repasses;
- `ledger_entries`: trilha contábil simplificada de crédito/débito;
- painel financeiro por evento;
- visão de saldo disponível, futuro e retido;
- trilha de ajuste manual com auditoria.

Ficam fora deste primeiro pacote:

- ERP completo;
- emissão fiscal completa;
- split marketplace multi-parte;
- afiliados/comissões avançadas;
- BI financeiro avançado.

## 5. Backlog técnico sugerido

### 5.1 Fundação de domínio

- criar entidades de `receivable`, `settlement`, `ledger_entry` e `settlement_adjustment`;
- versionar regras de taxa/repasse por tenant;
- vincular movimentos ao `sale`, `payment`, `refund`, `event` e `tenant`;
- garantir trilha de auditoria em toda mutação financeira.

### 5.2 Regras de cálculo

- calcular valor bruto da venda;
- calcular taxa fixa global da plataforma;
- calcular custo PSP rastreável;
- calcular valor líquido elegível para repasse;
- reservar valores bloqueados por refund/risco quando aplicável.

### 5.3 Operação

- dashboard financeiro do tenant;
- fechamento financeiro por evento;
- fila de repasses pendentes;
- exportação operacional básica;
- ajustes manuais auditáveis.

### 5.4 Segurança e consistência

- idempotência em geração de repasse;
- lock transacional em fechamento;
- reconciliação entre `payments`, `refunds`, `receivables` e `ledger_entries`;
- testes de isolamento multi-tenant e arredondamento monetário.

## 6. Fatiamento real iniciado em 3 de agosto de 2026

Primeira fatia técnica aberta:

- ajuste do desenho anterior para o modelo de split aprovado;
- definição de plataforma primária + organizador secundário;
- retenção da taxa fixa global no próprio split;
- custódia sem reserva extra no primeiro marco;
- liberação padrão D+1 após o fim do evento.

Fatiamento já implementado nesta etapa:

- tabela e serviço de `platform_finance_settings` como fonte de verdade da taxa fixa global;
- endpoint interno para leitura/atualização dessa configuração global;
- campo global `pagbank_primary_account_id` para a conta primária da plataforma no split;
- entidades-base de `receivables`, `settlements`, `settlement_adjustments` e `ledger_entries`;
- geração automática de `receivable` no evento `SalePaid`;
- cálculo inicial do vencimento como `D+1` após o maior `ends_at` entre os eventos envolvidos na venda;
- lançamento inicial em ledger do bruto e da taxa fixa da plataforma.

Fatiamento complementar implementado em 3 de agosto de 2026:

- campo `pagbank_receiver_account_id` por tenant para representar a conta do organizador no split;
- `PagBankPaymentProvider` agora escolhe credencial global da plataforma quando o split está configurado;
- payload real de split com `FIXED`:
  - em `qr_codes[].splits` para Pix;
  - em `charges[].splits` para cartão;
- retenção da taxa fixa global no recebedor da plataforma;
- custódia aplicada no recebedor do organizador, com `release.scheduled` em `D+1` pós-evento;
- persistência de `split_id` e da data planejada de liberação no `Payment.metadata`;
- propagação de `provider_split_id` para `receivables`.

Fatiamento complementar implementado na sequência imediata:

- serviço `SettlementGenerationService` para agrupar recebíveis elegíveis em lotes locais de repasse;
- comando `finance:generate-settlements` para execução manual/agendada;
- agendamento horário desse fechamento local;
- agrupamento por `tenant + event + data de disponibilidade`;
- vínculo automático de `receivables` ao `settlement`;
- mudança de status do recebível para `awaiting_release` quando entra em um lote;
- idempotência para não duplicar settlement nem reanexar recebíveis já vinculados.

Fatiamento complementar implementado em 3 de agosto de 2026 para fechamento do ciclo:

- `PagBankPaymentProvider` passou a consultar `GET /splits/{split_id}`;
- `PagBankPaymentProvider` passou a solicitar liberação em `POST /splits/{split_id}/custody/release`;
- serviço `SettlementReleaseService` para liberar settlements vencidos;
- comando `finance:release-settlements` para execução manual/agendada;
- baixa local do `settlement` com status `released` quando a custódia já estiver liberada ou for confirmada após a chamada;
- status intermediário `release_requested` quando a solicitação for aceita mas a consulta imediata ainda não refletir `RELEASED`;
- atualização em cascata dos `receivables` vinculados;
- lançamento de ledger `settlement_release` na baixa do repasse.

Fatiamento complementar implementado ainda em 3 de agosto de 2026 para robustez operacional:

- comando `finance:reconcile-settlement-releases`;
- reconciliação específica para settlements em `release_requested`;
- nova consulta ao split sem reenviar a solicitação de liberação;
- consolidação de `released_at` com a data efetivamente informada pelo PagBank;
- atualização incremental do metadata com `last_release_reconciliation_at`;
- fechamento tardio do repasse quando o PagBank refletir `RELEASED` com atraso.

Fatiamento complementar implementado ainda em 3 de agosto de 2026 para exceções financeiras:

- listener financeiro no evento `SaleRefundCreated`;
- `sale_refund_id` em `settlement_adjustments` para idempotência e rastreabilidade;
- ajuste automático de estorno sobre o `receivable` da venda;
- política inicial conservadora:
  - o estorno consome primeiro o líquido ainda atribuível ao organizador;
  - se o repasse ainda não saiu, esse valor reduz `receivable.net_amount` e, quando existir, `settlement.net_amount`;
  - se o repasse já saiu, esse valor vira `pending_recovery`;
  - qualquer sobra acima do líquido do organizador vira `refund_platform_exposure` com `pending_review`;
- lançamentos de ledger específicos para:
  - `refund_adjustment_applied`;
  - `refund_recovery_pending`;
  - `refund_platform_exposure`.

Incremento complementar validado em **4 de agosto de 2026** para contestações do PSP:

- `refund_id` em `settlement_adjustments` para rastrear chargeback/fraud review append-only;
- `ExternalReviewFinancialAdjustmentService` conectado ao fluxo de `registerExternalReview` do pagamento de venda;
- política inicial equivalente ao estorno para `chargeback`/`fraud alert`:
  - consome primeiro o líquido atribuível ao organizador;
  - se o repasse ainda não saiu, reduz `receivable.net_amount` e `settlement.net_amount`;
  - se o repasse já saiu, vira `pending_recovery`;
  - sobra acima do líquido do organizador vira `chargeback_platform_exposure` com `pending_review`;
- lançamentos de ledger específicos para:
  - `chargeback_adjustment_applied`;
  - `chargeback_recovery_pending`;
  - `chargeback_platform_exposure`.

Risco conhecido desta fatia:

- cancelamento e chargeback em split exigem desenho operacional cuidadoso;
- a plataforma passa a assumir posição mais central no fluxo financeiro e precisa amadurecer auditoria, ledger e reconcilição de repasse.

## 7. Critérios de saída do primeiro marco

O primeiro marco da Fase 5 estará realmente fechado quando:

- o organizador enxergar quanto vendeu, quanto será retido e quanto receberá;
- cada venda elegível gerar recebível rastreável;
- cada repasse gerar baixa auditável;
- divergências puderem ser ajustadas sem perda de trilha;
- o fechamento por evento não depender de conferência manual em planilha.

## 8. Próxima ação recomendada

O próximo passo mais valioso depois desta primeira fatia é implementar:

1. configurador global da taxa fixa no domínio administrativo;
2. infraestrutura de split PagBank com recebedor primário/secundário;
3. custódia e liberação D+1 pós-evento;
4. só então `receivables`, `settlements` e `ledger`.

Atualização consolidada até 4 de agosto de 2026:

1. configurador global da taxa fixa no domínio administrativo;
2. infraestrutura de split PagBank com recebedor primário/secundário;
3. geração local de `receivables` e `settlements`;
4. liberação externa da custódia e baixa efetiva do repasse no PagBank já iniciadas;
5. reconciliação operacional pós-liberação já iniciada;
6. política inicial de exceção para estorno já iniciada;
7. política inicial de contestação do PSP já iniciada em 4 de agosto de 2026;
8. workflow operacional de recuperação/cobrança já iniciado em 4 de agosto de 2026, com resolução auditável para `pending_recovery`;
9. workflow operacional de revisão já iniciado em 4 de agosto de 2026, com resolução auditável para `pending_review` e reclassificação para recuperação quando necessário;
10. ajustes manuais auditáveis já iniciados em 4 de agosto de 2026;
11. reconciliação estrutural inicial entre `receivables`, `settlements`, `settlement_adjustments` e `ledger_entries` já iniciada em 4 de agosto de 2026;
12. próximo gargalo: fechamento financeiro por evento, borderô/exportação e painéis operacionais/admin.
