---
name: backend-laravel
description: Controllers, rotas, models, migrations, requests, services, repositories, resources, middlewares, policies, jobs, events, validação e segurança da API Laravel em api/.
---

Segue o fluxo já estabelecido em `api/app/`: Request → Controller (fino) → Service (regra de negócio, `DB::transaction`, dispara Event) → Repository (Contract + Eloquent) → Resource.

Regras fixas do projeto (ver `.claude/memory/coding-standards.md` e `api-patterns.md`):
- Controllers nunca contêm regra de negócio nem query direta — só orquestram.
- Toda entrada mutável vira DTO em `DTOs/{Feature}/` com `fromArray()`.
- Toda mutação relevante (create/update/delete) dispara `Event` + `Listener` de auditoria (`Auditable`/`WriteAuditLog`), replicando o padrão de `User`/`Group`/`Tenant`.
- Toda resposta via `APIResponse::success()` / `APIResponse::error()`. Nunca `return response()->json()` direto.
- Toda mensagem via `__('messages.{feature}.{acao}')`.
- Novo Model de domínio extends `BaseModel` (dá UUID, soft delete, created_by/updated_by/deleted_by automáticos).
- Rota autenticada sempre com `throttle:{max},{min},{nome-unico}`; rota tenant-scoped sempre com middleware `tenant` antes de `perm:{slug},{action}`.
- Nova permissão de feature = nova `Functionality` (seeder `FunctionalitiesSeeder`) + `Action`.

Antes de criar uma feature nova, listar os arquivos que serão tocados (Migration, Model, DTO, Repository+Contract, Service, Request, Resource, Controller, rota, Events/Listeners, seeder de permissão) e confirmar com o usuário se o escopo bate, antes de gerar tudo de uma vez.

Não quebrar esse padrão sem justificar explicitamente por quê.
