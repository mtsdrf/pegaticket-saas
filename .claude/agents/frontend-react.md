---
name: frontend-react
description: Estrutura React, componentes, páginas, hooks, services de API, estado, formulários, validação e integração com a API Laravel de api/.
---

O frontend web vive em `web/`, projeto React próprio (Vite), separado de `api/` — não usar `api/resources/js`. `app/` é reservado para um app mobile/nativo futuro; não criar ainda.

Estrutura dentro de `web/`:
```
src/
  app/ pages/ components/ layouts/ hooks/ services/ types/ utils/ constants/ contexts/ routes/ styles/
```

Padrões obrigatórios:
- Um `service` por recurso em `services/`, espelhando os endpoints REST de `api/routes/api.php` (`/api/v1/users`, `/api/v1/groups`, `/api/v1/tenants`, etc).
- Toda chamada de API trata explicitamente `loading`, `error` e `empty state` — nunca assume sucesso silencioso.
- Resposta da API sempre no formato `{ success, message, data, meta }` (sucesso) ou `{ success, message, code, errors, meta }` (erro) — o client de API deve desembrulhar isso de forma centralizada (um único lugar, não em cada componente).
- Autenticação via JWT: access token + refresh (`/auth/refresh`), troca de tenant via `/auth/switch-tenant` — o estado de tenant ativo deve ser modelado explicitamente (contexto ou store), pois quase todo endpoint de negócio é tenant-scoped.
- Formulários: validação client-side espelhando as regras dos `Http/Requests` do backend, mas sem duplicar lógica de negócio.

Consultar `.claude/memory/api-patterns.md` antes de integrar qualquer endpoint novo.

## Já implementado (base criada em 2026-07-05)
- `src/services/apiClient.ts`: instância axios com `VITE_API_BASE_URL`, injeta `Authorization: Bearer`, desembrulha erro em `ApiRequestError` (`types/api.ts`), faz refresh automático em 401 (retry único via `refreshInFlight` compartilhado).
- `src/services/authService.ts`: `login/logout/myTenants/switchTenant`, tipados por `types/auth.ts` — espelha exatamente `AuthResource`/`MyTenantResource` do backend.
- `src/contexts/auth-context.ts` + `AuthContext.tsx` + `hooks/useAuth.ts`: estado de sessão (access token, tenant ativo) persistido em `localStorage` (`constants/storage.ts`).
- `src/routes/ProtectedRoute.tsx` + `AppRoutes.tsx`: `/login` pública, `/` protegida.
- `src/pages/Login/` e `src/pages/Dashboard/` (lista de tenants + troca de tenant + logout): referência de como tratar loading/error/empty state.

Ao adicionar um novo recurso, seguir o mesmo padrão desses arquivos, não reinventar.
