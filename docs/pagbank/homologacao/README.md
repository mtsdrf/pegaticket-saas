# Homologação PagBank — PegaTicket

Sistema: **PegaTicket**
Tipo: **Desenvolvimento próprio**
Ambiente: Sandbox (evidências desta pasta) → Produção (pasta separada, criada só após homologação aprovada)
Modelo financeiro: Marketplace com Split — PegaTicket como recebedor primário, tenant (organizador) como recebedor secundário.
Produtos: Venda online de ingressos para eventos.

Serviços implementados e a marcar no formulário de homologação (`.claude/skills/pagbank-integration.md` §45):

```
☑ API de Pedidos e Pagamentos (Order)
☑ Split de Pagamentos (Order)
☑ API Connect
☑ API de Cadastro (Account)
☑ API de Notificação
☐ API de Chargeback        — NÃO marcar (fase R3 do roadmap, ainda não implementada)
```

Não marcar `API transferência` (o repasse é via Split) nem `API PIX dedicada` (Pix é via Order).

## Status desta pasta — 2026-08-08

**Scaffold criado, sem evidência real ainda.** Não há credenciais de sandbox PagBank (`PAGBANK_TOKEN_SANDBOX`, `PAGBANK_CONNECT_CLIENT_ID`/`PAGBANK_CONNECT_CLIENT_SECRET`) configuradas neste ambiente. A skill (§39-40, §51) e o roadmap (`docs/roadmap/2026-08-07-pagbank-homologacao-producao-roadmap.md`, seção 9/R2.7) exigem evidência **real**, nunca inventada — por isso os arquivos `request.json`/`response.json` de cada subpasta são **templates com placeholders**, não capturas reais, e o checklist (`../00-checklist.md`) não deve marcar os itens de evidência como concluídos até isso ser substituído por dados reais.

### Como preencher quando houver credenciais de sandbox

1. Configurar `.env` local com `PAGBANK_ENVIRONMENT=sandbox`, `PAGBANK_TOKEN_SANDBOX`, `PAGBANK_CONNECT_CLIENT_ID`, `PAGBANK_CONNECT_CLIENT_SECRET`, `PAGBANK_CONNECT_REDIRECT_URI` apontando para um ambiente acessível.
2. Executar o fluxo real: `GET tenant-tools/pagbank-connect/authorize-url` → autorizar no PagBank sandbox → callback real → `POST tenant-tools/pagbank-connect/create-account` com dados de teste.
3. Capturar cada request/response real (sanitizado — ver regra de sanitização abaixo) e substituir os arquivos template em `connect/` e `account/`.
4. Marcar os itens correspondentes em `../00-checklist.md`.

### Regra de sanitização (skill §40, obrigatória antes de qualquer commit/anexo)

Remover sempre: `Authorization`, `access_token`, `refresh_token`, `client_secret`, CVV/PAN (não aplicável aqui, cartão não passa por Connect/Account), CPF/CNPJ completo (mascarar), e-mail/telefone reais de teste. Manter identificadores técnicos necessários (`account_id`, `order_id`) quando o próprio suporte PagBank precisar deles para localizar uma operação.
