# Evidências — API de Cadastro (Account)

**PENDENTE — nenhum arquivo desta pasta é uma captura real.** Ver `../README.md` para o procedimento de preenchimento com credenciais de sandbox reais.

## Cenários a capturar (skill §41)

1. **Criar conta SELLER — Pessoa Física** — `POST (sandbox.)api.pagseguro.com/accounts` com `person_type=pf` (`PagBankAccountService::createSellerAccount()`, `api/app/Services/Payment/PagBankAccountService.php`), payload via `POST tenant-tools/pagbank-connect/create-account`.
2. **Criar conta SELLER — Pessoa Jurídica** — mesmo endpoint, `person_type=pj` (inclui bloco `company`).
3. **Consultar conta** — `GET (sandbox.)api.pagseguro.com/accounts/{id}` (`PagBankAccountService::syncStatus()`). **Pendência técnica conhecida**: o mapeamento de `provider_status` para o status interno (`mapProviderStatus()`) só foi confirmado para o valor `ACTIVE`; os demais valores possíveis (análise, recusa) não foram confirmados na doc oficial — este cenário real também deve capturar o catálogo completo de status observados.
4. **Rejeição de cadastro** (se reproduzível em sandbox com dado de teste inválido) — evidência de erro 400/422 da API de Cadastro.

## Sanitização específica desta pasta

Além da regra geral (`../README.md`), **nunca** incluir CPF/CNPJ real de teste sem mascarar, mesmo em sandbox — usar os CPFs/CNPJs de teste documentados pelo próprio PagBank para sandbox quando existirem, em vez de gerar dados fictícios que pareçam reais.

## Formato de cada arquivo

Mesmo formato de `../connect/README.md`. Arquivos esperados: `01-create-account-pf-request.json`, `01-create-account-pf-response.json`, `02-create-account-pj-request.json`, `02-create-account-pj-response.json`, `03-get-account-request.json`, `03-get-account-response.json`.
