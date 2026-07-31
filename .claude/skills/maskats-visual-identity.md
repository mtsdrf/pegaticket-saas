---
name: maskats-visual-identity
description: Identidade visual oficial da Maskats — quando criar ou alterar qualquer tela, componente ou texto visível ao usuário em web/.
---

## Quando usar

Toda vez que uma tarefa envolver: criar tela nova, redesenhar tela existente, criar componente visual, escrever texto de UI (título, botão, mensagem de erro/vazio/sucesso), criar logo/favicon, ou converter HTML puro em React para este projeto.

## Identidade oficial

- **Nome**: Maskats (nunca alterar).
- **Tagline**: "Gestão clara para empresas em movimento."
- **Conceito**: SaaS de gestão comercial (pedidos, clientes, produtos, indicadores) — claro, moderno, produtivo, inteligente.
- Detalhamento completo: `.claude/memory/brand-guidelines.md`.

## Estilo visual

Moderno, limpo, profissional, elegante, levemente premium, funcional, acessível, responsivo. Nunca genérico, infantil, poluído, "financeiro demais", frio, antigo, clichê ou visualmente exagerado. Inspiração de qualidade: Linear, Vercel, Stripe, Notion, Figma, Apple — sem copiar nenhuma delas.

## Paleta

Ver `.claude/memory/design-system.md` para a paleta completa (light/dark) e `.claude/skills/maskats-theme-system.md` para os tokens CSS. Nunca hardcodar hex — sempre `var(--mk-*)`.

## Logo

Símbolo: `M` geométrico com movimento sutil (sugere avanço/organização/crescimento). Nunca usar seta literal, gráfico de barras, gráfico financeiro ou ícone genérico de analytics — isso é exatamente o visual antigo que está sendo substituído. Versões obrigatórias: horizontal (símbolo + wordmark), compacta (só símbolo), favicon, light, dark, monocromática.

## Regras de interface

- Um CTA primário por tela/contexto (botão `--mk-primary`), nunca múltiplos botões grandes competindo.
- Card como unidade visual padrão para agrupar conteúdo (métricas, formulários, listas).
- Sidebar com estado ativo sempre evidente (não só uma sutileza).
- Todo estado (loading, vazio, erro, sucesso) tem tratamento visual próprio — nunca tela em branco ou mensagem técnica crua.
- Tema claro e escuro sempre revisados juntos.

## Componentes recomendados

`Button`, `Input`, `Card`, `Badge`, `Alert`, `EmptyState`, `LoadingState`, `ErrorState`, `Sidebar`, `Navbar`, `PageHeader` — construir em `web/src/components/ui/` conforme a necessidade real da tela (não criar biblioteca inteira antecipadamente).

## Textos

Tom de voz: claro, objetivo, confiante, humano, profissional, sem jargão nem clichê. Exemplos oficiais (login, dashboard) em `.claude/memory/brand-guidelines.md` → "Exemplos de texto".

## Login

Fundo com gradiente sofisticado + elementos abstratos sutis de movimento (não imagem financeira genérica). Card moderno centrado, logo visível, headline "Bem-vindo ao Maskats", subheadline oficial, botão "Entrar no painel", link secundário "Atualizar sistema".

## Dashboard

Separar sempre: cabeçalho da página → ações rápidas → métricas → gráfico → navegação. Título "Visão geral", subtítulo "Acompanhe os principais números da operação.". Ações rápidas: Novo pedido / Adicionar cliente / Cadastrar produto. Métricas: Pedidos entregues / Pedidos pendentes / Valor recebido. Evitar azul chapado repetido, cards pesados idênticos, gráfico sem contexto.

## Checklist

```txt
- Paleta oficial (tokens --mk-*), nada hardcoded.
- Tom de voz Maskats nos textos.
- Logo correta para o contexto (horizontal/compacta/favicon).
- Tema claro e escuro revisados.
- Estados (loading/vazio/erro/sucesso) tratados.
- Responsivo (mobile/tablet/desktop).
- Acessível (labels, foco, contraste).
- Sem elementos proibidos na logo (seta, gráfico financeiro, ícone genérico).
```
