---
name: react-frontend
description: Padrão para criar páginas, componentes e integração com a API neste projeto quando o frontend React existir.
---

Projeto em `web/` (React + Vite), irmão de `api/`.

## Estrutura
```
src/
  app/ pages/ components/ layouts/ hooks/ services/ types/ utils/ constants/ contexts/ routes/ styles/
```

## Passo a passo para consumir um recurso novo (ex: `products`)
1. `services/productService.ts` — funções `list/get/create/update/remove`, chamando `/api/v1/products` via client axios central.
2. Client central de API (um só lugar) desembrulha `{ success, data, meta }` / `{ success, code, errors }` e injeta o JWT + tenant ativo no header.
3. `hooks/useProducts.ts` (ou equivalente) — encapsula chamada + `loading`/`error`/`data`.
4. `pages/Products/` — lista com estado de loading, erro e empty state tratados explicitamente (nunca assumir array não vazio).
5. `components/` — só componentes reutilizáveis entre páginas; específico de uma página fica dentro da própria pasta da página.
6. Formulário: validação client-side espelhando `Http/Requests` do backend correspondente, mas erro de validação do servidor (`errors` no payload) sempre tem prioridade de exibição.

## Autenticação e tenant
- Token JWT + refresh token — renovar via `/auth/refresh` antes de expirar ou no primeiro 401.
- Tenant ativo é estado global (contexto): trocar via `/auth/switch-tenant`, refletir na sidebar/header, e invalidar caches de dados tenant-scoped ao trocar.

## UX
- Toda ação (create/update/delete) mostra estado de carregamento e feedback de sucesso/erro — usar `message` do payload da API, não texto genérico fixo.
