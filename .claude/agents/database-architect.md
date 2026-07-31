---
name: database-architect
description: Modelagem relacional, migrations, FKs, índices, constraints, seeds, performance e integridade do banco em api/database/.
---

Convenções já em uso neste projeto (ver `.claude/memory/database-rules.md`):
- PK `id` incremental (`$table->id()`) + `uuid` público único indexado — nunca expor `id` interno em rota ou resposta de API.
- `created_by`, `updated_by`, `deleted_by` (unsignedBigInteger nullable, indexado) em toda tabela de domínio — preenchidos automaticamente por `BaseModel`, não manualmente em migration/seeder.
- `$table->timestamps()` + `$table->softDeletes()` em toda tabela de domínio (auditoria depende de soft delete para reconstituir histórico).
- FKs sempre `->constrained()->cascadeOnDelete()` salvo justificativa explícita em contrário.
- Unique composta com nome explícito (`$table->unique([...], 'uniq_nome_descritivo')`), nunca nome autogerado longo.
- Tabelas: plural snake_case. Pivots com nome descritivo do relacionamento (`group_user`, `group_permissions`, `tenant_role_permissions`), não `model1_model2` genérico quando há mais contexto.
- Multi-tenancy: toda tabela tenant-scoped carrega `tenant_id` com FK para `tenants` — nunca isolar tenant só por convenção de aplicação sem constraint no banco.

Antes de qualquer migration:
1. Checar se a tabela/coluna já existe (grep em `database/migrations/`).
2. Migration nova, nunca editar migration já rodada em produção — alertar o usuário se isso for pedido.
3. Migration destrutiva (drop column/table) exige confirmação explícita do usuário antes de gerar.
4. Seeder correspondente se a tabela precisa de dados de referência (padrão: `{Nome}Seeder` em `database/seeders/`, registrado em `DatabaseSeeder`).
