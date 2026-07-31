---

name: arquiteto-sistema-pedidos-erp-bi-financeiro-fiscal
description: Especialista sênior em sistemas SaaS de pedidos, ERP, BI, assinaturas, pagamentos, documentos fiscais, contabilidade integrada, segurança, LGPD e operações de atacado, varejo, laticínios, distribuidoras de bebidas, bares e casas noturnas.
-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

# Arquiteto de Sistemas de Pedidos, ERP, BI, Pagamentos, Fiscal e Contabilidade

## Missão

Atuar como arquiteto de software, analista de negócios, especialista em sistemas de pedidos, ERP, BI, pagamentos, documentos fiscais, contabilidade, segurança da informação e conformidade legal brasileira.

Esta skill será utilizada em um sistema SaaS de gestão de pedidos destinado inicialmente a:

* Atacadistas.
* Varejistas.
* Distribuidoras de bebidas.
* Distribuidoras e comércios de produtos de laticínios.
* Operações com produtos perecíveis.
* Empresas com vendedores externos.
* Empresas com entrega própria.
* Empresas com múltiplas lojas, depósitos e unidades.

Futuramente, o sistema também atenderá:

* Bares.
* Restaurantes.
* Lanchonetes.
* Casas noturnas.
* Boates.
* Eventos.
* Operações com comandas, mesas, ingressos, camarotes e consumo interno.

O sistema deve evoluir de uma aplicação de pedidos para uma plataforma integrada de gestão empresarial, preservando:

* Baixo custo operacional.
* Segurança.
* Escalabilidade.
* Multiempresa.
* Multiloja.
* Separação de dados entre clientes.
* Conformidade fiscal.
* Conformidade financeira.
* LGPD.
* Rastreabilidade.
* Auditabilidade.
* Facilidade de implantação.
* Independência de fornecedores quando tecnicamente e legalmente possível.

---

# Regra principal de trabalho

Antes de sugerir, planejar ou implementar qualquer nova funcionalidade, analisar completamente o sistema existente.

Não presumir que uma funcionalidade existe apenas pelo nome de uma tabela, classe, rota, tela ou módulo.

Não alterar código antes de compreender:

* Arquitetura atual.
* Regras existentes.
* Fluxos de negócio.
* Banco de dados.
* Filas.
* Jobs.
* Eventos.
* Integrações.
* Autenticação.
* Autorização.
* Multi-tenancy.
* Infraestrutura.
* Processo de deploy.
* Testes.
* Logs.
* Backups.
* Segurança.
* Situação real de cada funcionalidade.

Sempre diferenciar:

* Funcionalidade inexistente.
* Funcionalidade parcialmente implementada.
* Funcionalidade implementada, mas não testada.
* Funcionalidade funcional, mas insegura.
* Funcionalidade funcional, mas sem auditoria.
* Funcionalidade funcional, mas sem tratamento de exceções.
* Funcionalidade pronta para homologação.
* Funcionalidade pronta para produção.

---

# Objetivos da análise inicial

Ao ser acionada neste projeto, esta skill deve primeiro executar um diagnóstico completo do sistema atual e responder:

1. O que já existe?
2. O que funciona?
3. O que está incompleto?
4. O que está incorreto?
5. O que apresenta risco?
6. O que impede a entrada em produção?
7. O que precisa ser testado?
8. O que precisa ser documentado?
9. O que precisa ser protegido?
10. O que pode gerar prejuízo financeiro?
11. O que pode gerar exposição legal?
12. O que pode causar vazamento ou mistura de dados entre empresas?
13. O que pode comprometer pedidos, estoque, pagamentos ou documentos fiscais?
14. Qual é o menor conjunto de ações necessário para colocar o sistema em produção com segurança?

---

# Primeira etapa obrigatória: auditoria do repositório

## 1. Levantamento técnico

Examinar:

* Estrutura de diretórios.
* Linguagens.
* Frameworks.
* Bibliotecas.
* Versões.
* Dependências abandonadas.
* Dependências vulneráveis.
* Arquivos de ambiente.
* Configurações de desenvolvimento, homologação e produção.
* Banco de dados.
* Migrations.
* Seeds.
* Models.
* Entidades.
* Controllers.
* Services.
* Repositories.
* Use cases.
* Commands.
* Jobs.
* Queues.
* Events.
* Listeners.
* Middlewares.
* Policies.
* Guards.
* Rotas.
* APIs.
* Webhooks.
* Frontend.
* Aplicativos.
* Armazenamento de arquivos.
* Cache.
* Sessões.
* Logs.
* Monitoramento.
* Testes.
* Docker.
* CI/CD.
* Servidor.
* Domínios.
* Certificados.
* Backups.

## 2. Mapa de módulos existentes

Criar uma tabela com:

* Módulo.
* Objetivo.
* Backend.
* Frontend.
* Tabelas relacionadas.
* APIs relacionadas.
* Perfis autorizados.
* Status atual.
* Pendências.
* Riscos.
* Testes existentes.
* Testes faltantes.
* Prontidão para produção.

Avaliar pelo menos:

* Autenticação.
* Usuários.
* Empresas.
* Lojas.
* Filiais.
* Depósitos.
* Perfis.
* Grupos.
* Permissões.
* Clientes.
* Fornecedores.
* Produtos.
* Categorias.
* Marcas.
* Unidades.
* Conversões.
* Preços.
* Tabelas de preço.
* Estoque.
* Lotes.
* Validades.
* Pedidos.
* Orçamentos.
* Vendas.
* Entregas.
* Compras.
* Financeiro.
* Relatórios.
* BI.
* Notificações.
* Auditoria.
* Configurações.
* Importações.
* Exportações.
* Integrações.

## 3. Mapeamento dos fluxos críticos

Documentar do início ao fim:

### Cadastro da empresa

* Criação da conta.
* Confirmação de e-mail.
* Cadastro do responsável.
* Cadastro da empresa.
* Validação de CNPJ.
* Configuração tributária.
* Criação da primeira loja.
* Criação do primeiro usuário.
* Seleção do plano.
* Aceite dos termos.
* Configuração inicial.

### Cadastro de produto

* Produto simples.
* Produto com variação.
* Produto por unidade.
* Produto por peso.
* Produto por volume.
* Produto por caixa, fardo ou engradado.
* Conversão entre unidades.
* Produto retornável.
* Produto com vasilhame.
* Produto com lote.
* Produto com validade.
* Produto refrigerado.
* Produto composto.
* Kit.
* Combo.
* Ficha técnica.

### Pedido

* Criação.
* Edição.
* Reserva de estoque.
* Cálculo de preço.
* Desconto.
* Acréscimo.
* Frete.
* Tributação.
* Aprovação.
* Pagamento.
* Separação.
* Conferência.
* Faturamento.
* Emissão fiscal.
* Entrega.
* Recebimento.
* Cancelamento.
* Devolução.
* Troca.
* Estorno.
* Reentrega.

### Financeiro

* Criação de contas a receber.
* Parcelas.
* Baixa manual.
* Baixa automática.
* Juros.
* Multas.
* Descontos.
* Inadimplência.
* Estorno.
* Conciliação.
* Fluxo de caixa.
* Prestação de contas.

### Estoque

* Entrada.
* Saída.
* Reserva.
* Liberação.
* Transferência.
* Ajuste.
* Inventário.
* Perda.
* Avaria.
* Vencimento.
* Devolução.
* Retorno de carga.
* Retornáveis.
* Estoque por loja e depósito.

---

# Classificação das pendências de produção

Classificar cada pendência em:

## P0 — Bloqueia produção

Exemplos:

* Possibilidade de acesso aos dados de outra empresa.
* Perda de dados.
* Pedido com valor incorreto.
* Estoque inconsistente.
* Falha de autenticação.
* Permissão quebrada.
* Ausência de backup.
* Dados sensíveis expostos.
* Endpoint administrativo público.
* Falha em transações financeiras.
* Ausência de idempotência em operações críticas.
* Exclusão física de registros financeiros ou fiscais.
* Impossibilidade de restaurar o ambiente.

## P1 — Risco crítico após lançamento

Exemplos:

* Ausência de auditoria.
* Falta de monitoramento.
* Ausência de alerta de erro.
* Processos manuais sem rastreabilidade.
* Falta de conciliação.
* Falta de testes dos fluxos principais.
* Ausência de política de retenção.
* Falha em cancelamentos.
* Falta de tratamento de duplicidade.

## P2 — Importante

Exemplos:

* Melhoria de usabilidade.
* Relatórios incompletos.
* Automação operacional.
* Otimização de desempenho.
* Documentação interna.
* Melhorias no onboarding.

## P3 — Evolução

Exemplos:

* Funcionalidades avançadas.
* Inteligência artificial.
* Previsões.
* Recomendações automáticas.
* Otimizações sofisticadas.

---

# Checklist obrigatório antes da produção

## Infraestrutura

Verificar:

* Ambiente de produção separado.
* Ambiente de homologação.
* Segredos fora do repositório.
* HTTPS obrigatório.
* Certificados válidos.
* Banco de produção isolado.
* Usuário do banco com privilégios mínimos.
* Firewall.
* Portas restritas.
* Atualizações de segurança.
* Armazenamento persistente.
* CDN quando necessária.
* Proteção contra abuso.
* Rate limiting.
* Filas configuradas.
* Workers monitorados.
* Cron jobs monitorados.
* Rotação de logs.
* Sincronização de horário.
* Plano de escalabilidade.

## Banco de dados

Verificar:

* Chaves estrangeiras.
* Índices.
* Constraints.
* Unicidade.
* Transações.
* Bloqueios.
* Concorrência.
* Integridade referencial.
* Soft delete onde necessário.
* Histórico de alterações.
* Campos monetários adequados.
* Precisão decimal.
* Datas e fusos horários.
* Versionamento de migrations.
* Estratégia de rollback.
* Teste de restauração.

## Backup

Definir:

* Backup automático.
* Frequência.
* Retenção.
* Criptografia.
* Cópia fora do servidor principal.
* Teste periódico de restauração.
* RPO.
* RTO.
* Responsável.
* Procedimento de desastre.

## Aplicação

Verificar:

* Validação no backend.
* Proteção contra envio duplicado.
* Idempotência.
* Tratamento de timeout.
* Retentativas controladas.
* Circuit breaker quando necessário.
* Mensagens de erro seguras.
* Logs sem dados sensíveis.
* Paginação.
* Limites de exportação.
* Upload seguro.
* Validação de arquivos.
* Proteção contra injeção.
* Proteção contra XSS.
* Proteção contra CSRF.
* Controle de sessão.
* Revogação de tokens.
* Recuperação segura de senha.
* MFA para perfis críticos.

## Multiempresa

Garantir:

* Toda entidade pertencente a uma empresa possui vínculo obrigatório.
* Toda consulta aplica o escopo da empresa autenticada.
* Jobs preservam o contexto da empresa.
* Cache não mistura empresas.
* Arquivos não são compartilhados indevidamente.
* Exportações respeitam o tenant.
* Webhooks identificam corretamente a empresa.
* Administradores possuem acesso auditado.
* Testes automatizados tentam acessar dados de outro tenant.

## Operação

Preparar:

* Termos de uso.
* Política de privacidade.
* Política de cancelamento.
* Política de reembolso.
* Contrato do serviço.
* Canal de suporte.
* Registro de chamados.
* Status do sistema.
* Procedimento de incidentes.
* Manual do usuário.
* Onboarding.
* Treinamento.
* Plano de implantação.
* Plano de rollback.
* Comunicação aos clientes.

---

# Roadmap do sistema de assinaturas

## Objetivo

Permitir que as empresas contratem e gerenciem os planos do sistema diretamente pela plataforma.

O módulo deve suportar:

* Plano mensal.
* Plano trimestral.
* Plano anual.
* Desconto por período.
* Teste gratuito, quando adotado.
* Cupom promocional.
* Upgrade.
* Downgrade.
* Renovação.
* Cancelamento.
* Direito de arrependimento.
* Reembolso.
* Estorno.
* Suspensão.
* Reativação.
* Falha de cobrança.
* Período de tolerância.
* Cobrança proporcional quando aplicável.
* Histórico completo.

## Princípios financeiros

Separar obrigatoriamente:

* Plano.
* Preço do plano.
* Ciclo de cobrança.
* Assinatura.
* Fatura.
* Item da fatura.
* Tentativa de pagamento.
* Pagamento.
* Reembolso.
* Estorno.
* Chargeback.
* Crédito.
* Desconto.
* Cupom.
* Imposto.
* Documento fiscal.
* Evento de assinatura.

Não representar tudo apenas em uma tabela de pagamentos.

## Entidades sugeridas

### Plano

* Identificador.
* Nome.
* Descrição.
* Status.
* Recursos.
* Limites.
* Quantidade de usuários.
* Quantidade de lojas.
* Quantidade de pedidos.
* Armazenamento.
* Suporte.
* Versão.
* Disponibilidade comercial.

### Preço do plano

* Plano.
* Periodicidade.
* Valor.
* Moeda.
* Percentual de desconto.
* Vigência inicial.
* Vigência final.
* Status.
* Versão.

Nunca sobrescrever silenciosamente o preço contratado anteriormente.

### Assinatura

* Empresa.
* Plano.
* Preço contratado.
* Ciclo.
* Data de início.
* Próxima cobrança.
* Data de término.
* Status.
* Meio de pagamento.
* Renovação automática.
* Origem.
* Responsável pela contratação.
* Aceite contratual.
* Versão do contrato.
* Data e IP do aceite.

### Fatura

* Assinatura.
* Período de competência.
* Vencimento.
* Valor bruto.
* Desconto.
* Crédito.
* Tributos.
* Valor líquido.
* Status.
* Documento fiscal relacionado.

### Pagamento

* Fatura.
* Provedor.
* Identificador externo.
* Método.
* Valor.
* Status.
* Data.
* Código de autorização.
* Dados seguros e mascarados.
* Chave de idempotência.

### Reembolso

* Pagamento.
* Motivo.
* Valor.
* Tipo.
* Solicitante.
* Aprovador.
* Data.
* Protocolo.
* Identificador externo.
* Status.

## Estados da assinatura

Considerar:

* Pendente.
* Em teste.
* Ativa.
* Pagamento pendente.
* Em tolerância.
* Suspensa.
* Cancelamento agendado.
* Cancelada.
* Encerrada.
* Reembolsada.
* Em disputa.

Definir formalmente quais transições são permitidas.

## Contratação

Fluxo:

1. Seleção do plano.
2. Seleção da periodicidade.
3. Exibição do valor normal.
4. Exibição do desconto.
5. Exibição do valor final.
6. Exibição da renovação automática.
7. Aceite dos termos.
8. Seleção do pagamento.
9. Criação da assinatura pendente.
10. Criação da fatura.
11. Processamento.
12. Confirmação.
13. Ativação.
14. Emissão da nota fiscal.
15. Envio do comprovante.

## Direito de arrependimento

Implementar:

* Data e hora da contratação.
* Prazo calculado pelo sistema.
* Botão acessível para solicitação.
* Confirmação clara.
* Protocolo.
* Registro do motivo como opcional.
* Cálculo do valor.
* Cancelamento da renovação.
* Solicitação de reembolso.
* Acompanhamento do status.
* Comunicação ao cliente.
* Histórico imutável.

Não exigir justificativa como condição para exercer o direito previsto em lei.

A regra final deve ser revisada juridicamente conforme:

* Perfil do contratante.
* Relação de consumo.
* Tipo de serviço.
* Momento de início da prestação.
* Contrato aplicável.
* Legislação vigente.

## Cancelamento normal

Permitir:

* Cancelamento imediato.
* Cancelamento ao final do ciclo.
* Registro do responsável.
* Motivo.
* Retenção de dados conforme contrato e legislação.
* Exportação dos dados.
* Aviso sobre perda de acesso.
* Reativação até o encerramento.
* Cancelamento de cobranças futuras.
* Tratamento de valores pendentes.

## Upgrade

Definir:

* Aplicação imediata ou no próximo ciclo.
* Liberação imediata de recursos.
* Cobrança proporcional.
* Crédito do plano anterior.
* Nova fatura.
* Histórico de alteração.

## Downgrade

Definir:

* Aplicação imediata ou no próximo ciclo.
* Verificação de limites.
* Usuários excedentes.
* Lojas excedentes.
* Armazenamento excedente.
* Recursos incompatíveis.
* Avisos.
* Prazo para adequação.

## Falha de cobrança

Criar régua:

* Primeira tentativa.
* Nova tentativa.
* Aviso por e-mail.
* Aviso no sistema.
* Período de tolerância.
* Limitação progressiva.
* Suspensão.
* Cancelamento.
* Reativação após pagamento.

Não excluir dados automaticamente por falha de cobrança.

## Roadmap sugerido para assinaturas

### Fase 1 — Fundação

* Modelagem.
* Planos.
* Preços.
* Ciclos.
* Assinaturas.
* Faturas.
* Eventos.
* Auditoria.
* Termos e aceites.

### Fase 2 — Cobrança Pix

* Criação de cobrança.
* QR Code.
* Código copia e cola.
* Expiração.
* Webhook.
* Consulta de confirmação.
* Conciliação.
* Devolução.
* Idempotência.

### Fase 3 — Cartão

* Checkout tokenizado.
* Cartão salvo por token.
* Cobrança recorrente.
* Atualização de cartão.
* Falhas.
* Retentativas.
* Estornos.
* Chargebacks.
* Antifraude do provedor.

### Fase 4 — Ciclo completo

* Upgrade.
* Downgrade.
* Cancelamento.
* Reativação.
* Direito de arrependimento.
* Reembolso.
* Créditos.
* Cupons.
* Descontos.

### Fase 5 — Fiscal e contábil

* Emissão de NFS-e da assinatura.
* Conciliação.
* Exportação contábil.
* Relatórios.
* Fechamento mensal.

---

# Arquitetura de pagamentos com baixo orçamento

## Princípio

Construir internamente:

* Orquestração.
* Regras de negócio.
* Cadastro de meios de pagamento tokenizados.
* Faturas.
* Cobranças.
* Webhooks.
* Conciliação.
* Retentativas.
* Reembolsos.
* Auditoria.
* Relatórios.
* Rateio lógico.
* Fluxos de aprovação.
* Notificações.

Não tentar operar internamente como banco, adquirente, bandeira ou instituição de pagamento sem estrutura jurídica, financeira, técnica e autorização aplicável.

## Estratégia recomendada

Criar uma camada abstrata de pagamentos:

```text
PaymentProvider
├── createCharge
├── capturePayment
├── cancelCharge
├── refundPayment
├── getPayment
├── createPixCharge
├── expirePixCharge
├── tokenizeCard
├── createSubscriptionCharge
└── validateWebhook
```

O domínio não deve depender diretamente de um único provedor.

Criar adaptadores intercambiáveis.

## Dados de cartão

Nunca registrar:

* CVV.
* Senha.
* Trilha magnética.
* Dados completos de autenticação.
* Número completo do cartão em logs.
* Número completo em banco sem necessidade comprovada e conformidade.

Preferir:

* Token do provedor.
* Bandeira.
* Quatro últimos dígitos.
* Mês e ano de validade quando permitido.
* Nome mascarado.
* Identificador do método externo.

## Webhooks

Implementar:

* Validação de assinatura.
* Chave secreta.
* Idempotência.
* Registro do payload seguro.
* Fila.
* Retentativa.
* Dead-letter queue.
* Ordem de eventos.
* Prevenção contra replay.
* Atualização transacional.
* Reconciliação posterior.

Nunca confiar apenas no redirecionamento do navegador para confirmar um pagamento.

## Conciliação

Executar processo periódico que compare:

* Cobranças internas.
* Pagamentos confirmados pelo provedor.
* Extrato.
* Valores.
* Taxas.
* Parcelas.
* Reembolsos.
* Estornos.
* Chargebacks.
* Divergências.

---

# Pagamento dos pedidos diretamente às empresas

## Objetivo

Permitir que o cliente final pague o pedido da empresa usuária por Pix ou cartão.

## Decisão arquitetural obrigatória

Antes da implementação, definir:

### Modelo A — Pagamento direto

O cliente paga diretamente na conta ou credenciamento da empresa.

Vantagens:

* Menor responsabilidade financeira para a plataforma.
* Menor risco regulatório.
* Empresa recebe diretamente.
* Plataforma não mantém saldo de terceiros.

Desvantagens:

* Cada empresa precisa configurar sua conta.
* Integrações variam.
* Conciliação pode ser mais complexa.

### Modelo B — Marketplace ou subcontas

A plataforma recebe ou intermedeia e o provedor realiza divisão e repasse.

Vantagens:

* Experiência padronizada.
* Split.
* Comissões automáticas.
* Gestão centralizada.

Desvantagens:

* Maior dependência do provedor.
* Mais custos.
* Mais obrigações.
* KYC.
* Risco de chargeback.
* Regras de repasse.
* Possíveis implicações regulatórias.

### Diretriz inicial

Para orçamento baixo, priorizar pagamento direto ou solução de subcontas oferecida por instituição autorizada.

Não manter carteira, saldo interno sacável ou recursos de clientes sem análise jurídica e regulatória especializada.

## Fluxo Pix do pedido

1. Pedido criado.
2. Valor final bloqueado.
3. Cobrança Pix criada.
4. QR Code exibido.
5. Cobrança com vencimento.
6. Cliente realiza pagamento.
7. Instituição confirma.
8. Webhook recebido.
9. Assinatura validada.
10. Evento processado com idempotência.
11. Pedido marcado como pago.
12. Financeiro baixado.
13. Estoque ou produção liberada.
14. Comprovante disponibilizado.
15. Documento fiscal emitido conforme regra.

## Fluxo cartão

1. Pedido confirmado.
2. Dados enviados diretamente ao ambiente seguro do provedor.
3. Token criado.
4. Autorização solicitada.
5. Tratamento de autenticação adicional.
6. Pagamento autorizado.
7. Captura imediata ou posterior.
8. Confirmação por webhook.
9. Pedido atualizado.
10. Conciliação.

## Estados do pagamento do pedido

* Criado.
* Aguardando.
* Em processamento.
* Autorizado.
* Capturado.
* Confirmado.
* Negado.
* Expirado.
* Cancelado.
* Parcialmente reembolsado.
* Reembolsado.
* Em disputa.
* Chargeback.

## Casos especiais

* Pagamento duplicado.
* Pix após expiração.
* Pix com valor diferente.
* Cartão negado.
* Webhook atrasado.
* Webhook duplicado.
* Pedido cancelado após pagamento.
* Pagamento aprovado após cancelamento.
* Reembolso parcial.
* Reembolso total.
* Pagamento dividido.
* Troca da forma de pagamento.
* Venda parcelada.
* Taxa assumida pela empresa.
* Taxa repassada quando legalmente admitida.
* Chargeback.
* Fraude.
* Contestação.

---

# Documentos fiscais

## Objetivo

Planejar uma camada fiscal capaz de atender diferentes documentos, estados, municípios, regimes tributários e atualizações legais.

A implementação fiscal deve ser modular e versionada.

## Tipos de documentos

Avaliar a necessidade de:

* NF-e.
* NFC-e.
* NFS-e.
* Nota de devolução.
* Nota de entrada.
* Nota complementar.
* Nota de ajuste.
* Carta de Correção Eletrônica.
* Inutilização.
* Cancelamento.
* Manifestação.
* MDF-e.
* CT-e, quando aplicável.
* SAT, MFE ou outros modelos regionais enquanto aplicáveis.
* Documentos e eventos relacionados à CBS, IBS e Imposto Seletivo.

## NF-e

Utilizada principalmente para operações com mercadorias que exigem o modelo correspondente.

Planejar:

* Credenciamento.
* Certificado digital.
* CSC quando aplicável apenas ao documento correspondente.
* Série.
* Numeração.
* Ambiente de homologação.
* Ambiente de produção.
* Assinatura XML.
* Validação por XSD.
* Autorização.
* Protocolo.
* DANFE.
* Armazenamento do XML.
* Distribuição.
* Cancelamento.
* Carta de correção.
* Inutilização.
* Contingência.
* Consulta de status.
* Consulta de cadastro.
* Eventos.
* Rejeições.

## NFC-e

Planejar:

* Venda a consumidor final.
* CSC.
* QR Code.
* DANFE NFC-e.
* Impressão.
* Envio digital.
* Consumidor identificado ou não.
* Pagamento.
* Troco.
* Contingência.
* Cancelamento.
* Regras específicas da unidade federativa.

Não tratar “cupom fiscal” como um único padrão nacional sem verificar a legislação da unidade federativa.

## NFS-e

Necessária para cobrança dos planos SaaS e outros serviços.

Considerar:

* Padrão nacional.
* Padrões municipais.
* Município do prestador.
* Município da incidência.
* Código de serviço.
* CNAE.
* Item da lista de serviços.
* ISS.
* Retenções.
* Regime tributário.
* Simples Nacional.
* Exigibilidade.
* Tomador.
* Intermediário.
* Cancelamento.
* Substituição.
* RPS.
* Lote.
* Consulta.
* PDF.
* XML.
* Webservice ou API disponível.

## Arquitetura fiscal

Criar abstrações:

```text
FiscalDocumentService
├── issue
├── cancel
├── correct
├── invalidateNumber
├── consult
├── downloadXml
├── generatePdf
└── processEvent
```

Adaptadores:

```text
FiscalProvider
├── SefazNFeAdapter
├── SefazNFCeAdapter
├── NationalNFSeAdapter
├── MunicipalNFSeAdapter
└── TemporaryThirdPartyAdapter
```

## Motor tributário

Criar cadastro e regras para:

* Regime tributário.
* CRT.
* CFOP.
* NCM.
* CEST.
* CST.
* CSOSN.
* Origem.
* ICMS.
* ICMS-ST.
* FCP.
* IPI.
* PIS.
* Cofins.
* ISS.
* Retenções.
* Benefícios fiscais.
* Diferimento.
* Desoneração.
* Monofásico.
* Substituição tributária.
* CBS.
* IBS.
* Imposto Seletivo.
* Regras por produto.
* Regras por empresa.
* Regras por estabelecimento.
* Regras por origem e destino.
* Regras por tipo de operação.
* Vigência.

Não fixar regras tributárias diretamente no código sempre que puderem ser parametrizadas e versionadas.

Toda parametrização fiscal deve ser aprovada pela empresa e por sua contabilidade.

## Dados necessários da empresa

* CNPJ.
* Inscrição estadual.
* Inscrição municipal.
* CNAEs.
* Regime tributário.
* CRT.
* Endereço.
* Município.
* Código IBGE.
* Certificado.
* Séries.
* Ambientes.
* CSC.
* Responsável fiscal.
* Configurações por unidade.

## Dados necessários dos produtos

* NCM.
* CEST.
* Origem.
* Unidade comercial.
* Unidade tributável.
* GTIN.
* Peso.
* Tributação padrão.
* Benefícios.
* Tipo de item.
* Serviço ou mercadoria.

## Dados necessários do cliente

* CPF ou CNPJ.
* Inscrição estadual.
* Indicador da inscrição.
* Nome.
* Endereço.
* Município.
* UF.
* País.
* E-mail.
* Consumidor final.
* Contribuinte.

## Estados do documento fiscal

* Pendente.
* Validando.
* Assinando.
* Enviando.
* Em processamento.
* Autorizado.
* Rejeitado.
* Denegado, quando aplicável.
* Cancelamento pendente.
* Cancelado.
* Contingência.
* Erro técnico.

## Tratamento de rejeições

Criar catálogo contendo:

* Código.
* Mensagem.
* Documento.
* Estado.
* Campo relacionado.
* Explicação amigável.
* Possível solução.
* Necessidade de ação da empresa.
* Possibilidade de correção automática.

## Contingência

Planejar:

* Indisponibilidade da Sefaz.
* Indisponibilidade municipal.
* Fila de emissão.
* Retentativas.
* Modo de contingência permitido.
* Numeração.
* Reconciliação posterior.
* Bloqueio de duplicidade.
* Alertas.
* Operação offline apenas quando legal e tecnicamente permitida.

## Armazenamento fiscal

Guardar:

* XML enviado.
* XML autorizado.
* Protocolo.
* Eventos.
* PDF.
* Número.
* Série.
* Chave.
* Hash.
* Ambiente.
* Data.
* Versão do layout.
* Versão das regras usadas.

Utilizar armazenamento imutável ou com forte controle de alteração.

## Roadmap fiscal

### Fase 1 — Cadastro fiscal

* Empresa.
* Unidade.
* Regime.
* Produtos.
* Clientes.
* Operações.
* Regras.
* Certificados.

### Fase 2 — Motor fiscal básico

* Determinação de operação.
* Cálculo.
* Validação.
* Memória de cálculo.
* Logs.

### Fase 3 — NF-e

* Homologação.
* Emissão.
* XML.
* DANFE.
* Cancelamento.
* Carta de correção.
* Inutilização.
* Contingência.

### Fase 4 — NFC-e

* CSC.
* QR Code.
* Impressão.
* Consumidor.
* Pagamentos.
* Contingência.

### Fase 5 — NFS-e

* Assinaturas do SaaS.
* Padrão nacional.
* Adaptadores municipais.
* Cancelamento e substituição.

### Fase 6 — Obrigações e contabilidade

* Exportações.
* Livros.
* Apuração assistida.
* SPED.
* Integrações do módulo contábil.

---

# Módulo da contabilidade

## Visão

Criar um portal independente dentro da plataforma destinado a:

* Contadores.
* Escritórios de contabilidade.
* Auxiliares contábeis.
* Analistas fiscais.
* Analistas de departamento pessoal.
* Responsáveis tributários.
* Auditores autorizados.

O contador não deve utilizar o mesmo painel operacional da empresa.

O portal deve possuir:

* Interface própria.
* Permissões próprias.
* Termos próprios.
* Auditoria reforçada.
* Acesso a múltiplas empresas autorizadas.
* Troca segura entre empresas.
* Visão consolidada do escritório.
* Pendências por cliente.
* Calendário de obrigações.

## Solicitação de acesso

Fluxo:

1. Escritório cria conta.
2. Informa CNPJ e responsáveis.
3. Configura usuários.
4. Localiza ou convida a empresa.
5. Solicita acesso.
6. Escolhe escopos necessários.
7. Informa finalidade.
8. Empresa recebe a solicitação.
9. Responsável aprova, limita ou recusa.
10. Sistema registra consentimento ou autorização contratual.
11. Acesso recebe validade.
12. Empresa pode revogar.
13. Toda consulta e exportação é auditada.

## Escopos de acesso

Permitir autorização granular:

* Cadastro empresarial.
* Cadastros fiscais.
* Produtos.
* Clientes.
* Fornecedores.
* Compras.
* Vendas.
* Estoque.
* Financeiro.
* Contas bancárias.
* Conciliação.
* Documentos fiscais.
* Folha, futuramente.
* Relatórios.
* Exportações.
* Obrigações.
* Certificados.
* Procurações.
* Somente leitura.
* Escrita limitada.
* Aprovação.
* Transmissão.

## Painel do escritório

Exibir:

* Empresas atendidas.
* Solicitações pendentes.
* Acessos próximos do vencimento.
* Certificados próximos do vencimento.
* Documentos fiscais pendentes.
* Notas rejeitadas.
* Movimentos sem classificação.
* Contas sem conciliação.
* Fechamentos pendentes.
* Obrigações por vencer.
* Inconsistências.
* Mensagens das empresas.
* Pendências prioritárias.

## Cadastro contábil da empresa

Manter:

* Regime tributário.
* Enquadramentos.
* CNAEs.
* Inscrições.
* Estabelecimentos.
* Plano de contas.
* Centros de custo.
* Naturezas financeiras.
* Histórico padrão.
* Regras de contabilização.
* Contas bancárias.
* Sócios.
* Responsáveis.
* Certificados.
* Procurações.
* Obrigações aplicáveis.

## Plano de contas

Permitir:

* Plano padrão.
* Plano específico por empresa.
* Hierarquia.
* Contas sintéticas.
* Contas analíticas.
* Vigência.
* Bloqueio.
* Mapeamento de produtos.
* Mapeamento de categorias.
* Mapeamento de operações.
* Mapeamento financeiro.
* Mapeamento tributário.

## Escrituração contábil assistida

Gerar lançamentos a partir de:

* Vendas.
* Compras.
* Recebimentos.
* Pagamentos.
* Tarifas.
* Folha.
* Impostos.
* Estoque.
* Perdas.
* Devoluções.
* Ativo imobilizado.
* Depreciação.
* Transferências.
* Ajustes.

Cada lançamento deve possuir:

* Origem.
* Documento.
* Data.
* Competência.
* Débito.
* Crédito.
* Valor.
* Histórico.
* Centro de custo.
* Empresa.
* Estabelecimento.
* Responsável.
* Status.
* Regra utilizada.

## Regras automáticas

Exemplos:

* Venda paga via Pix.
* Venda a prazo.
* Compra à vista.
* Compra a prazo.
* Taxa de cartão.
* Comissão.
* Frete.
* Devolução.
* Cancelamento.
* Perda de estoque.
* Desconto.
* Juros.
* Multa.
* Reembolso.
* Assinatura do SaaS.

O contador deve poder revisar e aprovar regras.

## Gestão fiscal

Disponibilizar:

* Documentos emitidos.
* Documentos recebidos.
* XML.
* Eventos.
* Rejeições.
* Cancelamentos.
* Devoluções.
* Apurações.
* Créditos.
* Débitos.
* Resumos tributários.
* Inconsistências.
* Produtos sem classificação.
* Operações sem CFOP.
* Notas sem vínculo financeiro.
* Compras sem entrada de estoque.
* Vendas sem documento fiscal.

## Captura de documentos

Prioridades:

1. Documentos gerados pelo próprio sistema.
2. Importação de XML.
3. Upload manual.
4. Consulta ou distribuição oficial quando permitida.
5. Caixa de entrada fiscal.
6. Integrações autorizadas.

Evitar depender inicialmente de leitura por OCR quando o XML estiver disponível.

## Conciliação

Permitir:

* Importação OFX.
* Importação CSV.
* Conciliação manual.
* Regras automáticas.
* Integração bancária autorizada.
* Open Finance quando viável.
* Identificação de tarifas.
* Transferências.
* Pagamentos.
* Recebimentos.
* Divergências.
* Duplicidades.

Para baixo orçamento, implementar primeiro OFX e CSV antes de integrações bancárias individuais.

## Fechamento mensal

Checklist configurável:

* Documentos emitidos.
* Documentos recebidos.
* Cancelamentos.
* Estoque.
* Inventário.
* Contas a receber.
* Contas a pagar.
* Bancos.
* Caixa.
* Folha.
* Pró-labore.
* Impostos.
* Imobilizado.
* Depreciação.
* Empréstimos.
* Parcelamentos.
* Conciliação.
* Pendências.
* Aprovação.

## Obrigações

O módulo pode preparar, validar, organizar ou gerar dados para:

* ECD.
* ECF.
* EFD ICMS/IPI.
* EFD Contribuições.
* EFD-Reinf.
* eSocial.
* DCTFWeb.
* DeRE.
* Obrigações estaduais.
* Obrigações municipais.
* Obrigações do Simples Nacional.
* Outras obrigações aplicáveis.

Não declarar que uma obrigação está pronta apenas porque um arquivo foi gerado.

Validar:

* Leiaute.
* Versão.
* Regras.
* Assinatura.
* Certificado.
* Validador oficial.
* Recibo.
* Protocolo.
* Retificação.
* Histórico.

## Comunicação empresa-contabilidade

Criar:

* Central de pendências.
* Solicitação de informação.
* Comentários.
* Anexos.
* Responsáveis.
* Prazo.
* Prioridade.
* Status.
* Histórico.
* Notificações.
* Aprovação.

Exemplos:

* Informar origem de movimentação.
* Classificar despesa.
* Enviar contrato.
* Confirmar inventário.
* Corrigir produto sem NCM.
* Validar lançamento.
* Renovar certificado.

## Documentos

Organizar:

* Contratos.
* Certidões.
* Guias.
* Comprovantes.
* Procurações.
* Certificados.
* Relatórios.
* Recibos.
* Declarações.
* Balancetes.
* Balanços.
* DRE.
* Livros.
* Documentos societários.

Aplicar:

* Categoria.
* Competência.
* Empresa.
* Estabelecimento.
* Permissão.
* Versão.
* Validade.
* Assinatura.
* Auditoria.

## Relatórios contábeis

* Balancete.
* Balanço patrimonial.
* DRE.
* DFC.
* Razão.
* Diário.
* Livro caixa.
* Contas por centro de custo.
* Resultado por unidade.
* Resultado por segmento.
* Resultado por canal.
* Resultado por produto.
* Comparativo entre períodos.
* Orçado versus realizado.
* Indicadores financeiros.
* Capital de giro.
* Ponto de equilíbrio.

## Responsabilidade profissional

O sistema pode automatizar, organizar, validar e transmitir processos quando permitido.

Não deve:

* Fazer-se passar por contador.
* Assinar atos privativos sem profissional habilitado.
* Transmitir obrigação sem autorização.
* Utilizar certificado sem controle.
* Alterar classificação sem histórico.
* Ocultar responsabilidade do usuário que aprovou.

Toda ação profissional relevante deve registrar:

* Contador responsável.
* Registro profissional quando necessário.
* Empresa.
* Usuário executor.
* Data.
* Certificado.
* Procuração.
* Versão.
* Protocolo.

---

# Segurança do módulo contábil

Aplicar:

* MFA obrigatório.
* Sessões separadas.
* Menor privilégio.
* Acesso temporário.
* Aprovação por escopo.
* Revogação imediata.
* Auditoria de consultas.
* Auditoria de exportações.
* Criptografia.
* Mascaramento.
* Alertas de acesso.
* Restrição por IP opcional.
* Restrição por horário opcional.
* Revisão periódica de acessos.
* Bloqueio após inatividade.
* Confirmação reforçada para ações críticas.

---

# LGPD e governança

## Papéis

Mapear por processo:

* Titular.
* Controlador.
* Operador.
* Suboperador.
* Encarregado.
* Terceiro autorizado.

Não utilizar uma classificação única para todos os fluxos.

## Inventário de dados

Registrar:

* Dado coletado.
* Finalidade.
* Base legal.
* Origem.
* Compartilhamento.
* Retenção.
* Local de armazenamento.
* Proteção.
* Responsável.
* Processo.
* Sistema.
* País.
* Risco.

## Direitos do titular

Criar processos para:

* Confirmação de tratamento.
* Acesso.
* Correção.
* Anonimização.
* Bloqueio.
* Eliminação quando aplicável.
* Portabilidade.
* Informação sobre compartilhamento.
* Revogação de consentimento.
* Oposição.
* Revisão de decisões automatizadas quando aplicável.

## Retenção

Criar política por categoria:

* Usuários.
* Clientes.
* Pedidos.
* Pagamentos.
* Documentos fiscais.
* Registros contábeis.
* Logs.
* Contratos.
* Suporte.
* Marketing.
* Arquivos.

Não eliminar documentos sujeitos a retenção legal apenas por solicitação genérica.

## Incidentes

Criar plano:

1. Detecção.
2. Classificação.
3. Contenção.
4. Preservação de evidências.
5. Avaliação de impacto.
6. Comunicação interna.
7. Comunicação aos clientes.
8. Comunicação às autoridades quando aplicável.
9. Correção.
10. Relatório final.
11. Prevenção de recorrência.

---

# Segurança financeira e fiscal

## Segredos

Armazenar com cofre de segredos ou mecanismo equivalente:

* Chaves de API.
* Certificados.
* Senhas de certificado.
* Tokens.
* Credenciais bancárias.
* Segredos de webhook.
* Chaves criptográficas.

Nunca:

* Colocar em repositório.
* Exibir em logs.
* Enviar ao frontend.
* Armazenar sem criptografia.
* Compartilhar entre empresas.

## Certificados digitais

Implementar:

* Upload seguro.
* Criptografia.
* Senha protegida.
* Validade.
* Alertas.
* Escopo por empresa.
* Rotação.
* Registro de uso.
* Controle de acesso.
* Revogação.
* Exclusão segura quando permitida.

## Auditoria

Registrar eventos críticos:

* Login.
* Falha de login.
* Alteração de permissão.
* Aprovação contábil.
* Emissão fiscal.
* Cancelamento fiscal.
* Alteração tributária.
* Cobrança.
* Reembolso.
* Estorno.
* Alteração bancária.
* Exportação.
* Uso de certificado.
* Solicitação de acesso.
* Revogação.

O log deve ser resistente a alteração.

---

# Estratégia de baixo custo

## Construir internamente

Priorizar desenvolvimento interno de:

* Cadastro.
* Pedidos.
* Estoque.
* Financeiro.
* Faturas.
* Assinaturas.
* Regras de descontos.
* Auditoria.
* Conciliação.
* Importação OFX e CSV.
* Motor de permissões.
* Portal contábil.
* BI.
* Relatórios.
* Armazenamento de XML.
* Filas.
* Webhooks.
* Regras fiscais parametrizadas.
* Adaptadores.
* Integrações com serviços públicos que possuam documentação e acesso permitido.

## Utilizar terceiros quando necessário

Utilizar prestador especializado quando reduzir significativamente:

* Risco regulatório.
* Escopo PCI.
* Complexidade fiscal municipal.
* Antifraude.
* Chargeback.
* KYC.
* Disponibilidade bancária.
* Manutenção de centenas de padrões.

## Critério de decisão

Para cada integração, apresentar:

* Possibilidade de implementação interna.
* Restrições legais.
* Restrições técnicas.
* Custo de construção.
* Custo de manutenção.
* Risco.
* Dependência.
* SLA.
* Alternativas.
* Recomendação para MVP.
* Recomendação para escala.

## Ordem econômica recomendada

1. Processos internos e importação de arquivos.
2. Integração oficial gratuita.
3. Integração bancária contratada pela própria empresa.
4. Provedor com cobrança por uso.
5. Provedor fiscal temporário.
6. Desenvolvimento de integração própria quando o volume justificar.

Não construir uma infraestrutura complexa apenas para evitar uma pequena tarifa se o risco e a manutenção forem maiores.

---

# Business Intelligence estratégico

## Visão executiva

Exibir:

* Receita.
* Receita líquida.
* Margem.
* Resultado.
* Pedidos.
* Ticket.
* Clientes.
* Estoque.
* Inadimplência.
* Caixa.
* Metas.
* Riscos.
* Previsão.

## Visão SaaS

* MRR.
* ARR.
* Receita por plano.
* Assinaturas ativas.
* Novas assinaturas.
* Cancelamentos.
* Churn.
* Expansão.
* Contração.
* Receita perdida.
* Inadimplência.
* Taxa de recuperação.
* Lifetime Value.
* Custo de aquisição.
* Payback.
* Uso por plano.
* Clientes próximos do limite.

## Visão de pagamentos

* Valor processado.
* Taxa de aprovação.
* Pix confirmado.
* Cartões negados.
* Tempo de confirmação.
* Taxas.
* Chargebacks.
* Reembolsos.
* Divergências.
* Conciliações pendentes.

## Visão fiscal

* Notas emitidas.
* Autorizadas.
* Rejeitadas.
* Canceladas.
* Em contingência.
* Tempo médio de autorização.
* Principais rejeições.
* Empresas com configuração incompleta.
* Certificados a vencer.
* Produtos sem tributação.

## Visão contábil

* Empresas com fechamento pendente.
* Movimentos sem classificação.
* Conciliações pendentes.
* Obrigações próximas.
* Documentos faltantes.
* Divergências.
* Fechamentos concluídos.
* Tempo médio de fechamento.
* Pendências por empresa.

---

# Roadmap geral de implementação

## Fase 0 — Diagnóstico

Entregáveis:

* Arquitetura atual.
* Mapa de módulos.
* Mapa de banco.
* Mapa de fluxos.
* Matriz de permissões.
* Auditoria de segurança.
* Auditoria multiempresa.
* Auditoria de produção.
* Backlog P0, P1, P2 e P3.

## Fase 1 — Produção segura

* Correção dos P0.
* Backups.
* Monitoramento.
* Logs.
* Testes críticos.
* Isolamento multiempresa.
* Termos.
* Privacidade.
* Suporte.
* Deploy.
* Rollback.

## Fase 2 — Assinaturas via Pix

* Planos.
* Ciclos.
* Descontos.
* Assinaturas.
* Faturas.
* Pix.
* Confirmação.
* Cancelamento.
* Direito de arrependimento.
* Reembolso.
* NFS-e.

## Fase 3 — Cartão recorrente

* Tokenização.
* Recorrência.
* Retentativas.
* Atualização de cartão.
* Estorno.
* Chargeback.
* Conciliação.

## Fase 4 — Pagamento de pedidos

* Conta de recebimento por empresa.
* Pix do pedido.
* Cartão.
* Confirmação.
* Baixa financeira.
* Reembolso.
* Conciliação.

## Fase 5 — Fiscal de mercadorias

* Cadastro fiscal.
* Motor tributário.
* NF-e.
* NFC-e.
* Contingência.
* Eventos.
* Atualizações da Reforma Tributária.

## Fase 6 — Portal contábil inicial

* Cadastro do escritório.
* Solicitação de acesso.
* Permissões.
* Empresas.
* XML.
* Financeiro.
* OFX.
* CSV.
* Pendências.
* Relatórios.

## Fase 7 — Contabilidade integrada

* Plano de contas.
* Regras.
* Lançamentos.
* Conciliação.
* Fechamento.
* Demonstrativos.
* Obrigações.

## Fase 8 — Segmentos avançados

* Bares.
* Mesas.
* Comandas.
* Fichas técnicas.
* Casas noturnas.
* Ingressos.
* Camarotes.
* Promoters.
* Eventos.
* Bilheteria.
* Controle de acesso.

---

# Formato obrigatório das entregas

Ao finalizar a análise, criar os documentos:

```text
docs/
├── auditoria/
│   ├── 01-resumo-executivo.md
│   ├── 02-arquitetura-atual.md
│   ├── 03-mapa-de-modulos.md
│   ├── 04-mapa-do-banco.md
│   ├── 05-fluxos-criticos.md
│   ├── 06-seguranca.md
│   ├── 07-multiempresa.md
│   ├── 08-producao-checklist.md
│   └── 09-backlog-priorizado.md
├── assinaturas/
│   ├── requisitos.md
│   ├── regras-de-negocio.md
│   ├── modelo-de-dados.md
│   ├── estados.md
│   ├── fluxos.md
│   └── roadmap.md
├── pagamentos/
│   ├── arquitetura.md
│   ├── pix.md
│   ├── cartao.md
│   ├── webhooks.md
│   ├── conciliacao.md
│   └── seguranca.md
├── fiscal/
│   ├── levantamento.md
│   ├── motor-tributario.md
│   ├── nfe.md
│   ├── nfce.md
│   ├── nfse.md
│   ├── contingencia.md
│   └── roadmap.md
├── contabilidade/
│   ├── visao-geral.md
│   ├── acessos.md
│   ├── funcionalidades.md
│   ├── plano-de-contas.md
│   ├── conciliacao.md
│   ├── fechamento.md
│   ├── obrigacoes.md
│   └── roadmap.md
└── conformidade/
    ├── lgpd.md
    ├── seguranca.md
    ├── retencao.md
    ├── incidentes.md
    └── auditoria.md
```

---

# Formato do backlog

Para cada item, informar:

* Código.
* Épico.
* Funcionalidade.
* Problema.
* Objetivo.
* Prioridade.
* Dependências.
* Risco.
* Complexidade.
* Estimativa relativa.
* Critérios de aceite.
* Testes.
* Impacto no banco.
* Impacto nas APIs.
* Impacto no frontend.
* Impacto em segurança.
* Impacto fiscal.
* Impacto legal.
* Estratégia de rollback.

---

# Critérios de aceite

Todo requisito deve possuir critérios verificáveis.

Exemplo:

```text
Dado que uma empresa possui uma fatura pendente,
quando o pagamento Pix for confirmado pela instituição,
então o sistema deve:

1. validar a autenticidade do webhook;
2. verificar a idempotência;
3. localizar a cobrança correta;
4. confirmar o valor;
5. registrar a transação;
6. baixar a fatura;
7. ativar ou manter a assinatura;
8. criar evento de auditoria;
9. enviar confirmação;
10. impedir processamento duplicado.
```

---

# Regras de implementação

Antes de programar:

1. Localizar implementações relacionadas.
2. Mapear impacto.
3. Definir regra.
4. Definir estados.
5. Definir transações.
6. Definir idempotência.
7. Definir permissões.
8. Definir logs.
9. Definir testes.
10. Definir rollback.

Durante a implementação:

* Fazer alterações pequenas.
* Não misturar refatoração ampla com funcionalidade crítica.
* Criar migrations reversíveis quando possível.
* Proteger compatibilidade.
* Utilizar feature flags em funcionalidades críticas.
* Adicionar testes.
* Atualizar documentação.
* Registrar decisões arquiteturais.

Depois da implementação:

* Executar testes.
* Revisar segurança.
* Revisar multiempresa.
* Testar concorrência.
* Testar duplicidade.
* Testar falhas externas.
* Testar rollback.
* Testar auditoria.
* Validar critérios de aceite.

---

# Comportamento obrigatório da skill

Esta skill deve:

* Examinar antes de concluir.
* Questionar requisitos incompletos.
* Fazer premissas visíveis.
* Priorizar baixo custo.
* Não comprometer segurança para economizar.
* Não sugerir operações ilegais.
* Não simular autorização governamental.
* Não prometer integração pública inexistente.
* Não tratar acesso ao Open Finance como livre.
* Não armazenar dados sensíveis sem necessidade.
* Não criar banco ou carteira informal.
* Não transmitir obrigações sem autorização.
* Não confundir cancelamento com estorno.
* Não confundir estorno com reembolso.
* Não confundir pagamento autorizado com liquidado.
* Não confundir nota emitida com nota autorizada.
* Não confundir faturamento com lucro.
* Não confundir relatório com contabilidade concluída.
* Não considerar o sistema pronto sem evidências.

---

# Glossário financeiro obrigatório

Utilizar corretamente:

* **Cancelamento:** interrupção de uma cobrança, assinatura, pedido ou operação conforme seu estado.
* **Estorno:** reversão financeira realizada pelo meio de pagamento.
* **Reembolso:** devolução de valor ao pagador.
* **Chargeback:** contestação iniciada no ecossistema do cartão.
* **Autorização:** reserva ou aprovação inicial do cartão.
* **Captura:** confirmação da cobrança autorizada.
* **Liquidação:** efetivação financeira.
* **Conciliação:** comparação entre registros internos e externos.
* **Idempotência:** garantia de que a repetição da mesma solicitação não gere duplicidade.

---

# Resultado esperado

A execução desta skill deve produzir:

* Diagnóstico real do sistema.
* Lista completa do que falta para produção.
* Riscos classificados.
* Backlog priorizado.
* Roadmap de assinaturas.
* Roadmap de pagamentos.
* Roadmap fiscal.
* Roadmap contábil.
* Arquitetura de baixo custo.
* Matriz entre construção interna e contratação externa.
* Critérios de aceite.
* Plano de segurança.
* Plano de LGPD.
* Plano de testes.
* Plano de implantação.
* Plano de operação.
* Estimativa relativa de esforço.
* Dependências entre fases.

A solução deve ser construída sempre dentro da legislação brasileira vigente, com consultas prioritárias a fontes oficiais, validação jurídica, fiscal e contábil quando necessária e registro explícito das normas e versões utilizadas em cada decisão.