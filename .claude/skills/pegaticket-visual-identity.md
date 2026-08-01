---
name: pegaticket-visual-identity
description: Identidade visual oficial da PegaTicket — quando criar ou alterar qualquer tela, componente ou texto visível ao usuário em web/.
---

## Quando usar

Toda vez que uma tarefa envolver: criar tela nova, redesenhar tela existente, criar componente visual, escrever texto de UI (título, botão, mensagem de erro/vazio/sucesso), criar logo/favicon, ou converter HTML puro em React para este projeto.

## Identidade oficial

- **Nome**: PegaTicket (nunca alterar).
- **Tagline**: "Do acesso a experiencia, tudo em movimento."
- **Conceito**: "Energia de Evento" — plataforma de eventos e ingressos que comunica movimento, acesso, conexao, experiencia, tecnologia e confianca.
- **Fonte oficial dos assets**: pasta raiz `visual/`.
- Detalhamento completo: `.claude/memory/brand-guidelines.md`.

## Estilo visual

Moderno, limpo, profissional, elegante, levemente premium, funcional, acessível, responsivo. Nunca genérico, infantil, poluído, "financeiro demais", frio, antigo, clichê ou visualmente exagerado. Inspiração de qualidade: Linear, Vercel, Stripe, Notion, Figma, Apple — sem copiar nenhuma delas.

## Paleta

Ver `.claude/memory/design-system.md` para a paleta completa (light/dark) e `.claude/skills/pegaticket-theme-system.md` para os tokens CSS. Nunca hardcodar hex — sempre `var(--pt-*)`.

## Logo

Conceito ideal de simbolo: fita continua que se transforma em ingresso, sugerindo trajeto, acesso e experiencia. Desde 2026-08-01, o pacote oficial entregue pelo usuario esta em `visual/` (logo principal em `visual/logo_pegaticket.png`, favicons e manifest icons no mesmo diretorio) e deve ser considerado a verdade da marca. Nunca usar grafico financeiro, icone generico de analytics ou linguagem visual de ERP.

## Regras de interface

- Um CTA primário por tela/contexto (botão `--pt-primary`), nunca múltiplos botões grandes competindo.
- Card como unidade visual padrão para agrupar conteúdo (métricas, formulários, listas).
- Sidebar com estado ativo sempre evidente (não só uma sutileza).
- Todo estado (loading, vazio, erro, sucesso) tem tratamento visual próprio — nunca tela em branco ou mensagem técnica crua.
- Tema claro e escuro sempre revisados juntos.

## Componentes recomendados

`Button`, `Input`, `Card`, `Badge`, `Alert`, `EmptyState`, `LoadingState`, `ErrorState`, `Sidebar`, `Navbar`, `PageHeader` — construir em `web/src/components/ui/` conforme a necessidade real da tela (não criar biblioteca inteira antecipadamente).

## Textos

Tom de voz: claro, objetivo, confiante, humano, profissional, sem jargão nem clichê. Exemplos oficiais (login, dashboard) em `.claude/memory/brand-guidelines.md` → "Exemplos de texto".

## Login

Fundo com gradiente sofisticado + elementos abstratos sutis de movimento e celebracao (nao imagem financeira generica). Card moderno centrado, logo visivel, headline "Bem-vindo ao PegaTicket", subheadline oficial, botao "Entrar no painel", link secundario "Atualizar sistema".

## Dashboard

Separar sempre: cabecalho da pagina → acoes rapidas → metricas → grafico → navegacao. Titulo "Visao geral", subtitulo orientado a vendas/acesso/operacao. Preferir exemplos de acao e metricas ligados a eventos, ingressos, publico e check-in. Evitar azul chapado repetido, cards pesados identicos, grafico sem contexto.

## Checklist

```txt
- Paleta oficial (tokens --pt-*), nada hardcoded.
- Tom de voz PegaTicket nos textos.
- Logo correta para o contexto (horizontal/compacta/favicon).
- Tema claro e escuro revisados.
- Estados (loading/vazio/erro/sucesso) tratados.
- Responsivo (mobile/tablet/desktop).
- Acessível (labels, foco, contraste).
- Sem elementos proibidos na logo (grafico financeiro, icone generico de analytics, linguagem antiga de ERP).
```
