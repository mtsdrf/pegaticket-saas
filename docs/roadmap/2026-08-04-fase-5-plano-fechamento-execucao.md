# PegaTicket — Plano Único de Fechamento da Fase 5

Data de referência: **4 de agosto de 2026**

## Objetivo

Consolidar em um único documento os próximos passos necessários para **fechar a Fase 5** sem reabrir discovery a cada novo bloco.

Este plano assume como base o estado já implementado até **4 de agosto de 2026**:

- split PagBank com custódia;
- taxa global fixa;
- geração de `receivables`;
- geração de `settlements`;
- liberação e reconciliação de custódia;
- política inicial de estorno;
- política inicial de chargeback/fraud review.

## 1. O que já consideramos decidido

Não precisa ser rediscutido para continuar a execução:

1. **Modelo principal**
   - PagBank Split + Custódia.
   - Plataforma como recebedor primário.
   - Organizador como recebedor secundário.

2. **Prazo padrão**
   - `D+1` após o fim do evento.

3. **Taxa da plataforma**
   - valor fixo global em BRL;
   - igual para todos os tenants;
   - pode ser `R$ 0,00` ou maior.

4. **Estratégia de rollout**
   - primeiro fechar o financeiro operacional;
   - depois expandir para fiscal/ERP;
   - não abrir novas frentes paralelas de produto antes disso.

## 2. Princípio de execução daqui para frente

Daqui em diante, o trabalho deve seguir esta ordem:

1. **fechar o fluxo financeiro operacional end-to-end**;
2. **dar visibilidade operacional e administrativa**;
3. **fechar exceções e recuperação**;
4. **só então entrar em fiscal/ERP**.

Discovery adicional só deve acontecer se surgir um destes casos:

- o PagBank responder diferente do contrato documentado;
- aparecer bloqueio jurídico/regulatório;
- surgir contradição entre saldo local e saldo real do PSP;
- o usuário quiser mudar uma decisão de produto já fechada.

## 3. Lacunas remanescentes até fechar a Fase 5

### 3.1 Operação financeira

Ainda faltam:

- fechamento administrativo mais rico de `pending_recovery`;
- fechamento administrativo mais rico de `pending_review`;
- padronização final dos catálogos de ajustes manuais;
- fechamento financeiro por evento;
- visão de saldo disponível, futuro, retido e em risco;
- borderô/exportação operacional.

### 3.2 Governança financeira

Ainda faltam:

- regra explícita de responsabilização por:
  - custo PSP;
  - refund;
  - chargeback;
  - fraude;
  - divergência operacional;
- trilha administrativa para decidir recuperação vs absorção pela plataforma;
- motivos padronizados de ajuste e decisão.

### 3.3 Superfície de produto

Ainda faltam:

- painel financeiro do tenant;
- painel financeiro global/admin;
- fila de repasses;
- fila de exceções;
- filtros por tenant, evento, status e data;
- detalhe da venda com trilha financeira consolidada.

### 3.4 Fiscal/ERP

Ainda faltam:

- modelagem do documento fiscal;
- eventos contábeis/exportáveis;
- integração inicial com ERP;
- reconciliação fiscal mínima.

## 4. Plano único de execução

## Etapa A — Fechar a retaguarda financeira

Objetivo:

- fazer o motor financeiro ficar operacionalmente confiável antes de construir UI ampla.

Entregas:

1. **Recuperação de valores pós-repasse**
   - status: **parcialmente entregue em 4 de agosto de 2026**;
   - já existe fluxo de resolução para `pending_recovery`;
   - já marca recuperação do organizador vs absorção pela plataforma;
   - já reflete em `settlement_adjustments` e `ledger_entries`;
   - falta enriquecer catálogo operacional e superfície administrativa.

2. **Resolução de exposição da plataforma**
   - status: **parcialmente entregue em 4 de agosto de 2026**;
   - já existe workflow para `pending_review`;
   - já guarda autor, motivo, timestamp e observação;
   - já suporta absorção, descarte e reclassificação para recuperação;
   - falta amadurecer nomenclatura final e UX administrativa.

3. **Ajustes manuais auditáveis**
   - status: **entregue no primeiro marco em 4 de agosto de 2026**;
   - serviço administrativo para criar ajuste manual já implementado;
   - tipos iniciais entregues:
     - `manual_credit`;
     - `manual_debit`;
   - toda criação já gera `settlement_adjustment` + `ledger_entry`;
   - falta expandir catálogo para casos operacionais mais específicos.

4. **Reconciliação interna completa**
   - status: **parcialmente entregue em 4 de agosto de 2026**;
   - comando e resumo inicial já sinalizam:
     - recebível sem settlement quando já elegível;
     - settlement liberado sem ledger de baixa;
     - ajuste ainda aberto;
     - settlement com soma divergente;
   - falta ampliar a varredura para `payments`, `refunds` e `sale_refunds` em profundidade total.

Critério de aceite:

- qualquer exceção financeira deve poder ser representada e resolvida sem edição manual no banco.

## Etapa B — Fechar o fechamento por evento

Objetivo:

- transformar o financeiro em operação de evento, não só de venda isolada.

Entregas:

1. **Fechamento consolidado por evento**
   - status: **parcialmente entregue em 4 de agosto de 2026**;
   - resumo por evento já implementado com:
     - bruto;
     - taxa da plataforma;
     - líquido do organizador;
     - valores em custódia;
     - valores liberados;
     - refunds;
     - chargebacks;
     - exposições pendentes;
   - falta expandir a visão para painel/lista consolidada multi-evento.

   - resumo por evento com:
     - bruto;
     - taxa da plataforma;
     - líquido do organizador;
     - valores em custódia;
     - valores liberados;
     - refunds;
     - chargebacks;
     - exposições pendentes.

2. **Estado de fechamento**
   - status: **parcialmente entregue em 4 de agosto de 2026**;
   - status derivado já implementado:
     - `open`
     - `ready_to_settle`
     - `settling`
     - `settled`
     - `settled_with_exceptions`
   - falta consolidar isso em painéis administrativos e filtros globais.

   - status sugeridos:
     - `open`
     - `ready_to_settle`
     - `settling`
     - `settled`
     - `settled_with_exceptions`
   - fechamento deve depender dos `receivables`/`settlements`, não de planilha.

3. **Borderô operacional**
   - status: **parcialmente entregue em 4 de agosto de 2026**;
   - exportação CSV inicial já implementada por evento com:
     - venda;
     - pagamento;
     - recebível;
     - repasse;
     - ajustes;
   - falta expandir para Excel/PDF e consolidado final por evento.

   - exportação CSV/Excel/PDF simples com:
     - venda;
     - pagamento;
     - recebível;
     - repasse;
     - ajustes;
     - saldo final por evento.

Critério de aceite:

- um operador deve conseguir fechar um evento sem montar conta manual fora do sistema.

## Etapa C — Entregar a superfície administrativa e do tenant

Objetivo:

- expor o motor financeiro já construído para operação real.

Entregas mínimas de UI/API:

1. **Painel financeiro do tenant**
   - status: **parcialmente entregue em 4 de agosto de 2026**;
   - backend já implementado com:
     - saldo futuro;
     - saldo em custódia;
     - saldo liberado;
     - próximo repasse previsto;
     - contagem de ajustes/exceções;
   - UI inicial já implementada em 4 de agosto de 2026;
   - falta ampliar drill-down visual e refinamento final.

   - saldo futuro;
   - saldo em custódia;
   - saldo liberado;
   - repasses previstos;
   - ajustes recentes;
   - exceções pendentes.

2. **Lista de recebíveis**
   - status: **parcialmente entregue em 4 de agosto de 2026**;
   - backend já implementado com filtros por:
     - evento;
     - status;
     - settlement;
     - data;
   - UI inicial já implementada em 4 de agosto de 2026;
   - falta aprofundar o drill-down por venda.

   - filtros por:
     - evento;
     - data;
     - status;
     - settlement;
   - visão detalhada por venda.

3. **Lista de repasses**
   - status: **parcialmente entregue em 4 de agosto de 2026**;
   - backend já implementado com:
     - listagem;
     - resumo;
     - quantidade de recebíveis;
     - net;
     - status;
     - open adjustments amount;
   - UI inicial já implementada em 4 de agosto de 2026;
   - falta expandir filtros e ações administrativas globais.

   - filtros por:
     - status;
     - tenant;
     - data;
   - mostrar:
     - código;
     - total líquido;
     - quantidade de recebíveis;
     - released_at;
     - exceções ligadas.

4. **Fila de exceções**
   - status: **parcialmente entregue em 4 de agosto de 2026**;
   - backend já implementado para:
     - `pending_recovery`;
     - `pending_review`;
     - decisão/resolução;
   - falta consolidar divergências financeiras estruturais na mesma superfície visual.

   - `pending_recovery`;
   - `pending_review`;
   - divergências financeiras;
   - link para decisão/resolução.

5. **Configuração global financeira**
   - status: **parcialmente entregue em 4 de agosto de 2026**;
   - backend já implementado para taxa fixa global e parâmetros base do repasse;
   - falta a tela administrativa final consolidada.

   - tela administrativa para:
     - taxa fixa global;
     - `pagbank_primary_account_id`;
     - parâmetros operacionais do repasse.

Observação adicional em **4 de agosto de 2026**:

- a superfície administrativa global inicial do financeiro já entrou em implementação com:
  - dashboard cross-tenant;
  - lista cross-tenant de `receivables`;
  - lista cross-tenant de `settlements`;
  - fila cross-tenant de `settlement_adjustments`.
- a UI inicial dessas superfícies também já entrou em implementação em 4 de agosto de 2026.

Critério de aceite:

- o financeiro deve ficar operável por backoffice sem apoio constante de engenharia.

## Etapa D — Endurecimento operacional

Objetivo:

- reduzir risco de bugs, corrida e inconsistência de ambiente real.

Entregas:

1. **Idempotência completa**
   - geração de settlement;
   - liberação;
   - reconciliação;
   - ajustes financeiros;
   - resolução de exceções.

2. **Observabilidade**
   - logs estruturados para:
     - geração de recebível;
     - criação de settlement;
     - liberação PagBank;
     - reconciliação;
     - ajustes;
     - exceções.

3. **Health checks e comandos operacionais**
   - reprocessar settlement específico;
   - reconciliar settlement específico;
   - recalcular venda específica;
   - gerar relatório de inconsistência.

4. **Testes**
   - rounding monetário;
   - múltiplos eventos na mesma venda;
   - refund antes do repasse;
   - refund depois do repasse;
   - chargeback antes do repasse;
   - chargeback depois do repasse;
   - falha do PSP na liberação;
   - atraso de reconciliação.

Critério de aceite:

- qualquer problema operacional previsível precisa ter comando, log e teste.

## Etapa E — Fechar fiscal e integração externa

Objetivo:

- encerrar a Fase 5 com fundação suficiente para contabilidade e operação externa.

Entregas:

1. **Modelo fiscal mínimo**
   - entidade para documento fiscal;
   - vínculo com venda/evento/tenant;
   - status operacional.

2. **Exportação contábil**
   - export por período/evento/tenant;
   - base em ledger e não em consulta agregada solta.

3. **Integração ERP inicial**
   - primeiro conector simples ou export padronizado;
   - sem tentar ERP completo no primeiro fechamento.

Critério de aceite:

- o financeiro precisa ser exportável e rastreável sem reprocessamento manual de regra.

## 5. Ordem exata recomendada de execução

Para evitar idas e vindas, a ordem recomendada é:

1. workflow de `pending_recovery`;
2. workflow de `pending_review`;
3. ajustes manuais auditáveis;
4. reconciliação interna completa;
5. fechamento financeiro por evento;
6. borderô/exportação operacional;
7. painel financeiro do tenant;
8. painel financeiro global/admin;
9. endurecimento operacional e observabilidade;
10. fiscal mínimo e export contábil.

## 6. O que não devemos fazer agora

Para não dispersar:

- não abrir CRM/growth agora;
- não abrir antifraude enterprise agora;
- não abrir múltiplos gateways agora;
- não abrir ERP completo agora;
- não expandir PagBank direto por tenant como trilha principal;
- não refatorar o domínio financeiro já entregue sem necessidade objetiva.

## 7. Critério real de “Fase 5 concluída”

Consideraremos a Fase 5 concluída quando, ao mesmo tempo:

1. toda venda paga gerar recebível rastreável;
2. todo recebível elegível entrar em settlement sem intervenção manual;
3. todo settlement puder ser liberado e reconciliado;
4. refund e chargeback tiverem tratamento financeiro auditável;
5. exceções tiverem fila e resolução operacional;
6. o tenant conseguir enxergar saldo e repasses;
7. o backoffice conseguir fechar um evento financeiramente;
8. os dados puderem ser exportados para contábil/fiscal.

## 8. Decisões pendentes que ainda impactam execução

Só restam três decisões de produto que realmente podem mudar a implementação:

1. **quem absorve custo PSP por padrão**
2. **quem absorve refund/chargeback por padrão**
3. **se o fechamento padrão continuará 100% por evento ou ganhará visão consolidada por tenant**

Se o usuário não quiser reabrir isso agora, a recomendação operacional para seguir sem bloquear é:

- custo PSP: plataforma rastreia, mas ainda não redistribui automaticamente;
- refund/chargeback: consumir primeiro o líquido do organizador; sobra vira revisão da plataforma;
- fechamento: continuar por evento como padrão.

## 9. Conclusão executiva

O estado atual já saiu de discovery e entrou em **execução de fechamento**.

O caminho mais eficiente daqui até o encerramento da Fase 5 é:

1. finalizar **recuperação, revisão e ajustes**;
2. fechar **evento, borderô e painéis**;
3. endurecer operação;
4. entregar **export/fiscal mínimo**.

Se seguirmos esse plano sem abrir novas frentes paralelas, a Fase 5 deixa de ser “fundação promissora” e vira **financeiro operacional de verdade**.
