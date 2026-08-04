# PegaTicket — Modelo de Repasse Aprovado

Data de referência: **3 de agosto de 2026**

## Decisão aprovada

O modelo aprovado para a Fase 5 é:

- **opção 2, equilibrada**;
- **PagBank Split** como trilha principal;
- **plataforma como recebedor primário**;
- **organizador como recebedor secundário**;
- **custódia** sobre a parte do organizador;
- **taxa da plataforma fixa global em BRL**, retida no próprio split;
- **repasse padrão D+1 após o fim do evento**;
- **sem reserva extra no primeiro marco**, usando apenas custódia.

## Motivo da decisão

Esse desenho foi escolhido porque oferece o melhor equilíbrio entre:

- retenção automática da taxa da plataforma;
- menor dependência de repasse manual;
- aderência ao modelo de ticketing, em que cada checkout pertence a um único organizador;
- melhor capacidade de controle sobre cancelamento, refund e fechamento financeiro.

## Modelo operacional

### Durante a venda

- o comprador paga no checkout da plataforma;
- a transação nasce no PagBank com a plataforma como principal;
- o split separa a taxa fixa da plataforma e o valor do organizador;
- a parte do organizador fica em **custódia**.

### Após o evento

- o evento termina;
- inicia-se a janela padrão de liberação;
- o valor do organizador é liberado em **D+1 pós-evento**;
- não existe retenção adicional no primeiro marco além da própria custódia.

## Regras iniciais do produto

1. **Taxa da plataforma**
   - valor fixo global;
   - definido no administrativo da plataforma;
   - igual para todas as empresas;
   - pode ser `R$ 0,00` ou maior.

2. **Repasse padrão**
   - `D+1` após o fim do evento.

3. **Reserva de risco**
   - não existe reserva extra no primeiro marco;
   - somente a custódia do split.

4. **Responsabilidade financeira**
   - refund, chargeback e divergências operacionais ainda precisam de política formal de responsabilização no domínio financeiro.

## Implicações técnicas

O sistema deve passar a suportar:

- cadastro e uso da conta PagBank da plataforma como origem da transação;
- registro do identificador de split por venda/cobrança;
- cálculo da taxa fixa global antes da montagem do split;
- custódia da parcela do organizador;
- agenda de liberação vinculada ao fim do evento;
- operação de liberação por lote e por evento;
- rastreabilidade de `sale`, `payment`, `receivable`, `settlement` e `ledger_entry`.

## Impacto na arquitetura atual

O experimento de **PagBank direto por tenant** continua útil como trilha exploratória e fallback de integração, mas deixa de ser o desenho principal da Fase 5.

O desenho principal passa a ser:

- **split custodial**;
- **plataforma primária**;
- **organizador secundário**;
- **liberação pós-evento**.

## Sequência recomendada de implementação

1. criar o configurador global da taxa fixa;
2. modelar `receivables`, `settlements` e `ledger_entries`;
3. adaptar a integração PagBank para gerar `charges.splits`;
4. registrar custódia e agenda de liberação;
5. implementar liberação D+1 pós-evento;
6. adicionar reconciliação financeira do split.

## Fontes externas validadas

- PagBank Connect: <https://developer.pagbank.com.br/docs/connect>
- PagBank Orders: <https://developer.pagbank.com.br/docs/pedidos-e-pagamentos-order>
- Divisão do pagamento: <https://developer.pagbank.com.br/reference/divisao-de-pagamento>
- Configuração de split: <https://developer.pagbank.com.br/docs/config-split>
- Custódia no split: <https://developer.pagbank.com.br/reference/crie-e-pague-um-pedido-com-custodia>
- Cancelamento com split: <https://developer.pagbank.com.br/reference/cancelamento-de-pedido-com-divisao-do-pagamento>
