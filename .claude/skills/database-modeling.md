---
name: database-modeling
description: Padrão de migrations, nomenclatura e integridade referencial usado em api/database/migrations.
---

## Checklist de toda migration de tabela de domínio
```php
$table->id();
$table->uuid('uuid')->unique()->index();

// colunas de negócio aqui

$table->foreignId('tenant_id')->constrained()->cascadeOnDelete(); // se tenant-scoped

$table->unsignedBigInteger('created_by')->nullable()->index();
$table->unsignedBigInteger('updated_by')->nullable()->index();
$table->unsignedBigInteger('deleted_by')->nullable()->index();

$table->timestamps();
$table->softDeletes();
```

## Nomenclatura
- Tabela: plural snake_case (`tenant_users`, `group_permissions`).
- Pivot: nome descritivo do relacionamento, não `a_b` genérico quando o domínio sugere melhor nome (`group_permissions` em vez de `group_functionality`).
- Unique composta: `$table->unique([...], 'uniq_descricao_curta')` — sempre nomear explicitamente.
- FK: `$table->foreignId('x_id')->constrained()->cascadeOnDelete()` salvo razão explícita para `restrict`/`nullOnDelete`.

## Evitar
- Campo JSON para dado que tem estrutura relacional clara (preferir tabela própria).
- Coluna genérica sem regra clara (`data`, `meta` livre) em tabela de domínio central.
- Migration destrutiva (drop column/table, rename em produção) sem alertar o usuário e confirmar antes.
- Editar migration já aplicada — criar uma nova migration de alteração.

## Seeds
- Dado de referência (roles, functionalities, actions) sempre via Seeder idempotente (`updateOrCreate`), nunca `insert` cru.
