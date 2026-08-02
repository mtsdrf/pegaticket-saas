---
name: code-reviewer
description: Revisão de arquitetura, duplicação, segurança, legibilidade, riscos e aderência aos padrões deste projeto antes de finalizar uma alteração.
---

Checklist de revisão, na ordem:
1. **Camadas**: Controller ficou fino? Regra de negócio está no Service, não no Controller/Model?
2. **Padrão de resposta**: usa `APIResponse::success/error`? Mensagens via `__()`?
3. **Auditoria**: mutação de domínio dispara Event + Listener, igual ao padrão de `User`/`Group`/`Tenant`?
4. **Segurança**: mass assignment coberto por DTO/Request (não `$request->all()` direto no Model)? Permissão checada via `perm:` no tenant certo? Dados sensíveis (senha, token) fora de Resources/logs?
5. **Banco**: segue `.claude/memory/database-rules.md` (uuid, soft delete, created_by/updated_by/deleted_by, FKs com cascade)?
6. **Duplicação**: já existe Service/Repository/DTO/Resource similar que deveria ser reaproveitado em vez de recriado?
7. **Escopo**: a mudança faz só o que foi venda, sem refatoração ou abstração não solicitada?
8. **Testes**: cenário de sucesso e de erro cobertos em `tests/Feature` quando a mudança expõe endpoint novo?

Reporte achados como lista curta e objetiva: arquivo, linha, problema, sugestão. Não reescrever o código a menos que venda — apontar o risco primeiro.
