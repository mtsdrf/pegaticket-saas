# Checklist final de homologação PagBank — PegaTicket

Baseado em `.claude/skills/pagbank-integration.md` §63. Vivo — marcar conforme as fases do roadmap
(`docs/roadmap/2026-08-07-pagbank-homologacao-producao-roadmap.md`) avançam. Não marcar item sem evidência real.

```text
[x] Conta PagBank primária correta                         (platform_finance_settings.pagbank_primary_account_id)
[ ] Aplicação Connect configurada                           — Fase R2
[ ] API Account configurada                                 — Fase R2 (avaliar se necessária além de Connect)
[ ] Sellers de teste funcionando                             — Fase R2/R7
[x] Order funcionando                                        (PagBankPaymentProvider::createChargeForOrder)
[x] Split funcionando                                        (buildSplitPayload + custódia)
[ ] Cartão criptografado pelo mecanismo oficial              — confirmar SDK de tokenização no frontend, Fase R4
[x] Pix funcionando                                          (Order com qr_codes)
[x] Webhook funcionando                                      (PaymentWebhookController::handlePagBank)
[x] Idempotência de webhook funcionando                      (WebhookEvent, uniq_webhook_provider_type_external)
[ ] Idempotência de criação de charge/retry por timeout       — verificar, Fase R2/execução
[ ] ReCaptcha/anti-bot funcionando                            — Fase R4
[x] Cancelamento funcionando                                 (SalePaymentService::createRefundForPaidCancellation)
[x] Reembolso parcial funcionando                             (SaleRefundService + sale_refund_tickets)
[ ] Chargeback validado                                       — Fase R3 (ausente no rail PagBank hoje)
[ ] Nenhum PAN em log                                         — confirmar por leitura direta, Fase R4
[ ] Nenhum CVV armazenado                                     — confirmar por leitura direta, Fase R4
[x] Tokens protegidos                                         (access_token encrypted em tenant_settings)
[ ] Request/response Order anexados                           — Fase R7
[ ] Request/response Split anexados                           — Fase R7
[ ] Request/response Connect anexados                         — Fase R7 (depende de R2)
[ ] Request/response Account anexados                         — Fase R7 (depende de R2)
[ ] Webhook documentado                                        — Fase R7
[ ] Dados pessoais sanitizados nas evidências                  — Fase R7
[ ] URL de homologação acessível                               — Fase R7
[ ] Credencial de homologação criada                           — Fase R7
[ ] Passo a passo de acesso escrito                            — Fase R7
[ ] Produtos/serviços descritos                                — Fase R7
[ ] Documento da empresa confirmado                            — Fase R7, dado real do usuário
[ ] E-mail PagBank confirmado                                  — Fase R7, dado real do usuário
[ ] Responsável técnico confirmado                             — Fase R7, dado real do usuário
[ ] URL oficial confirmada                                     — Fase R7
[ ] Formulário revisado                                        — Fase R7
[ ] ZIP de evidências criado                                   — Fase R7
```

## Decisões de escopo já fechadas (não reabrir sem motivo novo)

Ver seção 5 de `docs/roadmap/2026-08-07-pagbank-homologacao-producao-roadmap.md`:

1. Custo PSP: plataforma rastreia (`pagbank_fee_actual`), não redistribui automaticamente ainda.
2. Refund/chargeback: consome líquido do organizador primeiro; excedente vira exposição da plataforma em revisão.
3. Fechamento continua por evento, não consolidado por tenant.

## Serviços a marcar no formulário de homologação (skill §45)

```text
☑ API de Pedidos e Pagamentos (Order)
☑ Split de Pagamentos (Order)
☑ API Connect            ← só marcar quando Fase R2 estiver implementada de verdade
☑ API de Cadastro         ← idem
☑ API de Notificação
☐ API de Chargeback       ← só marcar se Fase R3 for implementada antes do chamado
```

Não marcar `API transferência` nem `API PIX` dedicada (skill §10, §45).
