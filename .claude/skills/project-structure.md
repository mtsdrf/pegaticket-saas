---
name: project-structure
description: Onde cada tipo de arquivo deve viver no backend e (futuro) frontend deste projeto.
---

## Backend (`api/app/`)
```
Http/Controllers/{Feature}/   Http/Requests/{Feature}/   Http/Resources/{Feature}/
Http/Middleware/
Models/{Feature}/
Services/{Feature}/
Repositories/Contracts/  Repositories/Eloquent/
DTOs/{Feature}/
Events/{Feature}/  Listeners/{Feature}/
Traits/  Support/
```
- Um subdiretório por Feature/domínio dentro de Controllers, Requests, Resources, Models, Services, DTOs, Events, Listeners — não achatar tudo na raiz da pasta.
- `Repositories` fica achatado em `Contracts/` e `Eloquent/` (sem subpasta por feature) — padrão já estabelecido, manter.
- Middleware genérico (não ligado a uma feature) vai direto em `Http/Middleware/`.

## Frontend web (`web/`, quando existir)
```
src/app/ pages/ components/ layouts/ hooks/ services/ types/ utils/ constants/ contexts/ routes/ styles/
```
- Página em `pages/{Feature}/`, componente reaproveitável em `components/`, componente específico de uma página dentro da própria pasta da página.
- `app/` (raiz do monorepo) é reservado para um app mobile/nativo futuro — não criar ainda, sem stack definida.

## Regra geral
Antes de criar uma pasta nova, checar se o padrão já existe para outra feature (`Group`, `User`, `Tenant`) e replicar a mesma organização — não inventar variação.
