# Database Analysis — Schema Overview

Fonte: `dump_base.sql` (raiz do projeto), export phpMyAdmin 5.2.2, MariaDB 11.8.8, banco `u452434908_maskats` (padrão de nome de hospedagem compartilhada tipo Hostinger). Analisado em 2026-07-06.

**Código-fonte do sistema legado localizado e analisado** (2026-07-06, confirmado pelo usuário): `/home/mtsdrf/workspace/maskats/` — `backend/` (Laravel 8, PHP 7.3/8.0, tymon/jwt-auth) + `frontend/` (React 17, react-router-dom v5, Bootstrap 4) + `landing_page/`. Isso resolveu a maioria das dúvidas de negócio que antes eram só inferência de schema — ver [[06-business-rules]] (seção "Confirmado pelo código-fonte") e [[07-implementation-roadmap]].

**2026-07-22**: novo dump COM dados reais (`backup_prod_maskats.sql`, mesmas 20 tabelas) analisado para migração de 1 único estabelecimento (id=4, "Js Queijos e Doces") — volume real por tabela, integridade referencial nos dados de fato, e mapeamento campo-a-campo contra `api/database/migrations` atual. Ver [[09-estab4-migration-data-audit]].

## Conteúdo do dump
- **Só estrutura** — nenhum `INSERT`, sem dados reais. Sem risco de exposição de dado sensível nesta análise.
- **20 tabelas**, nenhuma **view**, nenhuma **function/procedure**, nenhuma **trigger**, nenhuma **sequence**. Toda regra de negócio está na aplicação (PHP legado), não no banco. *Confirmado pelo banco.*
- PKs sempre `id int(11) AUTO_INCREMENT`. Nenhuma PK composta, nenhuma UUID como PK (uuid é coluna auxiliar, não chave).

## Tecnologia / stack provável do sistema legado
- MariaDB puro, sem ORM detectável pela estrutura (nomes/padrões não batem com Laravel — sem `remember_token`, sem `email_verified_at`, sem tabela de password-reset, `usuario` não é `users`). *Inferência: sistema legado em PHP não-Laravel (ou framework próprio/CRUD gerado), o que este projeto (`api/`) está substituindo.*

## Padrões estruturais confirmados pelo banco
- **Auditoria universal** em 18/20 tabelas: `inclusao_usuario_id` (NOT NULL, FK→`usuario`), `inclusao_data` (default `current_timestamp()`), `alteracao_usuario_id` (nullable, FK→`usuario`), `alteracao_data` (nullable). Exceções: `cliente_novo` e `json`.
- **Soft-disable por flag**, não soft-delete real: coluna `ativo tinyint(1) DEFAULT 1` em 18/20 tabelas (mesmas exceções). Não existe `deleted_at`. Sistema aparentemente nunca apaga fisicamente um registro de domínio, só marca `ativo=0`.
- **Multi-tenant por coluna discriminadora**: `estabelecimento_id` presente em 18/20 tabelas (exceções: a própria `estabelecimento` e `cliente_novo`). Cada linha pertence a exatamente 1 estabelecimento — modelo diferente do novo sistema (`tenants`/`tenant_users` com N:N). Ver [[03-relationships-map]] e dúvida de migração em [[07-implementation-roadmap]].
- **UUID auxiliar** (`varchar(40)`) em 18/20 tabelas, mesmas exceções. `usuario.uuid` é `varchar(200)` — inconsistente com o padrão (`varchar(40)`) das demais tabelas; provável descuido no schema original, não uma decisão. *Confirmado pelo banco (inconsistência), causa é inferência.*
- **Charset inconsistente**: maioria das tabelas em `latin1`/`latin1_general_ci`, mas `bairro` e `json` em `utf8mb4`/`utf8mb4_unicode_ci` (e dentro de `bairro`, as colunas `nome`/`uuid` forçam `latin1` mesmo a tabela sendo `utf8mb4`). Risco real de mojibake em acentuação ao migrar dados reais — ver [[06-business-rules]].

## Tabelas fora do padrão (candidatas a técnicas/transitórias)
- `cliente_novo`: sem PK, sem índice, sem FK, sem colunas de auditoria/tenant. Só `inclusao_data`, `nome`, `whatsapp`, `email`. *Inferência: captura de lead (formulário público "quero ser cliente"), não uma entidade de domínio real.*
- `json`: sem PK, colunas `tabela` (nome da tabela referenciada) e `json` (valores atualizados) — comentários da própria coluna confirmam o propósito. *Confirmado pelo banco: é um log genérico de alteração, mas sem referência à linha alterada, ao usuário ou ao tipo de ação* — auditoria fraca comparada ao `AuditLog` (Events/Listeners) já implementado no novo backend. Ver risco em [[06-business-rules]].

## Inventário técnico (contagem)
- Tabelas: 20 · Views: 0 · Triggers: 0 · Functions/Procedures: 0 · Sequences: 0.
- FKs explícitas: 47 (todas `ON DELETE`/`ON UPDATE` padrão do MySQL — nenhum `CASCADE`/`SET NULL` explícito no dump, ou seja, comportamento é `RESTRICT` implícito). *Confirmado pelo banco.*
- Índices além de PK/FK: nenhum índice extra de busca (ex.: nada em `cliente.nome`, `produto.nome` etc.) — toda busca por texto hoje provavelmente é `LIKE` sem índice dedicado. Ver observação de performance em [[06-business-rules]].

## Dúvida para validação
- Confirmar se este dump reflete o banco de produção atual (mesmo sem dados) ou se é uma versão mais antiga/parcial — a ausência total de views/triggers pode ser real ou só um recorte do export.
