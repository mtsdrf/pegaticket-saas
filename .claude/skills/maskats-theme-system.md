---
name: maskats-theme-system
description: Sistema de tokens CSS (--mk-*) e tema claro/escuro da Maskats — como declarar e usar em web/.
---

## Objetivo

Garantir que toda cor, raio de borda, sombra e anel de foco da interface venha de um único conjunto de tokens CSS, funcionando em tema claro e escuro sem duplicar valores nos componentes.

## Regra principal de tokens

Nenhum componente declara cor/raio/sombra em hex ou valor solto. Sempre `var(--mk-*)`:

```css
.card {
  background: var(--mk-surface);
  border: 1px solid var(--mk-border);
  border-radius: var(--mk-radius-lg);
  box-shadow: var(--mk-shadow-sm);
  color: var(--mk-text);
}
```

## Tokens obrigatórios

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

## Tema claro

```css
:root {
  --mk-bg: #F6F8FB;
  --mk-surface: #FFFFFF;
  --mk-surface-soft: #EEF3F8;
  --mk-primary: #0F3D5E;
  --mk-primary-hover: #0B314C;
  --mk-secondary: #2563EB;
  --mk-accent: #22C7A9;
  --mk-text: #102033;
  --mk-muted: #64748B;
  --mk-border: #D8E0EA;
  --mk-success: #16A34A;
  --mk-warning: #F59E0B;
  --mk-danger: #DC2626;
  --mk-info: #0284C7;
  --mk-radius-sm: 6px;
  --mk-radius-md: 10px;
  --mk-radius-lg: 16px;
  --mk-radius-xl: 24px;
  --mk-shadow-sm: 0 1px 2px rgba(15, 61, 94, 0.06);
  --mk-shadow-md: 0 4px 12px rgba(15, 61, 94, 0.10);
  --mk-shadow-lg: 0 12px 32px rgba(15, 61, 94, 0.14);
  --mk-focus-ring: 0 0 0 3px rgba(37, 99, 235, 0.35);
}
```

## Tema escuro

```css
[data-theme='dark'] {
  --mk-bg: #07111F;
  --mk-surface: #0D1B2E;
  --mk-surface-soft: #13243A;
  --mk-primary: #38BDF8;
  --mk-primary-hover: #7DD3FC;
  --mk-secondary: #60A5FA;
  --mk-accent: #2DD4BF;
  --mk-text: #F8FAFC;
  --mk-muted: #94A3B8;
  --mk-border: #24364D;
  --mk-success: #22C55E;
  --mk-warning: #FBBF24;
  --mk-danger: #F87171;
  --mk-info: #38BDF8;
  --mk-shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.30);
  --mk-shadow-md: 0 4px 12px rgba(0, 0, 0, 0.35);
  --mk-shadow-lg: 0 12px 32px rgba(0, 0, 0, 0.45);
  --mk-focus-ring: 0 0 0 3px rgba(96, 165, 250, 0.45);
}
```

Radius não muda entre temas (só cor/sombra muda). `[data-theme='dark']` deve coexistir com fallback automático via `@media (prefers-color-scheme: dark)` quando não houver preferência manual salva (ver "Persistência de tema").

## Aplicação recomendada em body, card, button e input

```css
body {
  background: var(--mk-bg);
  color: var(--mk-text);
}

.mk-card {
  background: var(--mk-surface);
  border: 1px solid var(--mk-border);
  border-radius: var(--mk-radius-lg);
  box-shadow: var(--mk-shadow-sm);
}

.mk-button-primary {
  background: var(--mk-primary);
  color: var(--mk-bg);
}
.mk-button-primary:hover {
  background: var(--mk-primary-hover);
}
.mk-button-primary:focus-visible {
  box-shadow: var(--mk-focus-ring);
}

.mk-input {
  background: var(--mk-surface);
  border: 1px solid var(--mk-border);
  color: var(--mk-text);
}
.mk-input:focus {
  border-color: var(--mk-primary);
  box-shadow: var(--mk-focus-ring);
}
.mk-input[aria-invalid='true'] {
  border-color: var(--mk-danger);
}
```

## Persistência de tema

- Preferência do sistema (`prefers-color-scheme`) é o padrão inicial.
- Se o usuário escolher manualmente, salvar em `localStorage` (ex.: chave `maskats.theme`) e aplicar via atributo `data-theme="dark"`/`data-theme="light"` na raiz (`<html>`), que tem precedência sobre `prefers-color-scheme`.
- Nunca depender só de classe JS sem o atributo — `data-theme` é o contrato entre CSS e a escolha do usuário.

## Checklist

```txt
- Nenhum hex fora de :root/[data-theme='dark'].
- Todo componente novo usa var(--mk-*), nunca cor solta.
- Tema claro e escuro testados lado a lado.
- Foco visível (--mk-focus-ring) em todo elemento interativo.
- Contraste adequado nos dois temas.
- Preferência de tema persiste entre sessões.
```
