# Evidências — API Connect

**PENDENTE — nenhum arquivo desta pasta é uma captura real.** Ver `../README.md` para o procedimento de preenchimento com credenciais de sandbox reais.

## Cenários a capturar (skill §41)

1. **Autorização** — `GET connect.sandbox.pagbank.com.br/oauth2/authorize?...` (o redirect gerado por `PagBankConnectService::buildAuthorizationUrl()`, `api/app/Services/Payment/PagBankConnectService.php`) + a URL de callback recebida do PagBank após o vendedor de teste autorizar.
2. **Troca de código por token** — `POST (sandbox.)api.pagseguro.com/oauth2/token` (`PagBankConnectService::handleCallback()`).
3. **Renovação** — `POST (sandbox.)api.pagseguro.com/oauth2/refresh` (`PagBankConnectService::refreshTokenIfNeeded()`). **Pendência técnica conhecida**: o header `Authorization: Bearer <token da aplicação>` desse endpoint (e de `/oauth2/token`/`/oauth2/revoke`) foi implementado assumindo que é o mesmo `PAGBANK_TOKEN_SANDBOX` já usado por `PagBankPaymentProvider` — não confirmado com o suporte PagBank. Este cenário de sandbox real também serve para validar essa suposição antes de produção.
4. **Revogação** — `POST (sandbox.)api.pagseguro.com/oauth2/revoke` (`PagBankConnectService::disconnect()`).

## Formato de cada arquivo (skill §39)

```
Nome do cenário / Data / Ambiente (SANDBOX)
Endpoint / Método HTTP
Request Headers sanitizados / Request Body sanitizado
HTTP Status
Response Headers relevantes / Response Body
IDs relacionados
Resultado esperado / Resultado obtido
```

Arquivos esperados após execução real: `01-authorize-request.json`, `01-authorize-callback.json`, `02-token-exchange-request.json`, `02-token-exchange-response.json`, `03-refresh-request.json`, `03-refresh-response.json`, `04-revoke-request.json`, `04-revoke-response.json`.
