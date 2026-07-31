# PegaTicket — Codex Context

## Objetivo deste arquivo

Este é o meu resumo operacional do projeto para continuar o desenvolvimento com contexto próprio, sem depender de reler tudo do zero a cada tarefa.

Ele foi consolidado a partir de:

- `CLAUDE.md`
- `.claude/agents/*`
- `.claude/memory/*`
- estrutura real do repositório
- histórico git recente
- diff local ainda não commitado

## Estrutura real do projeto

Monorepo com git único na raiz:

```txt
pegaticket-saas/
  api/   Laravel 13 API
  web/   React 19 + Vite + TypeScript
```

`app/` continua reservado para futuro mobile/nativo e não existe ainda.

## Stack confirmada

### Backend

- Laravel 13
- PHP 8.3+
- JWT auth com refresh token
- MySQL/MariaDB
- Swagger
- arquitetura em camadas: Request -> Controller -> Service -> Repository -> Resource

### Frontend

- React 19
- TypeScript
- Vite
- React Router v7
- Axios
- MUI
- ag-Grid Community
- Chart.js

## Regras arquiteturais que viraram verdade do projeto

### Backend

- Controller é fino e não carrega regra de negócio.
- Entrada mutável passa por `FormRequest` + DTO.
- Service orquestra regra, transação e eventos.
- Persistência segue Repository Contract + Eloquent.
- Saída pública passa por Resource.
- Toda mutação relevante gera `Event` + `Listener` de auditoria.
- Todo model de domínio deve extender `BaseModel`.
- Toda tabela de domínio usa `id` interno + `uuid` público + `softDeletes` + `created_by/updated_by/deleted_by`.
- Toda rota autenticada usa throttle nomeado.
- Toda rota tenant-scoped usa `tenant` antes de `perm:`.
- Toda resposta segue `APIResponse::success/error`.
- Mensagens de usuário vêm de `__('messages...')`.

### Segurança importante já documentada e aplicada

- Route model binding por `uuid` não protege tenant por si só.
- Todo `update()`/`delete()` tenant-scoped precisa validar posse do registro no Service.
- Não capturar `\\RuntimeException` genericamente em Controller.
- FKs tenant-scoped em Request devem validar por `uuid` + `tenant_id` + `deleted_at null`.

## Domínio implementado hoje

### Backend já existente

- Auth: login, refresh, logout, troca de tenant, tenants do usuário
- RBAC de sistema: users, groups, functionalities, actions
- RBAC tenant: tenants, tenant roles, tenant users, tenant role permissions
- Auditoria
- Clientes
- Categorias de cliente
- Dias ideais
- Períodos ideais
- Localização global: estados, cidades, bairros
- Endereços tenant-scoped
- Categorias de produto
- Tipos de produto
- Produtos com upload de imagem
- Estoque: locations, balances, movements
- Pedidos
- Relatórios

### Frontend já existente

- Login ponta a ponta
- Sessão JWT com refresh automático
- Troca de tenant no layout
- Tema light/dark
- Dashboard com dados reais de relatórios
- CRUD/listagem de clientes
- CRUD/listagem de produtos
- CRUD/listagem de categorias de produto
- CRUD/listagem de tipos de produto
- Infra reutilizável de CRUD com MUI + ag-Grid

## Padrões reais do frontend

- `web/src/services/apiClient.ts` centraliza base URL, bearer token, refresh em 401 e normalização de boolean em query string para `1/0`.
- `AppLayout` é o shell autenticado padrão.
- Navegação atual:
  - `/`
  - `/clientes`
  - `/clientes/novo`
  - `/clientes/:uuid/editar`
  - `/produtos`
  - `/produtos/novo`
  - `/produtos/:uuid/editar`
  - `/produtos/categorias`
  - `/produtos/categorias/nova`
  - `/produtos/categorias/:uuid/editar`
  - `/produtos/tipos`
  - `/produtos/tipos/novo`
  - `/produtos/tipos/:uuid/editar`
- Listagens novas devem preferir `CrudListPage` + `ServerDataGrid`.
- Formulários novos devem preferir `CrudFormShell` ou `SchemaFormPage` quando couber.
- O uso é mobile-first. Isso é restrição de produto, não detalhe visual.

## Estado atual do git

Branch atual: `main`

Últimos commits visíveis:

```txt
61b4107 Ajustes
fa052ce Back finalizado
2ac0e55 Ajustes
0a1ee2b Cruds
1970453 Initial commit: PegaTicket SaaS (api + web)
```

## Onde o Claude provavelmente parou

Há alterações locais não commitadas no frontend:

- `web/src/components/crud/CrudFormShell.tsx`
- `web/src/components/crud/CrudListPage.tsx`
- `web/src/components/crud/ServerDataGrid.tsx`
- `web/src/pages/Client/ClientFormPage.tsx`
- `web/src/pages/Client/ClientListPage.tsx`

Essas mudanças indicam um trabalho recente de:

- ampliar largura útil das telas de formulário/listagem
- melhorar ocupação vertical do `ServerDataGrid`
- refinar responsividade do CRUD de clientes

Ou seja: o ponto mais recente de trabalho parece ser polimento/responsividade do frontend de CRUDs, não fundação de arquitetura.

## O que a memória do Claude acrescenta de útil

- O projeto já passou por engenharia reversa do legado.
- Não existe super-admin cross-tenant no produto novo.
- Estado/Cidade/Bairro são globais; Endereço é tenant-scoped.
- `DiaIdeal` e `PeriodoIdeal` são cadastros auxiliares simples.
- Dashboard já usa relatórios reais.
- ag-Grid virou contrato padrão para listagens server-side.
- Há contrato documentado de sort/filter/search entre grid e backend.

## O que `.remember` acrescentou

Praticamente nada operacional até aqui.

O conteúdo acessível em `.remember` está majoritariamente em logs automáticos de save, sem contexto funcional útil comparável à memória em `.claude/memory`.

## Próxima postura para continuar o projeto

Ao continuar daqui:

- tratar `CLAUDE.md` e `.claude/memory/*` como contexto histórico e regra de projeto
- tratar `CODEX.md` como resumo operacional rápido
- preservar mudanças locais não commitadas até entender se o usuário quer concluí-las, ajustá-las ou descartá-las
- priorizar continuidade do frontend de CRUDs e dos módulos ainda não expostos na UI, porque o backend já está mais avançado que o `web/`

## Próximos candidatos naturais de desenvolvimento

Em ordem mais provável:

1. finalizar o polimento das telas de CRUD já iniciadas
2. expor no frontend os módulos já prontos no backend e ainda ausentes da navegação
3. implementar pedidos no frontend
4. destravar as ações rápidas do dashboard
5. depois avançar em estoque e relatórios mais completos

## Nota operacional importante

Sempre ler arquivos em `.claude/...` a partir da raiz do projeto ou com caminho absoluto.

Já houve erro anterior por mudar o cwd para `api/` e tentar acessar `.claude/...` como se existisse dentro de `api/`.
