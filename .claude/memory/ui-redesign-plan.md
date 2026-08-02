---
name: ui-redesign-plan
description: Plano de rebranding visual da PegaTicket (login e dashboard) — diagnóstico, escopo e ordem de execução. Ainda não implementado.
metadata:
  type: project
---

# PegaTicket — Plano de Redesign de UI

## Objetivo

Aplicar a nova identidade visual da PegaTicket (ver [[brand-guidelines]] e [[design-system]]) às telas existentes de `web/`, sem alterar nome do sistema, autenticação, rotas, permissões, API ou banco de dados. Rebranding é puramente visual.

**Restrição de produto (2026-07-05, confirmada pelo usuário como "extremamente importante"): o uso majoritário do PegaTicket é no celular.** Toda etapa deste plano — especialmente Navbar/Sidebar/Dashboard, ainda pendentes — deve ser desenhada mobile-first (base para tela pequena, enriquecida para desktop depois), não o inverso. Ver [[design-system]] → "Layout"/"Responsividade" para as regras concretas (alvo de toque ≥44px, sidebar vira drawer/bottom-nav em mobile, sem dependência de hover).

## Escopo inicial

- Tela de login (`web/src/pages/Login/`).
- Dashboard/home (`web/src/pages/Dashboard/`) — hoje é na prática um seletor de tenants, vai virar a base da home real (header, ações rápidas, métricas, gráfico).
- Navbar e sidebar (ainda não existem como componentes próprios — serão criados).
- Tokens de tema (`web/src/index.css`) e primeiros componentes base (`components/ui/`).
- Favicon/logo (versão inicial simplificada do símbolo M).

## Fora do escopo (não tocar neste plano)

- Lógica de autenticação (`AuthContext`, `apiClient`, `authService`).
- Rotas (`AppRoutes`, `ProtectedRoute`).
- Permissões/RBAC (backend inalterado).
- Contratos de API e endpoints (`api/`).
- Banco de dados/migrations.
- ~~Métricas reais do dashboard...~~ Resolvido em 2026-07-10 — domínio de Vendas/Relatórios já existe no backend (Fases 5-6), Dashboard consome `GET /reports/indicators`/`GET /reports/charts` com dado real.

## Diagnóstico atual do login

- `LoginPage.tsx`/`LoginPage.css`: card simples, sem gradiente, sem logo, sem elemento visual de marca.
- Cores ad-hoc: botão `#4f46e5` (indigo genérico, fora da paleta oficial), erro `#e5484d` — nenhum dos dois é token.
- Textos genéricos ("Entrar", "E-mail", "Senha") — não usam o tom de voz nem os textos oficiais definidos em brand-guidelines.
- Tema: só `prefers-color-scheme`, sem tokens `--pt-*`.

## Diagnóstico atual do dashboard

- `DashboardPage.tsx` hoje é uma lista de tenants para troca de contexto (`selectTenant`), não uma home de gestão comercial — não tem cabeçalho de página, ações rápidas, métricas nem gráfico.
- Sem sidebar/navbar — é uma página solta com `<h1>PegaTicket</h1>` + botão "Sair".
- Mesmas cores ad-hoc do login (indigo, vermelho fora da paleta).
- Nenhuma separação visual de blocos — tudo empilhado numa lista.

## Nova direção

Ver [[design-system]] (seções Login e Dashboard) para o detalhamento visual completo. Resumo:

- **Login**: fundo com gradiente sofisticado verde/lima/teal + elementos abstratos sutis de movimento e celebracao, card moderno, logo PegaTicket oficial vindo de `visual/`, headline "Bem-vindo ao PegaTicket", subheadline oficial da fase atual, botao "Entrar no painel", link secundario "Atualizar sistema".
- **Dashboard**: titulo "Visao geral" + subtitulo, cards de acao rapida ligados ao contexto de eventos (ex.: Criar evento / Abrir vendas / Acompanhar check-in), metricas ligadas a vendas, acessos e publico, grafico em card, navbar leve, sidebar refinada com estado ativo claro.
- A troca de tenant (funcionalidade real hoje) precisa continuar acessível — mover para navbar/menu de usuário em vez de ocupar a tela inteira do dashboard.

## Etapas técnicas

1. ✅ Definir tokens `--pt-*` (light/dark) em `web/src/index.css`, seguindo `.claude/skills/pegaticket-theme-system.md`. Feito em 2026-07-05: `:root` (light), `@media (prefers-color-scheme: dark)` e `[data-theme='dark'/'light']` (para toggle manual futuro), + fontes Inter/Manrope via Google Fonts.
2. ✅ Criar primitivos em `web/src/components/ui/`: `Button.tsx`, `Input.tsx`, `Card.tsx` (estilos em `web/src/styles/components.css`).
3. ✅ Criar `Logo.tsx` (símbolo M com leve inclinação de movimento + traço de destaque em `--pt-accent`, variantes `full`/`mark`) e favicon (`public/favicon.svg`, cores fixas #0F3D5E/#22C7A9 já que favicon não acessa CSS vars).
4. ✅ Recriar `LoginPage` com gradiente (`color-mix` sobre `--pt-bg`/`--pt-primary`), blobs abstratos sutis, logo, textos oficiais ("Bem-vindo ao PegaTicket", subheadline, "Entrar no painel", "Atualizar sistema" → recarrega a página).
5. ✅ Criar `AppLayout`/`Navbar`/`Sidebar`. Feito em 2026-07-05, já em MUI: `web/src/theme/index.ts` (`createTheme` a partir da paleta PegaTicket, light/dark via `useMediaQuery('(prefers-color-scheme: dark)')` em `App.tsx`), `web/src/layouts/AppLayout.tsx` (`AppBar` fixo + `Drawer` — `temporary` em mobile/overlay, `permanent` em `sm+`). Atualização em 2026-07-09: seletor de tenant saiu do AppBar e foi fixado no rodapé da sidebar (`TenantMenu` em variante sidebar), com overflow próprio da navegação para o tenant continuar visível no bottom mesmo com muitos itens. Refinamento adicional no mesmo dia: sidebar mais larga, sem logo duplicada; a marca ficou apenas no header. A troca de tema saiu do menu da conta e virou um bloco fixo logo abaixo do tenant ativo (`ThemeModeSwitch`), com estado inicial seguindo o sistema e persistência de preferência manual após interação. O header passou a usar `UserMenu` à direita; em mobile, hamburger à esquerda + logo compacta central + menu de usuário à direita.
6. ✅ `DashboardPage` completo (2026-07-10): título "Visão geral" + subtítulo + underline gradiente (primary→accent, assinatura visual sutil), ações rápidas (Novo venda/Adicionar cliente/Cadastrar produto — desabilitadas com tooltip "Em breve", pois as telas de Venda/Cliente/Produto ainda não existem no frontend; nunca linkar para rota inexistente nem fingir ação pronta), 3 cards de métrica (Vendas entregues/Vendas pendentes/Valor recebido, dado real via `GET /reports/indicators`), gráfico de vendas por mês (`GET /reports/charts`, Chart.js, último mês destacado em `--pt-accent`). Novos arquivos: `services/reportService.ts`, `types/report.ts`, `hooks/useDashboardReport.ts` (refaz fetch ao trocar de tenant), `utils/format.ts`, `components/dashboard/{MetricCard,QuickActionCard,OrdersByMonthChart}.tsx`. Revelação de entrada em cascata (`.pt-reveal`, `index.css`) respeitando `prefers-reduced-motion`. `theme/index.ts` passou a exportar `pegaticketTokens` (fonte única light/dark reaproveitada pelo Chart.js, que não lê `var(--pt-*)` por rodar em canvas). Validado com screenshot real (Playwright headless) em light/dark × mobile/desktop, dado real do tenant migrado (Js Queijos e Doces, 37625 vendas) — sem erro de console.
7. ✅ Responsividade/acessibilidade do Dashboard revisada junto da implementação (mobile-first, alvo de toque, sem scroll horizontal, chart com `maxTicksLimit` pra não empilhar rótulo). Revisão final de acessibilidade de Login/AppLayout (herdada de etapas anteriores) ainda não teve um passe dedicado — mantém-se como pendência geral do produto, não específica do Dashboard.
8. ⏳ Qualquer listagem de domínio (users, groups, tenants...) usa ag-Grid Community desde a primeira versão — não começar com `<table>` manual para depois trocar. Lib já instalada (`ag-grid-community`/`ag-grid-react`), ainda sem uso real.

## Ordem recomendada de implementação

1. ✅ Tokens de tema (`--pt-*`).
2. ✅ Componentes UI primitivos (Button, Input, Card).
3. ✅ Logo/favicon.
4. ✅ Login.
5. ✅ Navbar/Sidebar (MUI `AppLayout`).
6. ✅ Dashboard completo (métricas/gráfico/ações rápidas com dado real).
7. ⏳ Passe final de acessibilidade + responsividade no restante do produto (Login/AppLayout já ok, Dashboard já ok — falta uma revisão dedicada de conjunto quando mais telas existirem).
8. ⏳ Primeira listagem de domínio em ag-Grid — candidata a próximo passo real. Provável próximo: tela de Vendas (destrava as 3 ações rápidas do Dashboard que hoje estão "Em breve").

## Checklist de entrega

```txt
- Nenhuma cor hardcoded fora de --pt-*.
- Tema claro e escuro revisados em cada tela alterada.
- Login com textos oficiais aplicados.
- Dashboard com header/ações rápidas/métricas/gráfico separados visualmente.
- Troca de tenant continua funcionando (só mudou de lugar na UI).
- Autenticação, rotas, permissões, API e banco intocados.
- Testado primeiro em viewport mobile (nao so validado depois do desktop pronto).
- Alvos de toque ≥44px em botões/inputs/itens clicáveis.
- Nenhuma ação crítica depende só de `:hover`.
- Responsividade validada (mobile/tablet/desktop).
- Acessibilidade básica revisada (labels, foco, contraste).
- Memoria (design-system.md / brand-guidelines.md) atualizada se alguma decisao visual mudar durante a implementacao.
```
