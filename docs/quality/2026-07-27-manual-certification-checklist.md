# Checklist Manual de Certificação

Data: 2026-07-27

## Objetivo

Checklist enxuto para homologação manual dos fluxos críticos antes da entrada em produção ou antes de cada release relevante.

## Pré-condições

- banco atualizado
- seed de QA aplicado
- credenciais de admin global válidas
- pelo menos uma empresa em trial
- pelo menos uma empresa com plano pago
- pelo menos um usuário comum da empresa
- ambiente com acesso mobile real

## Checklist por fluxo

### Autenticação

- login com credenciais válidas
- mensagem clara para credenciais inválidas
- logout funcional
- recuperação de senha funcional
- troca de senha funcional

### Empresa e assinatura

- owner enxerga o menu correto de assinatura
- usuário comum não enxerga menu de assinatura
- trial aparece corretamente
- troca de plano inicia o fluxo correto
- cancelamento altera CTAs corretamente
- recontratação fica disponível após cancelamento

### Permissões

- links sem permissão ficam ocultos
- acesso direto por URL sem permissão é bloqueado com mensagem clara
- grids proibidos não ficam carregando indefinidamente
- mensagens de bloqueio apontam o caminho correto

### Pedidos

- novo pedido manual
- novo pedido interno
- novo pedido na loja pública
- código do pedido gerado
- paginação alinhada visualmente
- grid com scroll horizontal quando necessário
- filtros e ordenações funcionando

### Operação

- abertura de caixa
- venda PDV
- abertura de comanda
- inclusão de item
- reserva de mesa
- fila de espera
- alteração de etapa do pedido
- cancelamento com motivo

### Offline

- queda de conexão
- fila offline acumulada
- sincronização após retorno
- banner de reconexão só aparece se houve perda anterior

### Público

- loja online com empresa habilitada
- empresa sem loja online exibe só informações institucionais
- reserva online aparece quando habilitada
- links públicos compartilháveis funcionando

### Integrações

- webhook de assinatura
- pagamento de assinatura
- marketplace com credenciais válidas
- fluxos externos retornando mensagens compreensíveis

## Critério de aprovação

Só aprovar a release quando:

- não houver bloqueio P0
- `php artisan test` estiver 100% verde
- fluxos críticos de operação passarem
- mensagens de erro estiverem compreensíveis
- permissões estiverem coerentes visualmente e via API
- ambiente mobile estiver navegável
