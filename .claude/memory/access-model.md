---
name: access-model
description: Modelo real de autorização e implicações operacionais do PegaTicket.
metadata:
  type: security
---

# Modelo de acesso atual

## Camadas de acesso ativas em 2026-07-10

1. **Autenticação global** via JWT.
2. **Contexto de tenant ativo** via `auth/switch-tenant` + claims `tenant_id`/`tenant_uuid` no token.
3. **Vínculo ativo com o tenant** exigido pelo middleware `tenant`:
   - tenant ativo
   - `TenantUser` ativo
   - `TenantRole` ativo
4. **Permissão de rota efetiva** via middleware `perm`, com modelo híbrido:
   - rotas globais: `GroupPermission`
   - rotas tenant-scoped com `tenant` resolvido: `GroupPermission` **ou** `TenantRolePermission`
5. **Gate comercial por plano do tenant** (desde 2026-07-12):
   - em rotas tenant-scoped, depois da permissão do usuário passar, a `Functionality` também precisa existir em `plan_functionalities` para o `tenant.plan_id` ativo
   - a negativa por plano retorna `403` com código `PLAN_UPGRADE_REQUIRED`, não `FORBIDDEN`

## Modelo híbrido atual

O sistema já tem CRUD de `tenant-roles`, `tenant-users` e `tenant-role-permissions`, e agora o middleware já permite que permissões do `TenantRole` abram rotas tenant-scoped. Na prática:

- rotas globais (`users`, `groups`, `functionalities`, `tenants`, catálogos globais) dependem de `GroupPermission`
- rotas tenant-scoped (`clients`, `products`, `stock`, `sales`, `reports`, `tenant_roles`, `tenant_users` etc.) dependem do middleware `tenant` e aceitam autorização por `GroupPermission` **ou** `TenantRolePermission`
- nessas rotas tenant-scoped, passar pela permissão do usuário já não basta: o módulo também precisa estar habilitado no plano do tenant
- isso permite usar grupos globais mínimos para governança e perfis do tenant para operação real do dia a dia

## Implicações operacionais

- Um usuário **sem grupo global** não acessa módulos globais da plataforma.
- Um usuário com `TenantUser` ativo + `TenantRolePermission` adequado já consegue acessar módulos tenant-scoped, desde que esteja no tenant certo.
- Um usuário **com grupos globais operacionais** continua podendo acessar os módulos permitidos em qualquer tenant do qual ele seja membro ativo, então grupos globais devem continuar mínimos.
- Um usuário **com grupos globais administrativos** acessa administração da plataforma inteira, independentemente do tenant ativo.
- Um usuário administrativo da plataforma ainda pode receber `PLAN_UPGRADE_REQUIRED` ao operar dentro de um tenant cujo plano não inclui o módulo; o caminho correto é trocar o plano do tenant, não bypassar a regra no usuário.
- O `Owner` criado automaticamente no momento da criação do tenant recebe todas as `tenant_role_permissions`, então agora ele já consegue operar os módulos tenant-scoped sem depender de grupos operacionais globais.
- Regra de transição para rollout seguro: se algum tenant legado ainda estiver sem `plan_id`, o gate se comporta como permissivo até o backfill acontecer. A base ativa já foi retroalimentada para `premium`, mas a tolerância continua útil para fixtures antigas e migrações intermediárias.

## Fluxo seguro recomendado com o backend atual

- **Admin máximo da plataforma**:
  - deve ter grupos globais com permissões administrativas e operacionais conforme necessidade
  - deve ser membro ativo do tenant para conseguir trocar para ele e operar módulos tenant-scoped
- **Usuário operacional do tenant**:
  - deve ser membro ativo do tenant (`TenantUser`)
  - deve ter `TenantRole` ativo
  - deve receber grupos globais mínimos, idealmente só o grupo `clients` se precisar gerenciar usuários/perfis do próprio tenant
  - não deve receber permissões globais de plataforma (`users`, `groups`, `functionalities`, `tenants`, `estados`, `cidades`, `bairros`) se a intenção é restringi-lo ao operacional

## Risco/Gap aberto

Ainda existem pontos de atenção:

- `users` continua sendo um recurso global; a contenção para clientes agora acontece por escopo aplicado no backend quando há tenant ativo e o usuário não é `administrators`
- o grupo `clients` é uma exceção operacional para permitir auto-administração do tenant; ele deve permanecer mínimo
- a navegação do frontend ainda não está 100% permission-aware, então parte das restrições aparece como erro controlado de acesso, não como ocultação total de menu
