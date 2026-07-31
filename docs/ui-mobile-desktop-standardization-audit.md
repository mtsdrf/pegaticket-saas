# Auditoria e Padrão de UI Mobile/Desktop

Data: 29 de julho de 2026

## Objetivo

Definir um padrão visual único para o Maskats, cobrindo:

- tamanho de botões
- tamanho e comportamento de inputs
- paddings e espaçamentos verticais
- grids e distribuição de colunas
- altura de cards lado a lado
- quebra e clamp de texto
- posicionamento de formulários
- responsividade mobile/desktop

Este documento é a base para a padronização visual do sistema inteiro.

## Fontes usadas nesta auditoria

### Agentes e referências internas consultadas

- [.claude/agents/ui-ux-master.md](/home/mtsdrf/workspace/maskats-saas/.claude/agents/ui-ux-master.md)
- [.claude/agents/frontend-react.md](/home/mtsdrf/workspace/maskats-saas/.claude/agents/frontend-react.md)
- [.claude/memory/design-system.md](/home/mtsdrf/workspace/maskats-saas/.claude/memory/design-system.md)
- [.claude/memory/ui-redesign-plan.md](/home/mtsdrf/workspace/maskats-saas/.claude/memory/ui-redesign-plan.md)
- [.claude/memory/brand-guidelines.md](/home/mtsdrf/workspace/maskats-saas/.claude/memory/brand-guidelines.md)
- [.claude/skills/maskats-theme-system.md](/home/mtsdrf/workspace/maskats-saas/.claude/skills/maskats-theme-system.md)
- [.claude/skills/mui.md](/home/mtsdrf/workspace/maskats-saas/.claude/skills/mui.md)
- [.claude/skills/react-frontend.md](/home/mtsdrf/workspace/maskats-saas/.claude/skills/react-frontend.md)

### Base técnica inspecionada

- tema e tokens:
  - [web/src/theme/index.ts](/home/mtsdrf/workspace/maskats-saas/web/src/theme/index.ts)
  - [web/src/index.css](/home/mtsdrf/workspace/maskats-saas/web/src/index.css)
  - [web/src/styles/surfaces.ts](/home/mtsdrf/workspace/maskats-saas/web/src/styles/surfaces.ts)
  - [web/src/styles/formFieldStyles.ts](/home/mtsdrf/workspace/maskats-saas/web/src/styles/formFieldStyles.ts)
- shells e layouts:
  - [web/src/layouts/AppLayout.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/layouts/AppLayout.tsx)
  - [web/src/components/layout/PageHeader.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/components/layout/PageHeader.tsx)
  - [web/src/components/crud/CrudListPage.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/components/crud/CrudListPage.tsx)
  - [web/src/components/crud/CrudFormShell.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/components/crud/CrudFormShell.tsx)
  - [web/src/components/crud/ServerDataGrid.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/components/crud/ServerDataGrid.tsx)
  - [web/src/pages/Settings/SettingsHubLayout.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/pages/Settings/SettingsHubLayout.tsx)
  - [web/src/components/auth/AuthPageShell.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/components/auth/AuthPageShell.tsx)
  - [web/src/pages/Portal/PortalShell.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/pages/Portal/PortalShell.tsx)
  - [web/src/pages/Accounting/AccountingShell.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/pages/Accounting/AccountingShell.tsx)

## Inventário do sistema

O diretório `web/src/pages` hoje contém mais de 100 telas distribuídas entre:

- administração
- autenticação
- dashboard
- pedidos e operação
- balcão
- PDV
- catálogo e loja online
- fiscal
- financeiro
- configurações
- contador
- portal do cliente
- analytics
- treinamento

## Arquétipos de tela já existentes

Hoje o sistema já tem alguns padrões reais, mas ainda não totalmente consolidados.

### 1. CRUD de listagem

Base principal:

- [CrudListPage.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/components/crud/CrudListPage.tsx)
- [ServerDataGrid.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/components/crud/ServerDataGrid.tsx)

Uso típico:

- clientes
- produtos
- categorias
- tipos
- usuários
- perfis
- tenants
- localidades

### 2. CRUD de formulário

Base principal:

- [CrudFormShell.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/components/crud/CrudFormShell.tsx)

Uso típico:

- formulários administrativos
- formulários fiscais
- formulários de produto
- formulários de pedido

### 3. Hub de configurações

Base principal:

- [SettingsHubLayout.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/pages/Settings/SettingsHubLayout.tsx)
- [SettingsIndexPage.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/pages/Settings/SettingsIndexPage.tsx)

### 4. Autenticação pública em duas colunas

Base principal:

- [AuthPageShell.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/components/auth/AuthPageShell.tsx)

### 5. Shells mobile-first de portal e contador

Base principal:

- [PortalShell.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/pages/Portal/PortalShell.tsx)
- [AccountingShell.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/pages/Accounting/AccountingShell.tsx)

### 6. Operação densa

Telas com comportamento mais livre e maior risco de divergência:

- pedidos
- pedidos da loja
- marketplace
- PDV
- balcão
- treinamento
- analytics

## Diagnóstico atual

### O que já está bem encaminhado

- existe um sistema de tokens `--mk-*`
- a maioria dos inputs já converge para altura mínima `44px`
- `CrudFormShell` e `CrudListPage` já ajudam a padronizar grande parte das telas
- o `PageHeader` já centraliza título, subtítulo, breadcrumb e ação primária
- o sistema está consistentemente mobile-first em boa parte dos shells

### O que ainda está inconsistente

#### 1. Raios e superfícies

Há conflito entre várias fontes:

- [design-system.md](/home/mtsdrf/workspace/maskats-saas/.claude/memory/design-system.md) fala em uma escala de radius
- [index.css](/home/mtsdrf/workspace/maskats-saas/web/src/index.css) hoje fixa `--mk-radius-sm/md/lg/xl` todos em `15px`
- [theme/index.ts](/home/mtsdrf/workspace/maskats-saas/web/src/theme/index.ts) usa `shape.borderRadius = 12`
- `MuiCard` usa `20px`
- [surfaces.ts](/home/mtsdrf/workspace/maskats-saas/web/src/styles/surfaces.ts) usa `16px`

Resultado:

- a mesma interface mistura cantos 12, 15, 16 e 20

#### 2. Altura de ações e controles

Levantamento rápido mostra múltiplas alturas em produção:

- `36`
- `40`
- `42`
- `44`
- `46`
- `48`
- `56`
- `64`

O padrão dominante é `44`, mas ainda existem muitas exceções sem regra única.

#### 3. Espaçamento

Há forte fragmentação de `spacing` e `gap`:

- `0.25`
- `0.35`
- `0.5`
- `0.6`
- `0.75`
- `0.8`
- `0.85`
- `0.9`
- `1`
- `1.1`
- `1.2`
- `1.25`
- `1.5`
- `1.75`
- `2`
- `2.2`
- `2.25`
- `2.5`
- `3`
- `4`

Isso reduz previsibilidade visual e dificulta pareamento entre cards e blocos.

#### 4. Igualdade de altura entre cards lado a lado

Hoje várias páginas já usam `grid`, mas nem sempre forçam:

- `alignItems: 'stretch'`
- card filho com `height: '100%'`
- estrutura interna com distribuição consistente

Resultado:

- cards vizinhos com títulos maiores ou textos mais longos ficam com altura diferente
- blocos lado a lado ficam “quebrados” visualmente

#### 5. Formulários ainda têm muitas composições ad hoc

Mesmo com `CrudFormShell`, vários formulários:

- definem grids próprios sem uma convenção única de colunas
- misturam campos curtos e longos sem agrupamento semântico consistente
- usam alturas corretas, mas não necessariamente o mesmo ritmo vertical

#### 6. Quebra de texto e clamp

O sistema ainda não tem uma política global clara para:

- títulos longos de cards
- descrições de ações rápidas
- cabeçalhos de cards analíticos
- textos secundários em grids e listas

Em alguns lugares há clamp, em outros não.

## Padrão proposto

## 1. Breakpoints oficiais

Adotar estes breakpoints de uso visual:

- `xs`: `< 640px`
- `sm`: `640px+`
- `md`: `900px+`
- `lg`: `1200px+`
- `xl`: `1536px+`

Regra:

- mobile define o layout base
- desktop apenas enriquece, nunca redefine a lógica visual inteira

## 2. Escala oficial de espaçamento

Padronizar para esta escala:

- `0.5` = 4px
- `1` = 8px
- `1.5` = 12px
- `2` = 16px
- `2.5` = 20px
- `3` = 24px
- `4` = 32px

Regra:

- evitar novos valores como `0.85`, `0.9`, `1.1`, `1.2`, `1.75`, `2.2`, `2.25`
- exceções só em componentes gráficos/canvas, não em layout geral

## 3. Escala oficial de altura

### Controles interativos

- botão padrão: `44px`
- botão primário importante: `48px`
- icon button: `44x44`
- chip clicável / pill tab: `40px`
- item de lista navegável: `44px`
- campo de input/select/autocomplete: `44px`
- campo de ação crítica em autenticação/checkout: `48px`

### Blocos

- card de listagem clicável: `minHeight 64px`
- quick action card: `minHeight 120px`
- KPI card: `minHeight 148px`
- dialog action bar: botões `44px`

## 4. Raios de borda

Padronizar uma única escala:

- `--mk-radius-sm`: `8px`
- `--mk-radius-md`: `12px`
- `--mk-radius-lg`: `16px`
- `--mk-radius-xl`: `20px`

Aplicação:

- inputs e botões: `12px`
- cards e surfaces: `16px`
- dialogs: `20px`
- pills e chips arredondados: `999px`

## 5. Largura e comportamento de formulários

### Regra base

- mobile: sempre um campo por linha
- tablet/desktop:
  - dois campos lado a lado para pares equivalentes
  - três colunas apenas para campos curtos
  - quatro colunas só em contextos técnicos e campos pequenos

### Regra semântica

Campos lado a lado devem representar contextos equivalentes:

- nome + status
- cidade + estado
- série + próximo número
- data inicial + data final

Não misturar na mesma linha:

- textarea alta com input curto
- upload com seletor curto
- campo sem helper text com campo com helper text longo

## 6. Igualdade de altura entre cards paralelos

Sempre que houver dois ou mais cards lado a lado:

- contêiner pai com `display: grid`
- `alignItems: 'stretch'`
- card filho com `height: '100%'`
- estrutura interna com `display: 'flex'`, `flexDirection: 'column'`

Quando houver bloco de ação no rodapé:

- usar `marginTop: 'auto'`

Resultado esperado:

- cartões com títulos ou textos diferentes continuam com mesma altura externa

## 7. Padrão de header de página

Base:

- [PageHeader.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/components/layout/PageHeader.tsx)

Padrão:

- título
- subtítulo
- ação primária
- breadcrumb ou voltar

Escala:

- margem inferior do header: `24px mobile`, `32px desktop`
- subtítulo com largura máxima controlada
- ação primária full width no mobile quando fizer sentido

## 8. Padrão de listagem

Base:

- [CrudListPage.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/components/crud/CrudListPage.tsx)

Padrão:

- header
- toolbar
- card da listagem
- grid
- paginação

Escala:

- spacing entre header e toolbar: `16px`
- spacing entre toolbar e card: `20px`
- padding interno do card: `16px mobile`, `20px desktop`

## 9. Padrão de formulário

Base:

- [CrudFormShell.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/components/crud/CrudFormShell.tsx)

Padrão:

- largura máxima do formulário controlada
- campos com ritmo visual uniforme
- ações no final sempre na mesma ordem

Escala:

- padding interno: `16px mobile`, `24px desktop`
- gap entre grupos: `20px`
- gap entre campos da mesma linha: `16px`
- gap entre formulário e botões: `24px`

## 10. Padrão de texto

### Títulos

- nunca mais que 2 linhas em cards
- usar clamp quando o card não puder crescer livremente

### Descrições

- texto secundário padrão em `13px` ou `14px`
- usar clamp de 2 ou 3 linhas em cards navegáveis

### Labels

- sempre acima do campo
- helper text deve ocupar a mesma linha-base visual em grupos equivalentes

## 11. Padrão de inputs

Todo input deve compartilhar:

- altura `44px`
- padding vertical uniforme
- label shrink uniforme
- helper/error text com a mesma distância
- largura `100%` dentro do container

Tipos cobertos:

- `TextField`
- `Select`
- `Autocomplete`
- `Date field`
- `Password field`
- `Search field`

## 12. Padrão de ações

### Ação primária

- `variant="contained"`
- altura `48px` em fluxos principais
- altura `44px` em contextos secundários

### Ação secundária

- `outlined` ou `soft`
- altura `44px`

### Ação icônica

- `44x44`

## 13. Padrão de dialog

- largura controlada por tipo
- corpo com `padding 20px`
- `Stack spacing={2}`
- botões no footer com altura `44px`
- não misturar botões `40`, `44` e `56` no mesmo ecossistema

## 14. Padrão de telas especiais

### Autenticação

- manter duas colunas só em `md+`
- mobile sempre coluna única
- card central com largura máxima previsível

### Portal e contador

- navegação em tabs/pills com altura `40px`
- conteúdo centralizado em largura fixa de leitura

### Operação densa

PDV, balcão, pedidos e marketplace podem ter exceções de densidade, mas ainda precisam respeitar:

- alturas de botão
- escala de spacing
- bordas/radius
- comportamento de grid
- igualdade de altura em cards comparáveis

## Principais divergências encontradas

### Nível 1: estrutural

- tokens de radius conflitantes entre CSS global, tema MUI e surfaces
- altura de botões e itens de navegação variando demais
- grids de formulários criados repetidamente em cada tela

### Nível 2: visual

- cards lado a lado sem garantia de mesma altura
- subtítulos e descrições sem clamp consistente
- tabs e pills variando entre `36`, `40` e `44`

### Nível 3: manutenção

- muitos `gap` e `spacing` fracionados sem escala oficial
- padrões similares reescritos em várias páginas

## Proposta de padronização por fases

### Fase 1: fundação

- unificar tokens de radius
- unificar alturas oficiais
- criar escala oficial de spacing
- criar helpers de layout reutilizáveis

### Fase 2: shells

- consolidar `PageHeader`
- consolidar `CrudListPage`
- consolidar `CrudFormShell`
- consolidar shells de auth, portal, contador e settings

### Fase 3: formulários

- criar utilitário padrão de grid de formulário
- criar grupos semânticos de campos
- aplicar a todos os formulários

### Fase 4: cards e dashboards

- normalizar KPI cards
- normalizar action cards
- normalizar cards de resumo e integração

### Fase 5: operação densa

- pedidos
- pedidos da loja
- marketplace
- PDV
- balcão
- treinamento
- analytics

## Proposta de artefatos a criar na implementação

- `web/src/styles/layoutStandards.ts`
  - larguras máximas
  - paddings padrão
  - gaps padrão
  - grids padrão
- `web/src/styles/sizeStandards.ts`
  - alturas padrão
  - tamanhos de ação
  - densidade por contexto
- `web/src/styles/textStandards.ts`
  - clamps
  - estilos de subtítulo
  - estilos de helper text
- `web/src/components/layout/SectionCard.tsx`
  - card padrão com altura uniforme
- `web/src/components/form/FormGrid.tsx`
  - grid semântico para formulários

## Checklist de aceite visual

- dois cards lado a lado com mesmo papel têm a mesma altura externa
- botões primários e secundários seguem alturas fixas oficiais
- inputs de texto, select e autocomplete têm a mesma altura
- formulários em mobile têm um campo por linha
- listas e dashboards não têm overflow visual de texto
- subtítulos e descrições longas têm clamp ou largura controlada
- nenhuma tela nova usa `spacing` arbitrário fora da escala oficial
- nenhum bloco paralelo depende da altura natural do conteúdo para alinhar

## Conclusão

O sistema já tem uma base visual boa o suficiente para consolidar um design system operacional, mas ainda carrega inconsistências importantes de:

- raio
- altura
- spacing
- grid
- equalização de cards

O caminho recomendado não é corrigir tela por tela aleatoriamente.

O caminho correto é:

1. padronizar tokens e primitivas
2. padronizar shells
3. padronizar formulários
4. padronizar cards
5. só então refinar os módulos densos

Assim conseguimos estabilizar mobile e desktop ao mesmo tempo, com um padrão único de implementação visual.

