---
name: html-to-react-rebrand
description: Converter template HTML/CSS/JS puro em React aplicando a identidade visual da Maskats, sem alterar regra de negócio.
---

## Objetivo

Transformar um template estático (HTML/CSS/JS) em componentes React idiomáticos para `web/`, já aplicando tokens e identidade da Maskats — sem tocar em autenticação, rotas, permissões, API ou banco de dados.

## Fluxo obrigatório para conversão

1. Mapear seções visuais do template (header, nav, conteúdo principal, cards, footer).
2. Identificar componentes repetidos (cards de métrica, itens de lista, linhas de tabela).
3. Converter HTML para JSX.
4. Trocar `class` por `className`.
5. Trocar `for` por `htmlFor`.
6. Fechar todas as tags corretamente (JSX é mais estrito que HTML).
7. Transformar scripts (toggle de menu, submit de formulário, fetch) em lógica React (`useState`, `useEffect`, handlers).
8. Separar dados repetidos (itens de lista/menu/tabela) em arrays e `.map()`.
9. Criar componentes reutilizáveis para blocos que se repetem (não deixar tudo em um arquivo).
10. Aplicar identidade Maskats (ver `.claude/skills/maskats-visual-identity.md`): paleta, tipografia, tom de voz nos textos.
11. Aplicar tokens (`var(--mk-*)`, ver `.claude/skills/maskats-theme-system.md`) em vez de cores/sombras do template original.
12. Considerar tema claro e escuro desde a primeira versão convertida.
13. Melhorar acessibilidade (labels, `alt`, `aria-*`, navegação por teclado) mesmo que o template original não tivesse.
14. Validar responsividade (o template estático pode não ter sido responsivo).
15. **Não alterar regra de negócio** — se o template tiver lógica de formulário/validação, replicar o comportamento visível, não inventar nova regra.

## Estrutura React recomendada

Seguir `.claude/skills/project-structure.md` e o padrão já usado em `web/src/`:

```txt
src/
  pages/{Feature}/{Feature}Page.tsx
  components/ui/        (Button, Input, Card — primitivos)
  components/           (compostos reutilizáveis entre páginas)
  layouts/               (AppLayout, AuthLayout — navbar/sidebar)
  hooks/
  services/
```

## Componentização

- Página (`pages/`) orquestra: busca dado, compõe layout, não tem JSX gigante.
- Bloco repetido do template vira componente em `components/` (ex.: `MetricCard`, `QuickActionCard`).
- Elemento realmente atômico e reutilizável entre páginas diferentes vira primitivo em `components/ui/` (ex.: `Button`, `Card`).
- Não criar componente para algo usado uma única vez sem motivo — três linhas repetidas na mesma página não justificam abstração.

## Regras de conversão

- `<div onclick="...">` → elemento real interativo (`<button>`), nunca `div` com handler fazendo papel de botão.
- Inline `style="..."` do template → classe CSS usando tokens, não inline style solto.
- IDs usados só para estilo no template → viram `className`; ID só permanece se necessário para acessibilidade (`aria-describedby`, `htmlFor`/`id` de input).
- Script de tema claro/escuro do template (se houver) → substituir pelo sistema de tokens + `data-theme` da Maskats, não manter o mecanismo antigo.

## Aplicação da identidade Maskats

- Qualquer cor do template original é substituída pela paleta oficial (`--mk-*`), nunca mantida como estava.
- Qualquer texto genérico do template é reescrito no tom de voz Maskats (ver `.claude/memory/brand-guidelines.md`).
- Logo/ícone genérico do template (analytics, gráfico, seta) é substituído pelo símbolo M da Maskats ou removido se não fizer sentido.

## Checklist final

```txt
- JSX válido (tags fechadas, className/htmlFor corretos).
- Nenhum <script> remanescente — tudo é React.
- Dados repetidos em array + .map(), sem duplicação de JSX.
- Componentização adequada (nem tudo em um arquivo, nem fragmentado demais).
- Tokens --mk-* aplicados, nenhuma cor do template original sobrevivendo hardcoded.
- Tema claro e escuro funcionando.
- Acessibilidade validada.
- Responsividade validada.
- Regra de negócio preservada (nada inventado, nada removido).
- Autenticação, rotas, permissões, API e banco intocados.
```
