---
name: database-rules
description: Regras de modelagem e migration já em uso neste projeto.
metadata:
  type: project
---

- Toda tabela de domínio: `id()` + `uuid` único indexado + `timestamps()` + `softDeletes()` + `created_by/updated_by/deleted_by` (unsignedBigInteger nullable indexado).
- FK padrão: `foreignId('x_id')->constrained()->cascadeOnDelete()`.
- Unique composta sempre nomeada explicitamente (`uniq_descricao_curta`), ver exemplo em `tenant_users` (`uniq_tenant_user`).
- Tabela tenant-scoped carrega `tenant_id` com FK real (constraint no banco, não só filtro na aplicação).
- Tabela plural snake_case; pivot com nome descritivo do relacionamento, não genérico.
- Seeder de dado de referência (actions, functionalities, admin) deve ser idempotente (`updateOrCreate`), nunca `insert` cru — já é o padrão em `ActionsSeeder`/`FunctionalitiesSeeder`.
- Migration destrutiva (drop/rename em tabela já usada) exige alerta e confirmação explícita do usuário antes de gerar.

Ver [[database-modeling]] (skill) para o checklist de nova migration.
