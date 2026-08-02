# Checklist de implantação — módulos que dependem de dados da empresa

> Todo o código nativo dos módulos abaixo já está em produção (migrations aplicadas). Nenhum tenant é afetado automaticamente — cada item aqui só entra em vigor quando alguém preenche o dado correspondente. Este documento é a referência de "o que configurar" quando um cliente specific optar por usar cada módulo.

## Cobrança de planos (assinatura)
- Sem ação: tenant continua sem `subscription` (endpoint `GET /subscription` retorna vazio, nenhum bloqueio de acesso).
- Para ativar: 1) confirmar/ajustar `plan_prices` (já seedado com os 3 planos × 3 ciclos, desconto 10%/20%); 2) decidir e implementar o ponto que liga `SubscriptionService::create()` ao signup (hoje deliberadamente desligado — ver `architecture-decisions.md`, Onda 1); 3) trocar `PaymentProviderInterface` do bind `ManualPaymentProvider` por um adapter de PSP real antes de cobrar de verdade (hoje toda cobrança fica `pending`).

## Pagamento de venda (cliente final → tenant)
- Sem ação: `tenant_settings.payment_receiving_method` fica `manual` (comportamento atual, sem mudança).
- Para ativar por tenant: o próprio dono cadastra `payment_pix_key` em Configurações (campo já existe, criptografado); ainda depende de um adapter real de `PaymentProviderInterface` pra o Pix ser cobrado de verdade — sem isso, `createPixChargeForOrder` só registra intenção `pending`.

## Cadastro fiscal / nota fiscal
- Sem ação: nenhum campo fiscal preenchido, nenhuma nota é gerada, sistema funciona igual hoje.
- Para ativar por tenant (pré-requisito de qualquer emissão real futura): preencher `tenants.cnpj/ie/im/cnae/tax_regime/ibge_city_code`; cadastrar `ncm`/`cest`/`csosn_cst` nos produtos relevantes; cadastrar `tax_rules` (hoje sem motor de cálculo automático — cadastro puro). Emissão real (D1/D2 do roadmap) continua fora de escopo até se contratar um serviço de emissão ou internalizar `sped-nfe`.

## Módulo do contador
- Sem ação: nenhuma tela nova aparece pro tenant além do item "Acesso de contadores" em Configurações (lista vazia).
- Para ativar: o escritório de contabilidade se cadastra em `/contador/cadastro` (fora do painel do tenant, autoatendimento do próprio contador) e solicita acesso pelo CNPJ do tenant — **pré-requisito: `tenants.cnpj` precisa estar preenchido**, senão a solicitação não encontra a empresa. O dono aprova em Configurações → Acesso de contadores, escolhendo os escopos.

## Observação geral
Nenhum destes módulos exige migration adicional pra "ligar" — são todos flags/dados por tenant sobre uma estrutura já pronta. O único trabalho de implantação real por cliente é: preencher os campos acima quando o cliente pedir, e (separadamente, fora deste checklist) contratar/plugar o PSP e o serviço de emissão fiscal quando o negócio decidir cobrar/emitir nota de verdade.
