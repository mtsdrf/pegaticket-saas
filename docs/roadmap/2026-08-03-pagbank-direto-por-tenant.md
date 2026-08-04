# PegaTicket — PagBank Direto por Tenant

Data de referência: **3 de agosto de 2026**

> **Status da decisão em 3 de agosto de 2026:** este documento passa a representar uma **trilha exploratória/secundária**, não o modelo principal aprovado para a Fase 5. O desenho principal foi formalizado em [2026-08-03-modelo-de-repasse-aprovado.md](/home/mtsdrf/workspace/pegaticket-saas/docs/roadmap/2026-08-03-modelo-de-repasse-aprovado.md).

## Decisão de produto

- a taxa da plataforma será um valor fixo em BRL;
- essa taxa será configurada pelo administrador global do sistema;
- a taxa será igual para todas as empresas;
- a taxa pode ser `R$ 0,00` ou maior.

## Modelo de integração escolhido

O primeiro modelo a ser suportado é:

- a empresa informa o próprio token PagBank no momento de integração;
- as cobranças dessa empresa passam a usar esse token;
- o valor da venda vai direto para a conta PagBank da empresa;
- a plataforma não faz repasse nesse fluxo.

## Viabilidade técnica

O fluxo é tecnicamente viável porque a API de Orders do PagBank opera com `Bearer token` da conta que recebe a venda. Isso permite:

- checkout e tokenização usando a credencial da própria empresa;
- criação de cobranças diretamente na conta dela;
- webhook e reconciliação usando a mesma credencial do seller.

O modelo também é compatível com uma evolução futura para **PagBank Connect**, caso a plataforma queira substituir o cadastro manual do token por autorização estruturada do seller.

## Limite importante deste modelo

Quando o dinheiro vai direto para a conta da empresa:

- não existe repasse a ser executado pela plataforma;
- a taxa fixa global da plataforma não fica automaticamente retida nesse mesmo fluxo;
- para retenção automática, será necessário estudar split ou outro arranjo operacional/contratual equivalente.

Em outras palavras: o modelo resolve o recebimento direto da empresa agora, mas **não resolve sozinho** a cobrança automática da taxa da plataforma.

## Primeira fatia técnica implementada

- novos campos em `tenant_settings` para modo de integração, ambiente e token PagBank por tenant;
- token PagBank salvo criptografado em repouso;
- token nunca retornado em claro pela API;
- token mascarado na trilha de auditoria;
- checkout do PagBank por venda usando credencial do tenant quando configurada;
- criação de cobrança usando credencial do tenant quando configurada;
- webhook e reconciliação usando a credencial da empresa associada à venda;
- fallback para a credencial global existente durante a transição.

## Próximos passos

1. criar o configurador global da taxa fixa no administrativo da plataforma;
2. decidir como a plataforma cobrará essa taxa no fluxo de recebimento direto;
3. só então avançar em `receivables`, `settlements` e `ledger`.
