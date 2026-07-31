---
name: design-system
description: Design system oficial da Maskats — paleta, tokens, tipografia e regras visuais para toda interface do produto.
metadata:
  type: project
---

# Maskats — Design System

## Objetivo

Definir os padrões visuais oficiais da Maskats para manter consistência entre login, dashboard, componentes, tema claro e tema escuro. Este arquivo é a referência de destino (target state) — ver [[ui-redesign-plan]] para o diagnóstico do estado atual e o plano de migração.

## Estilo visual

O visual da Maskats deve ser:

- Moderno.
- Limpo.
- SaaS.
- Profissional.
- Elegante.
- Levemente premium.
- Funcional.
- Acessível.
- Responsivo.
- Sem aparência de sistema antigo.

Inspiração de qualidade (não copiar): Linear, Vercel, Stripe, Notion, Figma, Apple.

> **Mobile-first é requisito de produto, não polish.** O uso majoritário do Maskats é no celular (confirmado pelo usuário em 2026-07-05) — toda tela é projetada primeiro para mobile e só depois enriquecida para tablet/desktop, nunca o inverso. Ver detalhamento em "Layout" e "Responsividade" abaixo, e [[ui-redesign-plan]] para como isso afeta Navbar/Sidebar/Dashboard (ainda pendentes).

## Paleta oficial

### Light mode

```txt
Background:      #F6F8FB
Surface:         #FFFFFF
Surface Soft:    #EEF3F8
Primary:         #0F3D5E
Primary Hover:   #0B314C
Secondary:       #2563EB
Accent:          #22C7A9
Text:            #102033
Muted Text:      #64748B
Border:          #D8E0EA
Success:         #16A34A
Warning:         #F59E0B
Danger:          #DC2626
Info:            #0284C7
```

### Dark mode

```txt
Background:      #07111F
Surface:         #0D1B2E
Surface Soft:    #13243A
Primary:         #38BDF8
Primary Hover:   #7DD3FC
Secondary:       #60A5FA
Accent:          #2DD4BF
Text:            #F8FAFC
Muted Text:      #94A3B8
Border:          #24364D
Success:         #22C55E
Warning:         #FBBF24
Danger:          #F87171
Info:            #38BDF8
```

## Tokens CSS recomendados

Ver detalhamento completo (valores, aplicação, persistência de tema) em `.claude/skills/maskats-theme-system.md`. Lista de tokens obrigatórios:

```txt
--mk-bg
--mk-surface
--mk-surface-soft
--mk-primary
--mk-primary-hover
--mk-secondary
--mk-accent
--mk-text
--mk-muted
--mk-border
--mk-success
--mk-warning
--mk-danger
--mk-info
--mk-radius-sm
--mk-radius-md
--mk-radius-lg
--mk-radius-xl
--mk-shadow-sm
--mk-shadow-md
--mk-shadow-lg
--mk-focus-ring
```

Nunca hardcodar hex de cor em componente — sempre `var(--mk-*)`.

## Tipografia

- Interface (corpo, labels, botões, tabelas): **Inter**.
- Marca/logo/wordmark: **Manrope SemiBold**, **Geist SemiBold** ou **Inter SemiBold** (nessa ordem de preferência).

## Hierarquia tipográfica

```txt
H1 (título de página):      28–32px, semibold, --mk-text
H2 (título de seção/card):  20–22px, semibold, --mk-text
H3 (subtítulo/label grande): 16–18px, medium, --mk-text
Body (texto padrão):         14–15px, regular, --mk-text
Small (texto secundário):    13px, regular, --mk-muted
Label (campo de formulário): 13px, medium, --mk-text
Métrica/número grande:       32–40px, semibold, --mk-text
```

Evitar mais de 4 tamanhos distintos numa mesma tela. Peso de fonte consistente: `regular` (texto), `medium` (labels/ênfase leve), `semibold` (títulos/CTAs) — nunca `bold` puro nem múltiplos pesos concorrendo no mesmo bloco.

## Layout

- **Mobile-first**: escrever o CSS base para a tela pequena (coluna única, elementos empilhados) e usar `min-width` media query para acrescentar colunas/sidebar/densidade em telas maiores — nunca partir do desktop e "encolher".
- Container principal com largura máxima confortável para leitura (~1280px em telas grandes), respiro lateral generoso — mas sem padding/margem excessiva em telas pequenas (usar escala menor de espaçamento abaixo de 640px).
- Grid/flexbox para cards e métricas, nunca tabela de layout.
- Espaçamento em escala consistente (base 4px: 4/8/12/16/24/32/48).
- Breakpoints: mobile (<640px, caso base/default), tablet (640–1024px), desktop (>1024px).
- Sidebar em mobile não é "a mesma sidebar encolhida" — vira drawer (overlay) ou bottom navigation; decidir ao implementar Navbar/Sidebar (ver [[ui-redesign-plan]]).
- Alvo de toque mínimo de ~44px em qualquer elemento clicável (botão, input, item de lista, ícone de ação) — crítico porque a maioria dos usuários está no celular, não no mouse.
- Nunca depender só de `:hover` para revelar ação importante (menu, ações de linha) — precisa funcionar por toque/tap também.

## Botões

- **Primary**: fundo `--mk-primary`, texto claro, hover `--mk-primary-hover`. Ação principal da tela, no máximo uma por contexto.
- **Secondary**: fundo `--mk-surface-soft` ou borda `--mk-border`, texto `--mk-text`. Ações alternativas.
- **Ghost/texto**: sem fundo, usado para ações terciárias (ex.: "Atualizar sistema").
- **Danger**: fundo/texto `--mk-danger`, só para ações destrutivas com confirmação.
- Estados obrigatórios: default, hover, focus (`--mk-focus-ring`), disabled, loading (spinner + texto mantido ou "…").
- Nunca botão azul chapado gigante disputando atenção com tudo na tela — um CTA primário por vez.

## Inputs

- Fundo `--mk-surface`, borda `--mk-border`, texto `--mk-text`, placeholder `--mk-muted`.
- Focus: borda `--mk-primary` + `--mk-focus-ring`.
- Erro: borda `--mk-danger` + mensagem curta abaixo do campo, cor `--mk-danger`.
- Disabled: opacidade reduzida, cursor `not-allowed`.
- Label sempre visível acima do campo (nunca só placeholder como label).

## Cards

- Fundo `--mk-surface` (ou `--mk-surface-soft` para cards de destaque secundário), borda `--mk-border`, `--mk-radius-lg`, `--mk-shadow-sm`.
- Padding interno confortável (16–24px), título com hierarquia clara (H2/H3), conteúdo com respiro.
- Card de métrica: número grande + label pequeno + (opcional) indicador de tendência usando `--mk-success`/`--mk-danger` — nunca cor decorativa solta.

### Regra obrigatória: foto de produto/entidade em card tem tamanho fixo

**A div/box que envolve a imagem define o tamanho (px fixo ou `aspectRatio`); a imagem se ajusta a ela — nunca o inverso.** Container com `width`/`height` fixos (ou `aspectRatio: '1/1'` + `width:'100%'`) + `overflow: 'hidden'`; a imagem em si sempre `width: '100%', height: '100%', objectFit: 'cover'`. Nunca deixar a imagem "empurrar" a altura do card (sem `width:'auto'`/`height:'auto'` soltos numa `<img>` dentro de um card de lista/grid). Padrão já em uso e a ser seguido em qualquer novo card de produto/entidade: `components/storefront/ProductGridCard.tsx` (`aspectRatio:'1/1'`), `components/storefront/ProductListItem.tsx` (`{ xs: 88, sm: 104 }` fixo), `components/shared/ImageUploadField.tsx` (`size` fixo). Badges/selos que sobrepõem a foto (`position:'absolute'`) não contam pro tamanho do container — a foto nunca redimensiona por causa deles.

### Badges sobre a foto do card de produto (2026-07-30)

`ProductCardBadges` tem `variant="overlay"` (usado por `ProductGridCard`, `position:'absolute'` sobre a imagem, fundo sólido + sombra pra destacar em qualquer foto) e `variant="inline"` (usado por `ProductListItem`, acima do nome, fundo translúcido — mantido porque ali a imagem é pequena/lateral, não teria espaço legível pro selo por cima). `ProductGridCard` aceita `excludeBadges` pra omitir selo redundante conforme o contexto — usado pelo rail "Mais vendidos" (`BestSellersRail`) pra não repetir o selo `best_selling` num card que já está dentro da seção "Mais vendidos".

## Sidebar

- Fundo `--mk-surface` (ou tom levemente distinto de `--mk-bg`), borda direita `--mk-border`.
- Item ativo com destaque claro: fundo `--mk-surface-soft` + texto/ícone `--mk-primary`, nunca só uma mudança sutil demais para notar.
- Ícones com propósito (não decorativos), rótulos sempre visíveis em desktop; colapsa para ícones-apenas ou drawer em mobile/tablet.

## Navbar

- Leve, sem excesso de elementos: logo/wordmark compacto, ação de contexto (ex.: tenant ativo), avatar/menu do usuário.
- Fundo `--mk-surface`, borda inferior `--mk-border`, sem sombra pesada.

## Login

- Fundo com gradiente sofisticado usando a paleta oficial (ex.: `--mk-bg` → `--mk-primary` em baixa opacidade), elementos abstratos sutis de movimento — nunca imagem financeira genérica de banco de imagens.
- Card de login centrado, `--mk-surface`, `--mk-radius-lg`, `--mk-shadow-md`.
- Logo Maskats visível acima do formulário.
- Textos oficiais: ver [[brand-guidelines]] → "Exemplos de texto → Login".

## Dashboard

- Separação clara de blocos: cabeçalho da página → ações rápidas → métricas → gráfico → navegação (sidebar/navbar já fora do conteúdo).
- Título "Visão geral" + subtítulo, cards de ação rápida (Novo pedido / Adicionar cliente / Cadastrar produto), cards de métrica (Pedidos entregues / Pedidos pendentes / Valor recebido), gráfico de pedidos por mês dentro de um card.
- Evitar: botões azuis gigantes, cards azuis pesados repetidos, azul chapado em todo bloco, gráfico sem título/contexto, sidebar sem estado ativo evidente.

## Acessibilidade

- Contraste mínimo AA em texto sobre `--mk-bg`/`--mk-surface` em ambos os temas.
- Foco visível (`--mk-focus-ring`) em todo elemento interativo.
- Labels reais em todo input, `aria-*` quando necessário, navegação por teclado funcional.
- Nunca comunicar estado só por cor (ex.: erro também tem ícone/texto, não só borda vermelha).

## Responsividade

- **Mobile é o caso primário de uso**, não o caso extra — validar mobile antes de considerar qualquer tela pronta, não só "depois de fazer o desktop".
- Toda tela funciona em desktop grande, notebook, tablet e mobile, mas o design nasce no mobile (ver "Layout").
- Sidebar e navbar viram drawer/bottom-nav em mobile (não "colapso" sutil) antes de qualquer conteúdo quebrar.
- Cards de métrica empilham em coluna única em mobile; gráfico se redimensiona, nunca vaza da viewport nem exige scroll horizontal da página inteira.
- Tabela/lista de dado denso precisa de estratégia mobile explícita (cards empilhados, ou scroll horizontal contido só na tabela) — nunca simplesmente encolher fonte até ficar ilegível.
- Formulário em mobile: um campo por linha, teclado adequado ao tipo de dado (`type="email"`, `inputmode="numeric"` etc.), botão de ação sempre alcançável sem zoom.

### Regra obrigatória: grid de cards nunca deixa item órfão

**Verificar sempre que uma tela for criada ou alterada, antes de considerar pronta.** Causa raiz real (Dashboard, 2026-07-20): `gridTemplateColumns: repeat(auto-fit, minmax(min(100%,260px),1fr))` decide o nº de colunas pela LARGURA disponível, não pela contagem de cards — nada garante que o total seja múltiplo do nº de colunas que sobra naquela largura. Com 5 cards, uma largura que comporta 4 colunas de ≥260px deixa o 5º sozinho, esticado (`1fr`) numa linha própria — feio e não intencional.

- Nunca usar `auto-fit`/`auto-fill` cru para um grid de cards de **contagem fixa e conhecida** (KPIs, resumos) sem antes verificar todos os breakpoints.
- Preferir colunas explícitas por breakpoint que DIVIDAM EXATAMENTE a contagem de cards daquela seção — **mas o nº de colunas não é só "divide certinho", também precisa caber o conteúdo do card sem espremer** (ajustado em 2026-07-20: 6 KPI cards em `lg: repeat(6,1fr)` cabiam matematicamente sem sobra, mas espremiam valor+label+caption a ponto de ficar ilegível; trocado pra travar em no máximo 3 colunas, `md: repeat(3,1fr)` sem override de `lg`/`xl`, deixando 6 cards em 2 linhas de 3):
  ```tsx
  sx={{
    display: 'grid',
    gridTemplateColumns: { xs: 'repeat(1,1fr)', sm: 'repeat(2,1fr)', md: 'repeat(3,1fr)' },
    gap: 1.5,
  }}
  ```
  Regra prática: **3 colunas é o teto confortável para `MetricCard`** (valor grande + label + caption); só usar mais colunas por linha se o card for bem mais simples (ícone + número curto, sem caption longa). Continuar exigindo que `N` (contagem de cards) seja múltiplo do nº de colunas escolhido em cada breakpoint — isso não muda, só o teto de colunas simultâneas mudou.
  onde `N` é a contagem de cards da seção. Ao decidir quantos cards uma seção nova vai ter, preferir números com muitos divisores (4 ou 6) — facilita caber sem sobra em 1/2/3/4/6 colunas.
- `auto-fit`/`auto-fill` continua aceitável para listas de tamanho VARIÁVEL/desconhecido (ex. filtros dinâmicos, tags) — o problema é só quando a contagem é fixa e conhecida em tempo de build.

## Biblioteca de componentes

A partir de 2026-07-05, **Material UI (MUI)** é a biblioteca de componentes/estilização do projeto (ag-Grid para tabelas/grids, Chart.js para gráficos) — ver decisão completa em [[architecture-decisions]] e regras de uso em `.claude/agents/react-19-master.md`. O tema MUI é construído a partir da paleta e tokens deste arquivo, não os substitui: a marca/paleta continua sendo definida aqui, o MUI só passa a ser o mecanismo de implementação. Desde 2026-07-09, 100% das telas (incluindo Login, migrada nessa data) usam MUI — os primitivos antigos `components/ui/{Button,Card,Input}` foram removidos.

## Alternância de tema (claro/escuro/sistema)

Desde 2026-07-09: `ThemeModeProvider` (`contexts/ThemeModeProvider.tsx`) é a fonte única do modo de tema — resolve `light`/`dark`/`system` (preferência salva em `localStorage['maskats.theme_mode']`, default `system` = `prefers-color-scheme`) e escreve o atributo `data-theme` no `<html>`. É crítico que `buildMaskatsTheme` (paleta MUI) e os tokens CSS `--mk-*` concordem sobre o modo — antes dessa mudança o MUI lia só `prefers-color-scheme` direto (sem estado, sem toggle, sem persistência); agora os dois leem do mesmo lugar. Toggle visível no `AppBar` (`components/ThemeModeToggle.tsx`, menu com Claro/Escuro/Sistema). Qualquer novo ponto de entrada da aplicação (ex.: uma segunda árvore de rotas) precisa estar dentro do `ThemeModeProvider`, nunca ler `prefers-color-scheme` isoladamente de novo.

## Regras de consistência

- Nenhuma cor fora da paleta oficial (`--mk-*` / tema MUI derivado dela) em componente novo.
- Mesmo conjunto de radius/shadow em todos os cards do sistema (não inventar variação por tela).
- Tema claro e escuro sempre revisados juntos antes de considerar uma tela pronta.
- Qualquer decisão visual nova relevante é registrada aqui, de forma curta, não deixada apenas no código.

## Menu de conta (2026-07-14)

- O ícone de usuário do header (`UserMenu.tsx`) é o lugar único com: avatar+nome+e-mail, empresa ativa+plano (`TenantMenu` variant `menu`), tema (`ThemeModeSwitch`, grupo ícone-switch-ícone centralizado), "Meus dados" e "Sair" — nessa ordem. A sidebar (`AppLayout.tsx` → `SidebarContent`) ficou só com navegação, sem duplicar empresa/tema no rodapé.
- `PlanChip` do plano **Diamante** trocou de dourado/âmbar pra **rubi/ametista** (pra diferenciar melhor do plano Ouro, que ficou com o dourado): `color #7A1152`, borda `color-mix(in srgb, #9F1D6B 45%, white)`, fundo `linear-gradient(135deg, color-mix(in srgb, #F6D5E6 65%, white), color-mix(in srgb, #9F1D6B 30%, white))` — mesmo estilo de gradiente dos outros planos, só a cor base muda. Ver `components/TenantMenu.tsx`.
- Avatar do usuário (`components/UserAvatar.tsx`): foto (`avatar_url`) quando existe, senão iniciais (`utils/initials.ts`) sobre `--mk-primary`; fallback de ícone genérico só quando nome ainda não carregou.
- Dados de perfil (nome/e-mail/avatar/`pending_email`) não vêm do `AccessProfile` (só permissões) — vêm de `GET /auth/profile`, compartilhados entre `UserMenu` e `MyAccountPage` via `UserProfileContext`/`useUserProfile` (irmão do `AuthContext`, também instanciado em `app/App.tsx`) pra que editar nome/foto em "Meus dados" reflita no header sem reload.

## Exceção mobile-first: módulo PDV (2026-07-22)

O PDV (`web/src/pages/Pdv/*`, rota `/pdv`) é a **primeira tela desktop/teclado-first** do projeto — exceção CONSCIENTE à prioridade mobile-first geral do Maskats. Ela é desenhada para um balcão fixo com teclado + leitor de código de barras, não para o celular:

- **Prioridade de layout invertida**: layout denso, campo de busca sempre focado, atalhos de teclado (`F2` foca busca, `F4` finaliza venda, `Delete`/`Backspace` remove item selecionado — ver `hooks/usePdvHotkeys.ts` e nota no `PdvSalePage`). Fluxo pensado para operar sem mouse.
- **Continua responsivo, não abandonado**: usa grid `1fr 360px` que colapsa para coluna única em telas menores; a exceção é de PRIORIDADE de design, não abandono de responsividade. Não trava nem estoura em mobile.
- **Tokens `--mk-*` normais**: só o layout/interação muda de prioridade — cores, radius, shadow, tema claro/escuro seguem exatamente o design system (nada de hex hardcoded).
- **Recibo (`PdvReceiptPrintView`)**: cupom não-fiscal estreito (80mm), `@media print` oculta AppBar/sidebar (`body * { visibility: hidden }` + `#pdv-receipt` visível) e imprime só o cupom via `window.print()`.

Regra: se surgir outra tela de operação de balcão (ex.: Balcão/garçom), pode seguir esta mesma exceção; qualquer OUTRO tipo de tela continua mobile-first por padrão.

### Módulo Balcão — mesas/comanda mobile-first, KDS tela-fixa (2026-07-22)

O módulo Balcão (`web/src/pages/Balcao/*`) tem DOIS perfis de tela distintos, cada um com sua diretriz:

- **App do garçom (`BalcaoTablesPage` `/balcao/mesas`, `BalcaoComandaPage` `/balcao/comandas/:uuid`)**: MOBILE-FIRST de verdade — é operado no celular/tablet do garçom andando pelo salão. Grid de mesas com `repeat(auto-fill, minmax(150px, 1fr))` (auto-fill, não auto-fit — regra do [[feedback_card_grid_layout]], sem item órfão esticado), cards com alvo de toque ≥96px, cor por status via token (`TABLE_STATUS_META`/`PREP_STATUS_META` em `constants/balcao.ts`, nunca hex). O fechamento (`CloseComandaModal`) reaproveita o padrão de split de pagamento do PDV, somando taxa de serviço (aceitar/recusar) e divisão em N partes.
- **KDS (`BalcaoKdsPage` `/balcao/kds`)**: EXCEÇÃO DE DESIGN CONSCIENTE — tela FIXA de cozinha/bar, leitura à DISTÂNCIA. Não é mobile-first nem desktop-denso: é um painel montado numa TV/tablet parado. Diretrizes próprias: fonte grande (produto em 22px, coluna/tempo em 18-20px), alto contraste, botão único GRANDE de avançar status por ticket (`py: 1.25`, 16px), colunas por status (`sent_to_station`/`preparing`/`ready`) lado a lado no desktop e empilhadas em mobile. Polling via `setInterval` no MESMO intervalo do resto do projeto (`POLL_INTERVAL_MS = 30000`, igual `StorefrontOrderManagementPage`). Tempo de espera em vermelho (`--mk-danger`) a partir de 15min. Estação selecionável no topo OU fixada via query param `?station=uuid` (para deixar o painel travado numa estação).

Regra: telas de acompanhamento passivo montadas em display fixo (tipo KDS/painel de senha) seguem o perfil "tela-fixa" (fonte grande, alto contraste, sem depender de toque preciso); telas operadas na mão do funcionário seguem mobile-first normal.

## Propagação final dos tokens de layoutStandards (2026-07-30)

`web/src/styles/layoutStandards.ts` (criado no commit `25279b9 Padronização das telas`: `PAGE_CONTAINER_SX`, `UI_RADIUS`, `UI_SIZE`, `FORM_GRID_2_SX`/`FORM_GRID_3_SX`, `CARD_EQUAL_HEIGHT_SX`, `CLAMP_TEXT_2_SX`/`CLAMP_TEXT_3_SX`, `SECTION_CARD_PADDING_SX`) foi propagado para as últimas telas do inventário de `docs/ui-global-screen-map.md` que ainda tinham valor solto (raio/container/grid/tamanho de controle hardcoded) em vez do token — trabalho feito em 3 lotes paralelos cobrindo núcleo operacional/auth público, loja pública/PDV/Balcão/SocialMedia, e Configurações/Contador/Analytics. CRUD (`CrudListPage`/`CrudFormShell`/`SchemaFormPage`), Portal (`PortalShell`) e Contador (`AccountingShell`/`AccountingCompanyLayout`) já herdavam o padrão via shell, sem precisar de edição própria.

Regra confirmada durante o trabalho: **nem todo arquivo do inventário tinha correspondência real com um token** — telas de auth com layout split-screen (`SignupPage`, `ForgotPasswordPage`, `ResetPasswordPage`, `AcceptInvitePage`, `ConfirmEmailPage`, `PortalLoginPage`) e alguns blocos de Configurações (`CashbackBlock`, `OperationsBlock`, `PaymentBlock`, `RetentionBlock`, `ScheduleAddressBlock`) foram deliberadamente deixados intactos por não terem valor solto mapeável 1:1 pra um token existente — forçar o token ali teria mudado a proporção/raio pretendido. Não criar token novo nem reestruturar essas telas "pra caber" sem essa ser uma decisão own à parte.

## Remoção de gradientes em cards e botões (2026-07-30)

Decisão explícita do usuário: gradiente linear/radial não é mais usado em **cards e botões** do produto — fundo sólido (`var(--mk-*)`/`color-mix`) no lugar. Escopo confirmado como restrito a componentes de interface (cards `Paper`/`Card`, chips, botões de seleção); telas de marca/hero (login, signup, convite, confirmação de e-mail — `AuthBrandPanel` e cópias inline em `LoginPage`/`SignupPage`/`ConfirmEmailPage`/`AcceptInvitePage`), o hero do `TrainingCenterPage`, banners (`ConnectionStatusBanner`), linhas decorativas (`PageHeader` underline, divisores) e o conteúdo gerado de story para redes sociais (`StoryCanvas`/`StorySingleBody`) **mantêm gradiente** — não fazem parte do escopo "cards ou botões".

Tokens `--mk-surface-raised-bg`/`--mk-surface-soft-bg` (usados por praticamente todo card elevado: `MetricCard`, `QuickActionCard`, `CrudListPage`, `UserMenu`, `SettingsHubLayout`) achatados pra `var(--mk-surface)`/`var(--mk-surface-soft)` puro em `index.css` — cobre a maioria dos cards do produto num único ponto. `--mk-page-background*`/`--mk-sidebar-background`/`--mk-decorative-overlay` (fundo de página/sidebar, não é card) permanecem com gradiente. Qualquer card/botão novo que usar gradiente de fundo deve ser convertido pra `color-mix(in srgb, TOKEN X%, var(--mk-surface))` sólido (ou o token direto), seguindo o mesmo padrão aplicado em `TenantMenu` (PlanChip/botão/menu), `OnboardingChecklistCard`, `DashboardPage`, `OrderListPage`, `ReceivablesAgingCard`, `RouteResultStopCard`, `BillingPeriodOptionCards`, `SubscriptionPage` e os cards de trilha/módulo/quiz do `TrainingCenterPage` (inclui remoção do efeito de shimmer/sweep animado, que também usava gradiente).
