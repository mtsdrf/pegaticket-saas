---
name: laravel-api
description: Padrão para criar um recurso REST completo no backend Laravel deste projeto (api/).
---

## Passo a passo para um recurso novo (ex: `Product`)

1. **Migration**: `database/migrations/..._create_products_table.php` — seguir `.claude/memory/database-rules.md` (id + uuid, created_by/updated_by/deleted_by, timestamps+softDeletes).
2. **Model**: `app/Models/Product/Product.php extends BaseModel`.
3. **DTOs**: `app/DTOs/Product/CreateProductDTO.php`, `UpdateProductDTO.php`, com `fromArray()`.
4. **Repository**: `app/Repositories/Contracts/ProductRepositoryInterface.php` + `app/Repositories/Eloquent/ProductRepository.php`, bind no `AppServiceProvider`.
5. **Service**: `app/Services/Product/ProductService.php` — `paginate/create/update/delete`, `DB::transaction`, dispara Events.
6. **Events + Listeners**: `app/Events/Product/{ProductCreated,Updated,Deleted}.php` + `app/Listeners/Product/Audit*` (trait `Auditable`).
7. **Requests**: `app/Http/Requests/Product/StoreProductRequest.php`, `UpdateProductRequest.php`.
8. **Resource**: `app/Http/Resources/Product/ProductResource.php`.
9. **Controller**: `app/Http/Controllers/Product/ProductController.php` — fino, delega ao Service, retorna via `APIResponse`.
10. **Rota** em `routes/api.php`, dentro de `prefix('v1')`, com `throttle:` nomeado e `perm:products,{action}` (+ `tenant` se for tenant-scoped).
11. **Permissão**: adicionar `Functionality` correspondente em `database/seeders/FunctionalitiesSeeder.php`.
12. **Mensagens**: chaves em `lang/{locale}/messages.php` sob `product.*`.
13. **Swagger**: doc em `resources/docs/{En,PtBR}/` seguindo o padrão de `Auth`.
14. **Testes**: `tests/Feature/Product/...` cobrindo sucesso + erro + permissão negada.

## Regras de resposta
- Sucesso: `APIResponse::success($data, __('messages.x.y'), $status, $meta)`.
- Erro: `APIResponse::error(__('messages.x.y'), $status, 'CODE', $errors)`.
- Nunca `response()->json()` direto num Controller de domínio.

## Regras de segurança
- Nunca `$request->all()` direto num Model — sempre via Request validado → DTO.
- Toda rota de mutação exige `perm:{slug},{action}`; toda rota tenant-scoped exige `tenant` antes.
