# Detalhamento de funcionalidades — Sistema PegaTicket

Levantamento completo de tudo que existe hoje no sistema, por tópico. Onde uma funcionalidade ainda não está 100% pronta pra operação real (depende de integração externa), isso está marcado explicitamente — o resto está implementado e testado.

---

## 1. Empresa (Tenant) e planos comerciais

- **Cadastro da empresa**: nome, slug (identificador único), logo (upload direto, guardado no banco — não depende de disco), status ativo/inativo, período de teste (trial).
- **Dados fiscais do emitente**: CNPJ, Inscrição Estadual, Inscrição Municipal, CNAE, regime tributário (Simples Nacional/Lucro Presumido/Lucro Real), ambiente fiscal (homologação/produção — nunca começa em produção por padrão, pra não emitir nota real por engano), código IBGE do município.
- **3 planos comerciais**, cada um libera um conjunto de funcionalidades (nunca aparece no menu o que o plano não inclui):
  - **Prata**: loja online, pedidos, clientes, catálogo de produtos, relatórios básicos, configurações, redes sociais.
  - **Ouro**: tudo do Prata + controle de estoque, PDV (frente de caixa), cashback/fidelidade, analytics avançado, montagem de rota de entrega.
  - **Diamante**: tudo do Ouro + Balcão (mesas/comandas, restaurante e bar), gestão da própria assinatura, acesso de contador externo, regras fiscais.
- **Configurações operacionais da empresa** (`tenant_settings`): forma(s) de pagamento aceitas, chave Pix de recebimento, bloquear ou não pedido sem estoque disponível, valor mínimo de pedido, tempo estimado de preparo, envio de link de rastreio por WhatsApp, taxa de serviço (percentual e se é obrigatória) — usada no Balcão.

## 2. Usuários e controle de acesso

- **Usuário (`User`)**: cadastro único de login (e-mail/senha), pode ter acesso a mais de 1 empresa ao mesmo tempo.
- **Vínculo empresa-usuário (`TenantUser`)**: liga um usuário a uma empresa através de um **perfil (Role)** daquela empresa especificamente — o mesmo usuário pode ser dono numa empresa e funcionário em outra.
- **Perfis por empresa (`TenantRole`)**: cada empresa pode criar quantos perfis quiser (ex.: "Vendedor", "Caixa", "Gerente"), além do perfil "Proprietário" (criado automaticamente, sempre com acesso total ao que o plano permite).
- **Permissão granular**: cada perfil tem, por funcionalidade (ex.: "Pedidos"), quais ações pode fazer (ver/criar/editar/excluir/aprovar/entregar/etc.) — controlado por tela dedicada, sem precisar mexer em código.
- **Convite de usuário**: é possível convidar alguém por e-mail pra entrar numa empresa com um perfil específico; a pessoa aceita o convite e já entra vinculada.
- **Troca de empresa ativa**: usuário com acesso a mais de uma empresa troca de contexto sem precisar deslogar — o token de acesso é reemitido pra empresa escolhida.
- **Recuperação de senha, confirmação de e-mail** — fluxos padrão de autoatendimento, sem precisar de suporte manual.
- **Auditoria**: toda criação/edição/exclusão relevante (empresa, usuário, permissão, pedido, produto etc.) fica registrada em log de auditoria — quem fez, quando, o que mudou.

## 3. Clientes

- **Cadastro de cliente**: nome, telefone(s), endereço, se é "cliente de confiança" (afeta liberação de pedido sem exigir pagamento antecipado), observações.
- **Categoria de cliente**: agrupamento livre (ex.: "Atacado", "Varejo", "VIP") — usado em relatórios e preço diferenciado por categoria.
- **Dia ideal / Período ideal**: preferência de dia da semana e turno pra contato/entrega — organiza a rota do vendedor.
- **Exportação de clientes**: lista filtrável, exportável em PDF.

## 4. Endereços (geografia)

- **Estado, Cidade, Bairro**: cadastro hierárquico, compartilhado entre todas as empresas do sistema (não duplica "Araraquara/SP" pra cada empresa nova).
- **Endereço completo**: rua, número, complemento, CEP, vinculado a um bairro — usado por clientes e pela própria empresa.
- **Geocodificação**: endereço pode ser localizado em mapa (latitude/longitude), usado na montagem de rota.

## 5. Produtos

- **Categoria de produto** e **Tipo de produto**: hierarquia de 2 níveis pra organizar o catálogo.
- **Produto**: nome, descrição, foto, preço, disponibilidade, SKU/código de barras, marca, unidade de medida.
- **Preço por categoria de cliente**: um mesmo produto pode ter preço diferente conforme a categoria do cliente que está comprando (atacado vs. varejo).
- **Preço de atacado**: quantidade mínima pra liberar um preço reduzido.
- **Promoção pontual**: preço promocional com data de início/fim, sem precisar mudar o preço "normal" do produto.

## 6. Estoque *(plano Ouro+)*

- **Local de estoque**: cada empresa pode ter mais de um depósito/filial; um é sempre o padrão.
- **Saldo por produto e local**: quantidade disponível, com reserva automática quando entra num pedido (evita vender o mesmo item duas vezes antes de confirmar).
- **Movimentações**: entrada (compra/reposição), saída (venda), ajuste (correção manual, com motivo), transferência entre locais, bloqueio (item indisponível temporariamente) — tudo com histórico completo, nunca some um registro.
- **Alerta de estoque mínimo**: produto pode ter um mínimo configurado pra sinalizar reposição.

## 7. Pedidos

- **Criação de pedido**: cliente, itens (produto + quantidade, preço travado no momento da venda), origem (equipe/loja online/PDV/balcão).
- **Status do pedido**: confirmado, cancelado (com motivo), e os estágios de entrega (a caminho, entregue).
- **Pagamento do pedido**: marcação de pago/parcial, valor pago, data do pagamento.
- **Cupom de desconto**: código aplicável no checkout, com regras (percentual/valor fixo/frete grátis, valor mínimo, limite de uso total e por cliente, validade).
- **Taxa de entrega**: calculada por bairro, configurada pela empresa.
- **Código sequencial de exibição**: cada pedido tem um número curto e sequencial por empresa (mais fácil de falar por telefone que o identificador interno).

## 8. Loja online (Storefront) — catálogo público sem login

- **Catálogo público**: acessível por link direto (`/loja/<empresa>`), sem exigir cadastro pra navegar.
- **Horário de funcionamento**: configurável por dia da semana, bloqueia pedido fora do horário.
- **Taxa de entrega e valor mínimo de pedido**: configurados pela empresa, aplicados automaticamente no checkout.
- **Carrinho e checkout**: fluxo completo até a confirmação do pedido.
- **Aprovação de pedido novo**: pedidos vindos da loja entram como pendentes até a equipe aprovar/recusar.
- **Acompanhamento de preparo**: tela pública (sem login) onde o cliente acompanha o status do próprio pedido pelo link recebido.
- **Verificação de idade**: bloqueio de confirmação pra produtos que exigem (bebida alcoólica, por exemplo).

## 9. Portal do cliente final

Área separada, com login próprio do cliente final (diferente do login da equipe da empresa):
- **Histórico de pedidos** feitos naquela empresa.
- **Favoritos**: produtos marcados pra recompra rápida.
- **Endereços salvos**: reutilizados em pedidos futuros.
- **Vouchers/cupons** disponíveis pra aquele cliente.
- **Extrato de cashback**: crédito ganho, resgatado, e o que ainda está pendente de liberação.
- **Perfil**: dados pessoais do cliente final.

## 10. Cashback / fidelidade *(plano Ouro+)*

- **Configuração por empresa**: percentual de crédito por compra, valor máximo de crédito por pedido, dias de carência antes do crédito ficar disponível pra uso, percentual máximo do pedido que pode ser pago com cashback acumulado, nome customizável do programa (ex.: "MaskCash").
- **Crédito automático**: gerado a partir de pedidos confirmados/pagos.
- **Resgate**: aplicado como desconto num pedido futuro, respeitando o limite percentual configurado.
- **Processamento automático**: liberação do crédito após o período de carência roda periodicamente, sem intervenção manual.

## 11. PDV — Frente de caixa *(plano Ouro+)*

- **Sessão de caixa**: abertura com valor inicial em dinheiro, movimentos de sangria/suprimento durante o dia, fechamento com conferência do valor declarado vs. o esperado pelo sistema.
- **Venda rápida**: registro de venda direto no caixa (sem passar pelo fluxo completo de pedido), com suporte a mais de uma forma de pagamento no mesmo fechamento (ex.: parte em dinheiro, parte no cartão).
- **Emissão de recibo**: layout de impressão/compartilhamento da venda.

## 12. Balcão — Mesas e comandas *(plano Diamante)*

- **Estações de preparo**: cozinha, bar, ou outras, configuráveis por empresa.
- **Roteamento por categoria de produto**: cada categoria pode ser vinculada a uma estação — o pedido do item já sai direcionado pra cozinha ou bar automaticamente.
- **Mesas**: mapa com status (livre/ocupada/reservada/fechando).
- **Comanda**: aberta por mesa (ou avulsa, tipo balcão sem mesa), recebe itens ao longo do atendimento, cada item com seu próprio status de preparo (na fila → enviado à estação → preparando → pronto → entregue na mesa), incluindo cancelamento individual de item com motivo.
- **Tela de cozinha/bar (KDS)**: painel fixo (pensado pra ficar numa tela na cozinha) com a fila de itens daquela estação, atualização automática.
- **Fechamento de comanda**: soma os itens não cancelados, aplica taxa de serviço (se configurada), aceita divisão de pagamento em mais de uma forma, libera a mesa.
- ⚠️ **Operação 100% offline** (funcionar sem internet e sincronizar depois) ainda não foi implementada — hoje o Balcão exige conexão ativa.

## 13. Assinatura — cobrança do próprio PegaTicket *(plano Diamante)*

- **Ciclo de cobrança**: mensal, trimestral (10% de desconto) ou anual (20% de desconto).
- **Teste grátis**: 14 dias antes da primeira cobrança.
- **Cancelamento**: sem multa, imediato ou no fim do ciclo vigente; direito de arrependimento de 7 dias.
- **Histórico de faturas e pagamentos** da própria assinatura.
- ⚠️ **Cobrança automática real via Pix/cartão** ainda não está conectada a nenhuma operadora de pagamento — hoje o registro de pagamento é manual/interno, pronto pra plugar uma operadora real quando decidido.

## 14. Módulo do Contador *(plano Diamante)*

- **Cadastro do escritório de contabilidade**: CNPJ, responsável, e-mail, senha própria (login separado do sistema principal).
- **Autenticação em duas etapas (TOTP)**: obrigatória pra ativar o acesso.
- **Solicitação de acesso a uma empresa**: o contador informa o CNPJ do cliente; a própria empresa precisa aprovar (tela "Acesso de contadores", do lado da empresa) antes de qualquer dado ficar visível.
- **Escopo de acesso configurável**: a empresa escolhe o que libera (ex.: só relatórios, ou relatórios + mensagens).
- **Mensagens**: canal de comunicação entre contador e empresa dentro do próprio sistema.

## 15. Fiscal *(plano Diamante)*

- **Cadastro fiscal do emitente** (CNPJ, regime tributário, ambiente).
- **Regras tributárias**: cadastro de alíquotas (ex.: ICMS, ISS) por tipo de imposto, com vigência (data início/fim).
- ⚠️ **Emissão real de nota fiscal (NF-e/NFC-e/NFS-e)** ainda não existe — depende de comunicação com a SEFAZ ou um serviço de emissão pago, ainda não contratado. O cadastro acima já deixa a base pronta pra quando isso for decidido.

## 16. Relatórios e Analytics

- **Dashboard**: métricas do dia a dia (pedidos entregues/pendentes, valor recebido) + gráfico de pedidos por mês.
- **Análises avançadas** *(Ouro+)*: indicadores mais profundos de operação.
- **Relatório de pedidos**: filtrável por período/status/cliente, exportável.
- **Base de clientes**: listagem completa, exportável.
- **Recebíveis**: o que já entrou vs. o que ainda está em aberto, por período.

## 17. Rotas de entrega *(plano Ouro+)*

- **Montagem de rota**: agrupa pedidos por região/proximidade pra organizar a saída de entrega do dia.

## 18. Redes sociais

- Cadastro dos links/handles da empresa (Instagram, Facebook, WhatsApp) usados na loja pública e materiais de divulgação.

## 19. Segurança e conformidade

- **Isolamento entre empresas**: nenhuma empresa acessa dado de outra, em nenhuma tela — validado em auditoria de segurança dedicada.
- **Limite de tentativas de login**: bloqueio temporário após tentativas malsucedidas repetidas.
- **Dados sensíveis criptografados** onde aplicável (ex.: chave Pix, segredo de autenticação em duas etapas do contador).
- **Log de auditoria**: histórico de quem alterou o quê, quando, em qualquer entidade relevante do sistema.

## 20. Administração da plataforma (equipe interna PegaTicket)

Área separada, não visível pra empresas clientes:
- Gestão de **planos comerciais** e quais funcionalidades cada um libera.
- Gestão de **empresas cadastradas** na plataforma como um todo.
- Gestão de **grupos/permissões internas** da equipe PegaTicket.
- **Auditoria global** de todas as empresas.
