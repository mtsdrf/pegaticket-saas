---
name: coding-standards
description: Padrões de código já estabelecidos no backend deste projeto.
metadata:
  type: project
---

- Controller: construtor com `private {Feature}Service $service`; métodos finos (`index/store/show/update/destroy`), sem lógica de negócio.
- Toda resposta HTTP via `App\Services\APIResponse::success/error` — nunca `response()->json()` cru num Controller de domínio.
- Toda mensagem de usuário via `__('messages.{feature}.{acao}')`, nunca string hardcoded.
- DTO de entrada: `readonly` implícito por convenção do projeto, construído via `fromArray($request->validated())`.
- Service: sempre `DB::transaction()` quando há mais de uma escrita (ex: criar + sincronizar relação), dispara Event ao final, usa `Auth::id()` para `actorId`.
- Model de domínio: `extends BaseModel` (nunca `Model` puro) — dá UUID, soft delete e `created_by/updated_by/deleted_by` automáticos via `Auth::id()`.
- Nome de rota de permissão: `perm:{functionality_slug},{action_key}` — slug e action já devem existir via seeder antes da rota usar.
- Throttle: sempre nomeado (`throttle:{max},{min},{nome-unico}`), nome único por rota para não colidir limites entre endpoints diferentes.
- PHPDoc nos Services do projeto é verboso (ver `UserService`) — manter o padrão existente ao editar arquivos que já têm isso, mas não é obrigatório exigir em código novo se o time preferir enxuto (checar com o usuário se preferir migrar o padrão).
- **Recurso tenant-scoped**: `update()`/`delete()` do Service sempre validam posse do tenant primeiro (`assertBelongsToCurrentTenant`, 404 se não bater) — route-model-binding não filtra tenant sozinho. Ver `.claude/memory/api-patterns.md`.
- **Exceção de regra de negócio**: nunca `catch (\RuntimeException $e)` genérico num Controller — usar exceção dedicada (`App\Exceptions\DuplicateNameException` e futuras análogas), porque `abort()`/exceções HTTP do Symfony também estendem `\RuntimeException`.
- **Leitura de `.claude/memory/*.md` em comandos de shell/briefs de agente**: sempre caminho absoluto (`/home/mtsdrf/workspace/pegaticket-saas/.claude/memory/...`) ou confirmar que o cwd é a raiz do projeto antes. Tarefas de backend frequentemente fazem `cd api/` no meio da execução (não existe `api/.claude/`) — um `cat .claude/memory/x.md` relativo depois disso falha com "arquivo inexistente" (achado 2026-07-09, sem perda de conteúdo real, só leitura no diretório errado). Mesmo cuidado vale para `web/`.

- **Texto voltado ao cliente final/proprietário nunca expõe detalhe técnico de implementação**: nada de "o backend", "a API", "o servidor", "requisição", "endpoint" em mensagem, label, texto de ajuda ou copy de botão visível a usuário não-técnico. Trocar por linguagem do que a pessoa reconhece (ex: "Você será direcionado ao Mercado Pago para autorizar a cobrança" em vez de "O redirecionamento só acontece depois que o backend cria a assinatura com segurança" — achado e corrigido em `OwnerCompanyPage.tsx`/`SubscriptionPage.tsx`, 2026-07-24). Vale para toda tela de cliente final (Portal, loja, área do proprietário) — telas internas de staff/admin têm mais tolerância a linguagem técnica, mas ainda assim preferir clareza.

Ver também [[architecture-decisions]] para o porquê de cada padrão.
