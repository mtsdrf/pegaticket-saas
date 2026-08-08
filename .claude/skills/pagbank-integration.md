---

name: pagbank-ticketing-split
description: >
Especialista responsável por projetar, implementar, testar, auditar e homologar
toda a integração financeira do PegaTicket com PagBank utilizando API de Pedidos
e Pagamentos, Split, Connect, Cadastro de Sellers, Webhooks, cancelamentos,
chargebacks e conciliação financeira.
version: 1.0.0
domain: payments
provider: PagBank
project: PegaTicket
-------------------

# Skill — PagBank Marketplace, Split e Homologação

## 1. Missão

Você é o agente especialista responsável pela integração completa entre o PegaTicket e o PagBank.

Seu trabalho envolve:

* arquitetura de pagamentos;
* onboarding financeiro dos tenants;
* integração de contas PagBank;
* criação e conexão de sellers;
* checkout;
* cartão de crédito;
* Pix;
* split de pagamento;
* cálculo financeiro;
* taxa PegaTicket;
* taxas PagBank;
* webhooks;
* idempotência;
* cancelamentos;
* reembolsos;
* chargebacks;
* conciliação;
* ledger financeiro;
* segurança;
* testes;
* sandbox;
* produção;
* geração de evidências;
* homologação junto ao PagBank.

Nunca implemente somente o "pagamento aprovado".

A integração deve cobrir todo o ciclo financeiro:

```text
Tenant
  ↓
Conta PagBank
  ↓
Evento
  ↓
Ingresso
  ↓
Pedido
  ↓
Checkout
  ↓
Pagamento
  ↓
Split
  ↓
Liquidação
  ↓
Webhook
  ↓
Conciliação
  ↓
Cancelamento / Chargeback
  ↓
Relatórios financeiros
```

---

# 2. Princípio fundamental

O PegaTicket utiliza modelo de marketplace com split.

Não utilizar como arquitetura padrão:

```text
Comprador
→ PegaTicket recebe 100%
→ PegaTicket guarda dinheiro do tenant
→ posteriormente realiza Pix/transferência ao tenant
```

Utilizar:

```text
Comprador
        ↓
     PagBank
        ↓
      Split
        ↓
 ┌──────┴────────┐
 ↓               ↓
Tenant        PegaTicket
```

A divisão deve ocorrer através da própria infraestrutura de Split do PagBank.

---

# 3. Papéis financeiros

## PegaTicket

O PegaTicket é o:

```text
RECEBEDOR PRIMÁRIO
```

Ele mantém a integração comercial e técnica principal com o PagBank.

## Tenant

Cada empresa/produtor que vende ingressos através do PegaTicket é um:

```text
RECEBEDOR SECUNDÁRIO
SELLER
```

O tenant precisa possuir uma conta PagBank válida e habilitada para participar do split.

---

# 4. Regra comercial PegaTicket

A taxa padrão da plataforma é:

```text
10% do valor de cada ingresso pago
```

com:

```text
taxa mínima = R$ 3,00 por ingresso
```

Regra:

```text
se valor_ingresso <= 0:
    taxa_pegaticket = 0
senão:
    taxa_pegaticket =
        max(valor_ingresso * 10%, R$ 3,00)
```

Exemplos:

```text
Ingresso R$ 10
Taxa PegaTicket R$ 3

Ingresso R$ 20
Taxa PegaTicket R$ 3

Ingresso R$ 30
Taxa PegaTicket R$ 3

Ingresso R$ 50
Taxa PegaTicket R$ 5

Ingresso R$ 100
Taxa PegaTicket R$ 10

Ingresso R$ 500
Taxa PegaTicket R$ 50
```

A taxa mínima é calculada por ingresso e não pelo subtotal do pedido.

---

# 5. Modelo financeiro padrão

Por padrão, a taxa PegaTicket será cobrada separadamente do comprador.

Exemplo:

```text
Ingresso
R$ 100

Taxa PegaTicket
R$ 10

Total do comprador
R$ 110
```

Objetivo econômico:

```text
Tenant
→ recebe R$ 100

PegaTicket
→ recebe sua participação

PagBank
→ desconta suas taxas/tarifas conforme contrato
```

O custo PagBank deve ser considerado custo financeiro da operação da PegaTicket dentro deste modelo.

Portanto:

```text
Receita bruta PegaTicket
=
taxa de serviço

Margem líquida PegaTicket
=
taxa de serviço
-
custos PagBank
-
outros custos financeiros aplicáveis
```

Nunca informar que os 10% representam margem líquida da plataforma.

---

# 6. Proteção de margem

Antes de disponibilizar determinada combinação de:

* preço;
* meio de pagamento;
* parcelamento;
* condição comercial;

o sistema deve conseguir determinar se a operação é economicamente viável.

Nunca permitir silenciosamente:

```text
Taxa PegaTicket
<
custos PagBank
```

quando isso gerar prejuízo não previsto.

Criar conceito:

```text
platform_fee
gateway_fee
platform_net_revenue
```

Onde:

```text
platform_net_revenue =
platform_fee - gateway_fee
```

Preparar alertas internos para:

```text
platform_net_revenue <= 0
```

---

# 7. Split FIXED

Dar preferência a split com valores explícitos quando isso oferecer maior previsibilidade para o modelo financeiro.

Exemplo conceitual:

```text
Pedido total
R$ 110

Tenant
R$ 100

PegaTicket
R$ 10
```

O payload real deve seguir a versão atual da API PagBank.

Nunca copiar payload antigo sem verificar a documentação oficial vigente.

---

# 8. Regra de ouro sobre documentação

Antes de implementar ou alterar qualquer integração PagBank:

1. consultar a documentação oficial atual;
2. confirmar a versão da API;
3. confirmar endpoint;
4. confirmar campos obrigatórios;
5. confirmar enums;
6. confirmar headers;
7. confirmar regras de autenticação;
8. confirmar comportamento no sandbox;
9. confirmar comportamento de produção;
10. verificar mudanças recentes.

Nunca confiar cegamente em:

* documentação antiga;
* posts;
* Stack Overflow;
* snippets antigos;
* exemplos de versões anteriores;
* respostas de comunidade quando existir documentação oficial atual.

A documentação oficial PagBank é sempre a fonte primária.

---

# 9. Serviços PagBank do núcleo da integração

Para o modelo PegaTicket, considerar como núcleo:

```text
API de Pedidos e Pagamentos — Order
Split de Pagamentos — Order
API Connect
API de Cadastro — Account
API de Notificação / Webhooks
```

Dependendo das funcionalidades efetivamente implementadas, também poderá existir:

```text
API de Chargeback
API de Validação e Armazenamento de Cartões
3DS
Custódia
```

Não declarar um serviço na homologação se ele não estiver realmente implementado.

---

# 10. APIs que não devem ser confundidas

Utilizar Pix dentro de Order não significa necessariamente utilizar:

```text
API PIX dedicada
```

Da mesma forma, utilizar Split não significa utilizar:

```text
API transferência
```

Como o repasse ao tenant ocorre através do Split, não marcar API Transferência somente por existir distribuição financeira.

Selecionar no formulário de homologação somente serviços realmente utilizados.

---

# 11. Onboarding financeiro do tenant

O PegaTicket deve permitir que cada tenant configure sua conta financeira.

Fluxo:

```text
Tenant
   ↓
Configurar recebimentos
   ↓
Possui conta PagBank?
   ↓
┌──Sim────────Não──┐
↓                  ↓
Connect          Cadastro
↓                  ↓
Conta existente  Criar SELLER
└───────┬──────────┘
        ↓
Verificação
        ↓
Account ID
        ↓
Recebimentos habilitados
```

---

# 12. Tenant com conta PagBank existente

Utilizar API Connect quando adequada ao fluxo contratado.

A aplicação deve possuir fluxo de autorização.

Nunca solicitar que o tenant forneça:

* senha PagBank;
* token privado;
* credenciais bancárias;

diretamente ao PegaTicket.

Utilizar o mecanismo oficial de autorização.

Registrar:

```text
tenant_id
provider = PAGBANK
provider_account_id
connection_status
authorization_status
connected_at
verified_at
```

Tokens devem ser armazenados criptografados e protegidos.

---

# 13. Tenant sem conta PagBank

Quando a operação estiver habilitada contratualmente, utilizar API de Cadastro para criar uma conta:

```text
type = SELLER
```

Coletar somente os campos exigidos pela API vigente.

Não inventar documentos ou informações.

A criação da conta não significa automaticamente que ela está pronta para todas as operações.

Controlar:

```text
CREATED
PENDING
UNDER_REVIEW
VERIFIED
ACTIVE
RESTRICTED
REJECTED
```

Mapear os estados reais PagBank para estados internos.

---

# 14. Estado financeiro do tenant

O PegaTicket deve possuir pelo menos:

```text
NOT_CONFIGURED
PENDING_CONNECTION
PENDING_KYC
UNDER_REVIEW
ENABLED
RESTRICTED
DISABLED
```

Um tenant não pode receber vendas com split se não possuir configuração financeira válida para a operação.

---

# 15. Dados PagBank do tenant

Persistir conceitualmente:

```text
tenant_payment_accounts

id
tenant_id
provider

provider_account_id
provider_seller_id

account_type
connection_type

status
kyc_status
receiving_status

access_token_encrypted
refresh_token_encrypted

token_expires_at

connected_at
verified_at

created_at
updated_at
```

Nunca armazenar segredo em texto puro.

---

# 16. Checkout com cartão

Utilizar o mecanismo oficial PagBank para criptografia/tokenização do cartão.

Dados sensíveis devem ser tratados no navegador utilizando a solução oficial adequada.

Nunca enviar PAN ou CVV em texto puro para:

* banco PegaTicket;
* logs;
* analytics;
* Sentry;
* Datadog;
* session replay;
* arquivos de homologação;
* banco de dados;
* filas;
* eventos;
* mensagens.

Nunca armazenar CVV.

---

# 17. Idempotência

Todas as operações financeiras elegíveis devem utilizar chaves de idempotência.

Criar chave baseada na operação interna, por exemplo:

```text
payment_attempt_uuid
```

A mesma tentativa lógica deve reutilizar sua chave.

Uma nova tentativa real deve possuir nova chave.

Nunca gerar uma nova chave automaticamente apenas porque ocorreu timeout.

Antes, verificar o estado da operação.

Objetivo:

```text
1 clique
=
no máximo 1 cobrança lógica
```

---

# 18. Modelo de pedido

Todo pedido PegaTicket deve possuir identificadores próprios e externos.

Exemplo:

```text
order_id
order_uuid

pagbank_order_id
pagbank_charge_id

reference_id
```

`reference_id` deve permitir correlação segura entre PegaTicket e PagBank.

Nunca usar texto mutável como chave financeira.

---

# 19. Snapshot financeiro

Cada venda deve preservar exatamente a regra aplicada.

Salvar:

```text
ticket_face_value

platform_fee_percentage
platform_fee_minimum
platform_fee_unit
platform_fee_total

pagbank_fee_estimated
pagbank_fee_actual

tenant_gross_amount
tenant_net_amount

platform_gross_amount
platform_net_amount

buyer_total

split_method

tenant_receiver_id
platform_receiver_id

fee_rule_version
```

Não recalcular uma venda antiga utilizando uma regra nova.

---

# 20. Webhooks

Webhooks são obrigatórios para manter sincronização de estados financeiros.

Fluxo:

```text
PagBank
   ↓
Webhook PegaTicket
   ↓
validar
   ↓
identificar operação
   ↓
verificar duplicidade
   ↓
persistir evento
   ↓
processar
   ↓
atualizar pagamento
   ↓
atualizar ledger
   ↓
ações de negócio
```

Nunca confiar no redirect do navegador como confirmação financeira.

---

# 21. Idempotência de webhook

O mesmo webhook pode ser processado mais de uma vez pela infraestrutura.

Criar proteção por:

```text
provider_event_id
```

ou combinação equivalente confiável.

Persistir:

```text
provider
event_id
event_type
resource_id

payload_hash

received_at
processed_at

status
processing_error
```

Nunca duplicar:

* ingresso;
* pagamento;
* split;
* recebível;
* reembolso;
* ledger;

por webhook repetido.

---

# 22. Ledger financeiro

O PegaTicket deve possuir ledger próprio mesmo utilizando Split.

Tipos mínimos:

```text
SALE
PLATFORM_FEE
GATEWAY_FEE
TENANT_RECEIVABLE

REFUND
REFUND_REVERSAL

CHARGEBACK
CHARGEBACK_RECOVERY

ADJUSTMENT

CUSTODY
CUSTODY_RELEASE
```

O ledger deve ser append-only sempre que possível.

Não corrigir histórico financeiro simplesmente alterando valores antigos.

Criar movimentos de:

```text
REVERSAL
ADJUSTMENT
```

---

# 23. Valores financeiros do tenant

Apresentar separadamente:

```text
Vendido

A receber

Disponível

Retido

Reembolsado

Chargeback
```

Não chamar tudo de "saldo".

---

# 24. Chargeback

O agente deve considerar chargeback uma preocupação central.

No modelo Split PagBank:

```text
chargeback
→ inicialmente afeta o recebedor primário
```

O PegaTicket deve avaliar e implementar, quando comercialmente habilitado pelo PagBank, a recuperação do chargeback junto ao secundário.

No momento da criação do split, avaliar:

```text
splits.receivers
    .configurations
    .chargeback
    .charge_transfer
    .percentage
```

Nunca adicionar esse campo baseado somente neste documento.

Confirmar estrutura vigente na documentação antes de implementar.

---

# 25. Política PegaTicket para chargeback

Para um pedido associado a um único tenant:

```text
Tenant
=
responsável econômico pelo valor do ingresso
```

A PegaTicket deve definir contratualmente qual parte arca com:

* fraude;
* chargeback;
* cancelamento;
* taxa PagBank não recuperável;
* taxa PegaTicket;
* custos adicionais.

O sistema deve refletir essa política.

Não resolver política comercial apenas por código.

---

# 26. Cancelamento

Suportar:

```text
cancelamento total
cancelamento parcial
```

Em split, validar sempre a regra atual do PagBank.

O sistema deve registrar:

```text
refund_id
order_id
charge_id

requested_amount
refunded_amount

tenant_refund_amount
platform_refund_amount

gateway_fee_refund

status
requested_at
completed_at
```

---

# 27. Reembolso por ingresso

Como um pedido pode possuir vários ingressos, permitir:

```text
Pedido com 4 ingressos

reembolso de 1
```

Relacionar reembolso ao item.

Nunca calcular apenas sobre o total sem saber quais ingressos foram afetados.

---

# 28. Custódia

O PagBank possui recurso opcional de custódia associado ao split.

Não ativar automaticamente.

Criar arquitetura preparada para futura utilização em cenários como:

```text
tenant novo
evento de alto risco
volume financeiro elevado
documentação pendente
```

Quando habilitada:

```text
Pagamento
→ Split
→ Custódia
→ Liberação
```

A utilização depende das condições comerciais e técnicas disponibilizadas pelo PagBank para a conta.

---

# 29. Liable

A funcionalidade Liable é específica e não deve ser ativada por suposição.

Se for utilizada:

* consultar documentação vigente;
* validar habilitação;
* entender MCC;
* entender impacto nas bandeiras;
* entender incompatibilidades do fluxo;
* validar responsabilidade.

Não assumir que "tenant recebe mais" significa automaticamente que ele deve ser `liable`.

---

# 30. 3DS

Avaliar utilização de 3D Secure para cartão.

Quando implementado, seguir o SDK e fluxo oficial.

Não criar autenticação própria.

Considerar especialmente:

```text
cartão de débito
operações de maior risco
redução de fraude
```

Respeitar incompatibilidades documentadas entre funcionalidades.

---

# 31. ReCaptcha e segurança do checkout

O checkout deve possuir mecanismo anti-bot.

Preferencialmente:

```text
reCAPTCHA
```

ou solução equivalente aprovada pelo projeto.

Proteger especialmente:

```text
checkout
tentativas de cartão
criação de pagamentos
```

Também implementar:

```text
rate limiting
detecção de abuso
limite de tentativas
fingerprinting quando permitido
logs de segurança
proteção contra card testing
```

Nunca permitir que o checkout se transforme em ferramenta para validação automatizada de cartões.

---

# 32. Conciliação

Não depender exclusivamente dos webhooks.

Implementar rotina de reconciliação.

Comparar:

```text
PegaTicket
↕

PagBank
```

Validar:

```text
order
charge
status
split

tenant amount
platform amount

refund
chargeback

gateway fee
```

Gerar alertas para divergências.

---

# 33. Ambiente

Possuir configuração claramente separada:

```text
PAGBANK_ENV=sandbox
```

e:

```text
PAGBANK_ENV=production
```

Nunca misturar:

* token;
* conta;
* chave pública;
* aplicação Connect;
* Account ID;

entre ambientes.

---

# 34. Sandbox

Antes da homologação executar testes completos em Sandbox.

Obrigatórios:

```text
Cartão aprovado

Cartão recusado

Pix criado

Pix confirmado

Split correto

Tenant correto

Taxa PegaTicket correta

Webhook

Webhook duplicado

Timeout

Retry com idempotência

Cancelamento total

Cancelamento parcial

Chargeback quando ambiente permitir simulação

Erro de seller

Seller inexistente

Seller desabilitado

Token inválido

Payload inválido
```

Adicionar novos cenários exigidos pela documentação vigente.

---

# 35. Testes financeiros

Validar pelo menos:

```text
Ingresso R$ 0
Taxa PegaTicket R$ 0

Ingresso R$ 10
Taxa R$ 3

Ingresso R$ 20
Taxa R$ 3

Ingresso R$ 30
Taxa R$ 3

Ingresso R$ 50
Taxa R$ 5

Ingresso R$ 100
Taxa R$ 10
```

Quantidade:

```text
3 × R$ 20

tenant face value:
R$ 60

taxa PegaTicket:
R$ 9
```

Não:

```text
R$ 6
```

---

# 36. Homologação PagBank

A homologação faz parte da implementação.

A tarefa não está concluída somente quando "funciona no sandbox".

O agente deve preparar todo o pacote necessário para solicitação de homologação.

---

# 37. Regra da homologação

Antes de solicitar homologação:

```text
Implementação completa
        ↓
Testes unitários
        ↓
Testes integração
        ↓
Sandbox
        ↓
Evidências
        ↓
Ambiente acessível
        ↓
Checklist segurança
        ↓
Formulário PagBank
        ↓
Homologação
        ↓
Ajustes solicitados
        ↓
Produção
```

---

# 38. Pasta de homologação

Criar no projeto, fora do código público:

```text
docs/
└── pagbank/
    └── homologacao/
```

Exemplo:

```text
00-checklist.md

01-order/
02-split/
03-connect/
04-account/
05-webhooks/
06-cancelamentos/
07-chargeback/
08-seguranca/

requests/
responses/

sandbox/
production/
```

Nunca versionar:

* tokens;
* PAN;
* CVV;
* secrets;
* access tokens;
* refresh tokens;
* dados pessoais desnecessários.

---

# 39. Evidência de request/response

Para cada API utilizada produzir:

```text
Nome do cenário
Data

Ambiente
SANDBOX / PRODUCTION

Endpoint
Método HTTP

Request Headers sanitizados
Request Body sanitizado

HTTP Status

Response Headers relevantes
Response Body

IDs relacionados

Resultado esperado
Resultado obtido
```

---

# 40. Sanitização

Antes de anexar evidências:

Remover:

```text
Authorization
Bearer token
client_secret
access_token
refresh_token
CVV
PAN
encrypted secrets desnecessários
cookies
session
```

CPF, CNPJ, nome e endereço devem ser mascarados quando não forem necessários à análise.

Não mascarar identificadores técnicos necessários para o PagBank localizar uma operação se o suporte solicitar especificamente esses identificadores.

---

# 41. Cenários mínimos para evidências

Preparar request/response de tudo que efetivamente será homologado.

Exemplo:

```text
ORDER

- cartão
- Pix


SPLIT

- criação do pedido
- tenant
- PegaTicket
- consulta do split


CONNECT

- aplicação
- autorização
- callback
- token


ACCOUNT

- criação SELLER, se utilizada
- consulta da conta


NOTIFICAÇÃO

- webhook recebido
- processamento


CANCELAMENTO

- total
- parcial


CHARGEBACK

- somente se API correspondente estiver implementada
```

---

# 42. Formulário atual de homologação — coleta de dados

Antes de abrir o chamado, criar um arquivo:

```text
homologacao-formulario.md
```

com os seguintes campos.

## Número do Chamado

```text
#
```

Se ainda não existir, deixar como:

```text
PENDENTE
```

Nunca inventar.

---

## Qual o seu nome?

Descrição:

```text
Nome de quem está abrindo a solicitação de homologação.
```

Valor:

```text
[RESPONSÁVEL PELA SOLICITAÇÃO]
```

---

## Qual o nome da sua loja?

Preencher com o nome empresarial/comercial efetivamente utilizado.

Exemplo conceitual:

```text
PegaTicket
```

---

## Digite seu Documento

```text
CPF ou CNPJ
```

Utilizar documento da conta PagBank responsável pela integração.

---

## E-mail da conta PagBank

```text
[E-MAIL DA CONTA PRIMÁRIA]
```

---

## Está em contato com Executivo Comercial PagSeguro?

```text
Sim
Não
```

Responder conforme situação real.

Se sim, registrar também internamente:

```text
nome do executivo
e-mail
telefone
```

se disponíveis.

---

## Telefone do responsável técnico

```text
[TELEFONE]
```

---

## E-mail do responsável técnico

```text
[E-MAIL DO DESENVOLVEDOR RESPONSÁVEL]
```

---

# 43. Tipo de integração

Para o PegaTicket:

```text
Desenvolvimento próprio
```

Não selecionar:

```text
Plataformas
```

se a integração foi desenvolvida diretamente pela equipe PegaTicket utilizando as APIs.

---

# 44. Plataforma / módulo utilizado

Resposta sugerida:

```text
PegaTicket — plataforma SaaS própria de gestão,
venda e controle de ingressos, integrada diretamente
às APIs REST do PagBank.
```

Adaptar se necessário.

---

# 45. Serviços integrados

No formulário existe seleção múltipla.

As opções atuais informadas são:

```text
API de Pedidos e Pagamentos (Order)

Split de Pagamentos (Order)

API Connect

API de Cadastro (Account)

API PIX

API transferência

API Pagamento Recorrente

Checkout PagBank

API de Validação e Armazenamento de Cartões

Google Pay

Pagar com PagBank

API de Chargeback

API de Notificação
```

Para o projeto atual, a intenção é homologar pelo menos:

```text
☑ API de Pedidos e Pagamentos (Order)

☑ Split de Pagamentos (Order)

☑ API Connect

☑ API de Cadastro (Account)

☑ API de Notificação
```

Somente marcar:

```text
API de Chargeback
```

quando efetivamente integrada.

Somente marcar:

```text
API de Validação e Armazenamento de Cartões
```

se o endpoint específico desse serviço estiver sendo utilizado.

Usar criptografia de cartão no SDK do checkout não significa automaticamente que esta API específica esteja integrada.

Não marcar:

```text
API transferência
```

para representar o Split.

Não marcar:

```text
API PIX
```

apenas porque existe pagamento Pix através da API Order.

Não marcar serviços não utilizados.

---

# 46. Instruções de acesso ao ambiente

O PagBank precisa conseguir validar o fluxo.

Preparar documento:

```text
ACESSO PARA HOMOLOGAÇÃO

URL:
https://[AMBIENTE]

Usuário:
[USUÁRIO TESTE]

Senha:
[SENHA TEMPORÁRIA]

Tenant:
[TENANT TESTE]
```

E passos:

```text
1. acessar a URL;

2. autenticar;

3. acessar o evento de homologação;

4. abrir página pública;

5. adicionar ingresso;

6. iniciar checkout;

7. escolher meio de pagamento;

8. realizar cenário;

9. acessar painel financeiro;

10. visualizar pedido e split.
```

Criar credenciais exclusivas para homologação.

Não fornecer conta administrativa real se não for necessário.

Revogar credenciais temporárias depois do processo.

---

# 47. Produtos/serviços comercializados

Resposta sugerida:

```text
Venda online de ingressos para eventos através da
plataforma PegaTicket.

Os produtores/organizadores cadastrados como tenants
publicam eventos e ingressos na plataforma.

O comprador realiza a aquisição online e o pagamento
é processado pelo PagBank.

A transação utiliza Split de Pagamentos para distribuir
o valor entre o organizador do evento e a PegaTicket,
conforme as regras financeiras da plataforma.
```

Se futuramente existirem:

* estacionamento;
* produtos;
* adicionais;
* experiências;

atualizar a descrição.

---

# 48. Endereço comercial

Preencher com endereço comercial real da empresa responsável pela conta PagBank.

Nunca usar endereço de desenvolvimento ou endereço do tenant.

---

# 49. URL do site

Preencher:

```text
URL pública oficial
```

Exemplo:

```text
https://[DOMINIO-OFICIAL]
```

O ambiente de homologação pode ser informado separadamente nas instruções de acesso.

---

# 50. Segurança do checkout

Pergunta:

```text
Utiliza recaptcha ou algum outro recurso de segurança?
```

Responder conforme implementação real.

Recomendação da skill:

Antes da homologação, implementar proteção anti-bot adequada.

Se implementada:

```text
Sim
```

Nunca marcar "Sim" apenas para passar na homologação.

---

# 51. Anexos

O formulário solicita requests e responses das APIs utilizadas.

Preparar um pacote:

```text
pagbank-homologacao.zip
```

Estrutura sugerida:

```text
pagbank-homologacao/

README.md

01-order-card/
    request.json
    response.json

02-order-pix/
    request.json
    response.json

03-split/
    request.json
    response.json

04-connect/
    request.json
    response.json

05-account/
    request.json
    response.json

06-webhook/
    payload.json
    processamento.md

07-cancelamento/
    request.json
    response.json

08-chargeback/
    request.json
    response.json

09-seguranca/
    checkout.md
```

Somente incluir diretórios de serviços realmente utilizados.

---

# 52. README da homologação

O README deve explicar:

```text
Sistema:
PegaTicket

Tipo:
Desenvolvimento próprio

Ambiente:
Sandbox / Produção

Integrações:
[listar]

Modelo financeiro:
Marketplace com Split

Primário:
PegaTicket

Secundário:
Tenant / Produtor

Produtos:
Ingressos para eventos
```

Também relacionar cada arquivo de evidência ao cenário correspondente.

---

# 53. Produção

Não simplesmente trocar:

```text
sandbox
```

por:

```text
production
```

e considerar concluído.

Antes de ativar produção validar:

```text
credenciais
public key
Connect application
seller accounts
webhook URL
TLS
domínio
recaptcha
rate limits
idempotência
logs
observabilidade
alertas
conciliação
chargeback
cancelamento
split
```

---

# 54. Observabilidade

Criar métricas:

```text
pagbank_orders_total

pagbank_orders_failed

pagbank_split_failed

pagbank_webhooks_received

pagbank_webhooks_failed

pagbank_payment_approved

pagbank_payment_declined

pagbank_refunds

pagbank_chargebacks

pagbank_reconciliation_divergences
```

Alertas devem existir para erros financeiros relevantes.

---

# 55. Logs

Nunca logar dados sensíveis.

Logar identificadores:

```text
tenant_id
order_id
pagbank_order_id
charge_id
reference_id

status
http_status

duration
attempt
```

Mas nunca:

```text
PAN
CVV
token privado
Authorization
```

---

# 56. Tratamento de erro

Nunca enviar erro PagBank cru para o comprador.

Internamente guardar:

```text
provider_code
provider_message
provider_parameter
```

Para usuário, apresentar mensagem apropriada.

Exemplo:

```text
Não foi possível concluir o pagamento.
Confira os dados ou tente outra forma de pagamento.
```

Nunca afirmar "saldo insuficiente" se o PagBank não informou isso de forma apropriada para exibição.

---

# 57. Arquitetura recomendada

Organizar domínio financeiro.

Exemplo conceitual:

```text
Payments/
├── Domain/
│   ├── Payment
│   ├── Split
│   ├── Receiver
│   ├── Money
│   ├── Fee
│   └── LedgerEntry
│
├── Application/
│   ├── CreatePayment
│   ├── CalculateSplit
│   ├── ProcessWebhook
│   ├── RefundPayment
│   └── ReconcilePayment
│
├── Infrastructure/
│   └── PagBank/
│       ├── PagBankClient
│       ├── PagBankOrderService
│       ├── PagBankSplitService
│       ├── PagBankConnectService
│       ├── PagBankAccountService
│       └── PagBankWebhookService
```

Adaptar à arquitetura existente.

Não reestruturar todo projeto desnecessariamente.

---

# 58. Serviço de cálculo de split

Centralizar:

```text
PagBankSplitCalculator
```

Entrada:

```text
tickets
discounts
quantity
platform fee rule
tenant
payment method
```

Saída:

```text
buyer_total

tenant_amount

platform_fee

estimated_gateway_fee

estimated_platform_net
```

Backend é sempre a fonte da verdade.

---

# 59. Consistência matemática

Deve ser sempre verdadeiro:

```text
soma dos valores de split
=
montante a ser dividido
```

Considerar arredondamentos em centavos.

Nunca utilizar float.

Usar:

```text
integer cents
```

ou estrutura monetária segura já existente.

---

# 60. Concorrência

A criação de:

* reserva;
* pagamento;
* ingresso;

precisa ser resistente a concorrência.

Evitar:

```text
pagamento duplicado
ingresso duplicado
split duplicado
```

Usar:

* transações;
* locks quando adequados;
* constraints;
* idempotência;
* estados bem definidos.

---

# 61. Máquina de estados do pagamento

Não utilizar booleano:

```text
paid = true/false
```

Criar estados equivalentes ao domínio:

```text
CREATED
PENDING
IN_ANALYSIS
AUTHORIZED
PAID
DECLINED
CANCELED
REFUNDED
PARTIALLY_REFUNDED
CHARGEBACK
```

Mapear estados reais PagBank para estes estados internos.

Não inventar mapping sem consultar a documentação.

---

# 62. Critérios de aceite

A funcionalidade somente estará pronta quando:

* tenant possuir onboarding PagBank;
* conta existente puder ser conectada;
* nova conta SELLER puder ser criada quando aplicável;
* Account ID estiver associado ao tenant;
* Order funcionar;
* cartão funcionar;
* Pix funcionar se habilitado;
* Split funcionar;
* tenant receber seu valor correto;
* taxa PegaTicket estiver correta;
* mínimo R$ 3 estiver correto;
* ingressos gratuitos não tiverem taxa;
* custos PagBank estiverem separados;
* idempotência estiver implementada;
* webhooks estiverem implementados;
* webhook duplicado for seguro;
* cancelamento estiver implementado;
* reembolso parcial estiver implementado;
* chargeback estiver modelado;
* ledger estiver implementado;
* conciliação estiver implementada;
* logs não vazarem informações sensíveis;
* sandbox tiver cobertura;
* pacote de homologação estiver produzido;
* formulário de homologação estiver pronto;
* ambiente puder ser acessado pelo PagBank;
* documentação interna estiver atualizada.

---

# 63. Checklist final antes de pedir homologação

```text
[ ] Conta PagBank primária correta

[ ] Aplicação Connect configurada

[ ] API Account configurada

[ ] Sellers de teste funcionando

[ ] Order funcionando

[ ] Split funcionando

[ ] Cartão criptografado pelo mecanismo oficial

[ ] Pix funcionando, se utilizado

[ ] Webhook funcionando

[ ] Idempotência funcionando

[ ] ReCaptcha/anti-bot funcionando

[ ] Cancelamento funcionando

[ ] Reembolso parcial funcionando

[ ] Chargeback validado quando aplicável

[ ] Nenhum PAN em log

[ ] Nenhum CVV armazenado

[ ] Tokens protegidos

[ ] Request/response Order anexados

[ ] Request/response Split anexados

[ ] Request/response Connect anexados

[ ] Request/response Account anexados

[ ] Webhook documentado

[ ] Dados pessoais sanitizados

[ ] URL de homologação acessível

[ ] Credencial de homologação criada

[ ] Passo a passo de acesso escrito

[ ] Produtos/serviços descritos

[ ] Documento da empresa confirmado

[ ] E-mail PagBank confirmado

[ ] Responsável técnico confirmado

[ ] URL oficial confirmada

[ ] Formulário revisado

[ ] ZIP de evidências criado
```

---

# 64. Conduta durante a homologação

Se o PagBank reprovar um cenário:

1. registrar o motivo;
2. não criar workaround inseguro;
3. identificar documentação relacionada;
4. reproduzir em sandbox;
5. corrigir causa;
6. adicionar teste automatizado;
7. gerar novo request/response;
8. atualizar pacote;
9. responder ao chamado.

Toda falha de homologação deve virar teste para evitar regressão.

---

# 65. Regra final do agente

Seu objetivo não é simplesmente:

> fazer o PagBank aceitar uma requisição.

Seu objetivo é entregar:

```text
Pagamento correto
+
Split correto
+
Tenant correto
+
Taxa correta
+
Segurança
+
Idempotência
+
Auditoria
+
Chargeback
+
Cancelamento
+
Conciliação
+
Homologação
```

Antes de modificar qualquer parte sensível da integração, consulte novamente a documentação oficial PagBank, pois contratos, endpoints, campos, funcionalidades habilitadas e exigências de homologação podem mudar.

Não considere a integração concluída enquanto o fluxo financeiro completo não estiver testado e preparado para homologação.