---
name: pegaticket-theme-system
description: Sistema de tokens CSS (--pt-*) e tema claro/escuro da PegaTicket — como declarar e usar em web/.
---

## Objetivo

Garantir que toda cor, raio de borda, sombra e anel de foco da interface venha de um único conjunto de tokens CSS, funcionando em tema claro e escuro sem duplicar valores nos componentes.

## Regra principal de tokens

Nenhum componente declara cor/raio/sombra em hex ou valor solto. Sempre `var(--pt-*)`:

```css
.card {
  background: var(--pt-surface);
  border: 1px solid var(--pt-border);
  border-radius: var(--pt-radius-lg);
  box-shadow: var(--pt-shadow-sm);
  color: var(--pt-text);
}
```

## Tokens obrigatórios

```txt
--pt-bg
--pt-surface
--pt-surface-soft
--pt-primary
--pt-primary-hover
--pt-secondary
--pt-accent
--pt-text
--pt-muted
--pt-border
--pt-success
--pt-warning
--pt-danger
--pt-info
--pt-radius-sm
--pt-radius-md
--pt-radius-lg
--pt-radius-xl
--pt-shadow-sm
--pt-shadow-md
--pt-shadow-lg
--pt-focus-ring
```

## Tema claro

```css
:root {
  --pt-bg: #F7FAF9;
  --pt-surface: #FFFFFF;
  --pt-surface-soft: #F0F6F4;
  --pt-primary: #08CFA7;
  --pt-primary-hover: #00B995;
  --pt-secondary: #20D565;
  --pt-accent: #9BEA00;
  --pt-text: #10231D;
  --pt-muted: #49635A;
  --pt-border: #DCE8E4;
  --pt-success: #16A34A;
  --pt-warning: #F59E0B;
  --pt-danger: #DC2626;
  --pt-info: #0EA5E9;
  --pt-radius-sm: 6px;
  --pt-radius-md: 10px;
  --pt-radius-lg: 16px;
  --pt-radius-xl: 24px;
  --pt-shadow-sm: 0 1px 3px rgba(12, 50, 39, 0.08);
  --pt-shadow-md: 0 8px 24px rgba(12, 50, 39, 0.10);
  --pt-shadow-lg: 0 18px 48px rgba(12, 50, 39, 0.14);
  --pt-focus-ring: 0 0 0 3px rgba(8, 207, 167, 0.28);
}
```

## Tema escuro

```css
[data-theme='dark'] {
  --pt-bg: #07110E;
  --pt-surface: #101E1A;
  --pt-surface-soft: #0F1D18;
  --pt-primary: #19DFC0;
  --pt-primary-hover: #35E8CD;
  --pt-secondary: #37DE77;
  --pt-accent: #A6EE16;
  --pt-text: #F8FAFC;
  --pt-muted: #B6CBC3;
  --pt-border: #233A32;
  --pt-success: #22C55E;
  --pt-warning: #FBBF24;
  --pt-danger: #F87171;
  --pt-info: #38BDF8;
  --pt-shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.30);
  --pt-shadow-md: 0 4px 12px rgba(0, 0, 0, 0.35);
  --pt-shadow-lg: 0 12px 32px rgba(0, 0, 0, 0.45);
  --pt-focus-ring: 0 0 0 3px rgba(96, 165, 250, 0.45);
}
```

Radius não muda entre temas (só cor/sombra muda). `[data-theme='dark']` deve coexistir com fallback automático via `@media (prefers-color-scheme: dark)` quando não houver preferência manual salva (ver "Persistência de tema").

## Aplicação recomendada em body, card, button e input

```css
body {
  background: var(--pt-bg);
  color: var(--pt-text);
}

.pt-card {
  background: var(--pt-surface);
  border: 1px solid var(--pt-border);
  border-radius: var(--pt-radius-lg);
  box-shadow: var(--pt-shadow-sm);
}

.pt-button-primary {
  background: var(--pt-primary);
  color: var(--pt-bg);
}
.pt-button-primary:hover {
  background: var(--pt-primary-hover);
}
.pt-button-primary:focus-visible {
  box-shadow: var(--pt-focus-ring);
}

.pt-input {
  background: var(--pt-surface);
  border: 1px solid var(--pt-border);
  color: var(--pt-text);
}
.pt-input:focus {
  border-color: var(--pt-primary);
  box-shadow: var(--pt-focus-ring);
}
.pt-input[aria-invalid='true'] {
  border-color: var(--pt-danger);
}
```

## Persistência de tema

- Preferência do sistema (`prefers-color-scheme`) é o padrão inicial.
- Se o usuário escolher manualmente, salvar em `localStorage` (ex.: chave `pegaticket.theme`) e aplicar via atributo `data-theme="dark"`/`data-theme="light"` na raiz (`<html>`), que tem precedência sobre `prefers-color-scheme`.
- Nunca depender só de classe JS sem o atributo — `data-theme` é o contrato entre CSS e a escolha do usuário.

## Checklist

```txt
- Nenhum hex fora de :root/[data-theme='dark'].
- Todo componente novo usa var(--pt-*), nunca cor solta.
- Tema claro e escuro testados lado a lado.
- Foco visível (--pt-focus-ring) em todo elemento interativo.
- Contraste adequado nos dois temas.
- Preferência de tema persiste entre sessões.
- A composicao visual deve combinar teal (primary), verde (secondary) e lima (accent), conforme o pacote oficial em `visual/`, sem voltar para a fase anterior azul/coral.
```
