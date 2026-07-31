# Catálogo Mestre de Testes do PegaTicket

Data: 2026-07-28  
Objetivo: listar os cenários obrigatórios de teste do sistema inteiro com rastreabilidade por módulo, camada e criticidade.

## Legenda

- `BE`: teste automatizado de backend
- `FE-E2E`: teste automatizado de interface
- `SMOKE`: teste rápido de release/deploy
- `MANUAL`: homologação assistida ou visual
- `P0`: bloqueia produção
- `P1`: crítico para beta controlada
- `P2`: importante, mas pode entrar em ondas seguintes

## 1. Identidade e autenticação

| ID | Cenário | Camada | Prioridade | Cobertura atual |
|---|---|---|---|---|
| AUTH-001 | login com credenciais válidas | BE + FE-E2E | P0 | coberto |
| AUTH-002 | login com credenciais inválidas | BE + FE-E2E | P0 | coberto |
| AUTH-003 | refresh de token | BE | P0 | coberto |
| AUTH-004 | logout | BE + FE-E2E | P0 | coberto |
| AUTH-005 | lockout após tentativas inválidas | BE | P0 | coberto |
| AUTH-006 | recuperação de senha não revela existência do e-mail | BE | P0 | coberto |
| AUTH-007 | reset de senha com token válido | BE | P0 | coberto |
| AUTH-008 | reset de senha com token inválido/expirado | BE | P0 | coberto |
| AUTH-009 | política de senha mínima | BE | P1 | coberto |
| AUTH-010 | aceite de convite cria vínculo correto | BE | P1 | coberto |
| AUTH-011 | confirmação de e-mail troca pendência corretamente | BE | P1 | coberto |
| AUTH-012 | acesso a rota inexistente redireciona para login/raiz adequada | FE-E2E + SMOKE | P1 | coberto |

## 2. Multiempresa e autorização

| ID | Cenário | Camada | Prioridade | Cobertura atual |
|---|---|---|---|---|
| ACCESS-001 | seleção obrigatória da empresa ativa na primeira entrada | FE-E2E | P0 | coberto |
| ACCESS-002 | troca de empresa atualiza contexto | BE + FE-E2E | P0 | coberto |
| ACCESS-003 | menu oculta links sem permissão | FE-E2E | P0 | coberto |
| ACCESS-004 | rota protegida bloqueia acesso sem permissão | BE + FE-E2E | P0 | coberto |
| ACCESS-005 | owner vê assinatura mesmo sem permissão de grupo | BE + FE-E2E | P0 | coberto |
| ACCESS-006 | não owner não acessa assinatura | BE + FE-E2E | P0 | coberto |
| ACCESS-007 | plano bloqueia funcionalidade fora do pacote | BE | P0 | coberto |
| ACCESS-008 | override libera funcionalidade fora do plano | BE | P1 | coberto |
| ACCESS-009 | override bloqueia funcionalidade dentro do plano | BE | P1 | coberto |
| ACCESS-010 | falha transitória de access-profile não apaga sessão | FE-E2E | P1 | coberto |

## 3. Cadastro self-service e legal

| ID | Cenário | Camada | Prioridade | Cobertura atual |
|---|---|---|---|---|
| SIGNUP-001 | cadastro self-service cria empresa e proprietário | BE | P0 | coberto |
| SIGNUP-002 | cadastro exige aceite de termos e privacidade | BE | P0 | coberto |
| SIGNUP-003 | documentos legais públicos retornam versão ativa | BE | P1 | coberto |
| SIGNUP-004 | signup frontend completo até criação da sessão | FE-E2E | P1 | coberto |
| LGPD-001 | exportação de dados da empresa funciona | BE | P1 | coberto |
| LGPD-002 | criação de solicitação de privacidade | BE | P1 | coberto |
| LGPD-003 | atualização de status de solicitação de privacidade | BE | P1 | coberto |
| LGPD-004 | bloco de dados e privacidade renderiza orientação correta | FE-E2E | P2 | coberto |

## 4. Administração global

| ID | Cenário | Camada | Prioridade | Cobertura atual |
|---|---|---|---|---|
| ADMIN-001 | CRUD de usuário admin respeita permissões | BE | P0 | coberto |
| ADMIN-002 | CRUD de grupos respeita permissões | BE | P0 | coberto |
| ADMIN-003 | CRUD de funcionalidades respeita permissões | BE | P0 | coberto |
| ADMIN-004 | CRUD de planos respeita permissões | BE | P0 | coberto |
| ADMIN-005 | CRUD de empresas respeita permissões | BE | P0 | coberto |
| ADMIN-006 | CRUD de perfis da empresa respeita permissões | BE | P0 | coberto |
| ADMIN-007 | CRUD de usuários da empresa respeita permissões | BE | P0 | coberto |
| ADMIN-008 | owner role protection | BE | P0 | coberto |
| ADMIN-009 | tela administrativa principal por módulo | FE-E2E | P1 | coberto |
| ADMIN-010 | pendências de pagamento listam corretamente | FE-E2E | P2 | coberto |

## 5. Assinatura e cobrança SaaS

| ID | Cenário | Camada | Prioridade | Cobertura atual |
|---|---|---|---|---|
| SUB-001 | leitura da assinatura atual | BE | P0 | coberto |
| SUB-002 | contratação inicial sem assinatura | BE + FE-E2E | P0 | coberto |
| SUB-003 | contratação de plano pago exige token de cartão | BE | P0 | coberto |
| SUB-004 | contratação inicial exibe seleção de plano e período | FE-E2E | P0 | coberto |
| SUB-005 | contratação inicial bloqueia submit sem aceite de termos | FE-E2E | P0 | coberto |
| SUB-006 | cancelamento imediato | BE | P0 | coberto |
| SUB-007 | cancelamento fim de ciclo | BE | P0 | coberto |
| SUB-008 | renovação de cancelamento agendado | BE | P0 | coberto |
| SUB-009 | arrependimento dentro da janela | BE | P1 | coberto |
| SUB-010 | arrependimento fora da janela | BE | P1 | coberto |
| SUB-011 | troca de plano válida | BE | P1 | coberto |
| SUB-012 | troca de cartão válida | BE | P1 | coberto |
| SUB-013 | histórico de faturas | BE + FE-E2E | P1 | coberto |
| SUB-014 | geração de Pix de fatura | BE | P1 | coberto |
| SUB-015 | webhooks/preapproval Mercado Pago | BE | P0 | coberto |
| SUB-016 | fluxo visual de assinatura ativa/cancelada/suspensa | FE-E2E | P1 | coberto |

## 6. Onboarding e treinamento

| ID | Cenário | Camada | Prioridade | Cobertura atual |
|---|---|---|---|---|
| ONB-001 | checklist inicial vazio | BE | P1 | coberto |
| ONB-002 | checklist reflete produto/cliente/pedido existentes | BE | P1 | coberto |
| ONB-003 | dismiss do checklist persiste por vínculo usuário+empresa | BE | P1 | coberto |
| ONB-004 | treinamento abre sem quebrar contexto | FE-E2E | P2 | coberto |

## 7. Endereços e geolocalização

| ID | Cenário | Camada | Prioridade | Cobertura atual |
|---|---|---|---|---|
| GEO-001 | CRUD estados | BE | P1 | coberto |
| GEO-002 | CRUD cidades | BE | P1 | coberto |
| GEO-003 | CRUD bairros | BE | P1 | coberto |
| GEO-004 | CRUD endereços | BE | P1 | coberto |
| GEO-005 | create endereço dispara geocode | BE | P1 | coberto |
| GEO-006 | update endereço relevante dispara geocode | BE | P1 | coberto |
| GEO-007 | reverse geocode resolve estado/cidade/bairro | BE | P2 | coberto |

## 8. Clientes e CRM

| ID | Cenário | Camada | Prioridade | Cobertura atual |
|---|---|---|---|---|
| CRM-001 | CRUD cliente | BE | P0 | coberto |
| CRM-002 | cliente com endereço inline | BE | P0 | coberto |
| CRM-003 | sync de categorias do cliente | BE | P1 | coberto |
| CRM-004 | CRUD categoria de cliente | BE | P1 | coberto |
| CRM-005 | CRUD dia ideal | BE | P2 | coberto |
| CRM-006 | CRUD período ideal | BE | P2 | coberto |
| CRM-007 | PDF de clientes | BE | P2 | coberto |
| CRM-008 | fluxo frontend de novo cliente | FE-E2E | P1 | coberto |

## 9. Produtos e catálogo interno

| ID | Cenário | Camada | Prioridade | Cobertura atual |
|---|---|---|---|---|
| PROD-001 | CRUD produto | BE | P0 | coberto |
| PROD-002 | upload/substituição de imagem | BE | P1 | coberto |
| PROD-003 | grupos e opções de produto | BE | P1 | coberto |
| PROD-004 | CRUD categoria de produto | BE | P1 | coberto |
| PROD-005 | CRUD tipo de produto | BE | P1 | coberto |
| PROD-006 | importação CSV preview | BE | P1 | coberto |
| PROD-007 | importação CSV commit | BE | P1 | coberto |
| PROD-008 | PDF de produtos | BE | P2 | coberto |
| PROD-009 | toggle disponibilidade | BE | P1 | coberto |
| PROD-010 | preço por categoria de cliente | BE | P1 | coberto |

## 10. Estoque

| ID | Cenário | Camada | Prioridade | Cobertura atual |
|---|---|---|---|---|
| STOCK-001 | CRUD local de estoque | BE | P1 | coberto |
| STOCK-002 | local padrão por empresa | BE | P1 | coberto |
| STOCK-003 | movimentação de estoque | BE | P0 | coberto |
| STOCK-004 | saldo reflete pedidos/PDV | BE | P0 | coberto por pedidos+pdv |
| STOCK-005 | grid de saldos/movimentos | FE-E2E | P2 | coberto |

## 11. Pedidos internos

| ID | Cenário | Camada | Prioridade | Cobertura atual |
|---|---|---|---|---|
| ORD-001 | criar pedido manual | BE | P0 | coberto |
| ORD-002 | editar itens do pedido | BE | P0 | coberto |
| ORD-003 | reservas de estoque na criação | BE | P0 | coberto |
| ORD-004 | pagamento integral | BE | P0 | coberto |
| ORD-005 | pagamento parcial | BE | P1 | coberto |
| ORD-006 | entrega e baixa de reserva | BE | P0 | coberto |
| ORD-007 | desfazer entrega | BE | P1 | coberto |
| ORD-008 | desfazer pagamento | BE | P1 | coberto |
| ORD-009 | cancelamento antes/depois da entrega | BE | P0 | coberto |
| ORD-010 | parcelas create/update/delete/reallocate | BE | P0 | coberto |
| ORD-011 | limite de desconto | BE | P1 | coberto |
| ORD-012 | código sequencial por empresa | BE | P0 | coberto |
| ORD-013 | filtro por etapa operacional | BE | P1 | coberto |
| ORD-014 | tela pedidos manuais vazia | FE-E2E | P1 | coberto |
| ORD-015 | tela pedidos manuais populada | FE-E2E | P1 | coberto |
| ORD-016 | criação frontend de pedido manual | FE-E2E | P1 | coberto |

## 12. Pedidos da loja

| ID | Cenário | Camada | Prioridade | Cobertura atual |
|---|---|---|---|---|
| SFO-001 | fila ativa de pedidos da loja | BE | P0 | coberto |
| SFO-002 | aprovação de pedido | BE | P0 | coberto |
| SFO-003 | recusa de pedido | BE | P0 | coberto |
| SFO-004 | despacho | BE | P0 | coberto |
| SFO-005 | entrega | BE | P0 | coberto |
| SFO-006 | retorno de etapa undispatch/undeliver | BE | P1 | coberto |
| SFO-007 | pagamento final | BE | P1 | coberto |
| SFO-008 | board operacional renderiza fila | FE-E2E | P1 | coberto |
| SFO-009 | drag and drop entre etapas | FE-E2E | P1 | coberto |
| SFO-010 | cancelamento solicitado com decisão operacional | BE + FE-E2E | P1 | coberto |

## 13. Loja pública e checkout

| ID | Cenário | Camada | Prioridade | Cobertura atual |
|---|---|---|---|---|
| STF-001 | catálogo público disponível quando storefront ativo | BE | P0 | coberto |
| STF-002 | catálogo bloqueado quando storefront desativado | BE | P0 | coberto |
| STF-003 | categorias públicas | BE | P1 | coberto |
| STF-004 | carrinho persiste itens localmente | FE-E2E | P2 | coberto |
| STF-005 | taxa de entrega por bairro | BE | P1 | coberto |
| STF-006 | cupom válido/inválido | BE | P1 | coberto |
| STF-007 | promoção ativa no preço | BE | P1 | coberto |
| STF-008 | checkout feliz de delivery | BE | P0 | coberto |
| STF-009 | checkout pickup | BE | P1 | coberto |
| STF-010 | checkout wholesale | BE | P1 | coberto |
| STF-011 | guards de checkout fora do horário/loja desativada | BE | P0 | coberto |
| STF-012 | checkout frontend fim a fim | FE-E2E | P0 | coberto |
| STF-013 | telemetria de abandono de carrinho | BE | P2 | coberto |

## 14. Portal do cliente final

| ID | Cenário | Camada | Prioridade | Cobertura atual |
|---|---|---|---|---|
| PORTAL-001 | OTP login | BE | P0 | coberto |
| PORTAL-002 | pedidos do cliente autenticado | BE | P0 | coberto |
| PORTAL-003 | favoritos | BE | P1 | coberto |
| PORTAL-004 | endereços do cliente | BE | P1 | coberto |
| PORTAL-005 | vouchers do cliente | BE | P1 | coberto |
| PORTAL-006 | solicitação de cancelamento | BE | P1 | coberto |
| PORTAL-007 | cobrança Pix do próprio pedido | BE | P1 | coberto |
| PORTAL-008 | avaliação do pedido | BE | P2 | coberto |
| PORTAL-009 | reorder | BE | P2 | coberto |
| PORTAL-010 | portal frontend login e pedidos | FE-E2E | P1 | coberto |

## 15. PDV

| ID | Cenário | Camada | Prioridade | Cobertura atual |
|---|---|---|---|---|
| PDV-001 | impedir venda sem caixa aberto | BE | P0 | coberto |
| PDV-002 | venda com split payment válido | BE | P0 | coberto |
| PDV-003 | venda com pagamento divergente é rejeitada | BE | P0 | coberto |
| PDV-004 | venda sem cliente usa consumidor balcão | BE | P1 | coberto |
| PDV-005 | idempotência por client_sale_uuid | BE | P1 | coberto |
| PDV-006 | snapshot offline | BE | P1 | coberto |
| PDV-007 | PIN de operador | BE | P1 | coberto |
| PDV-008 | fluxo frontend PDV venda | FE-E2E | P0 | coberto |
| PDV-009 | fluxo offline controlado | MANUAL + FE-E2E | P1 | coberto |

## 16. Balcão

| ID | Cenário | Camada | Prioridade | Cobertura atual |
|---|---|---|---|---|
| BAL-001 | abertura de comanda | BE | P0 | coberto |
| BAL-002 | inclusão de item | BE | P0 | coberto |
| BAL-003 | reserva de mesa | BE | P1 | coberto |
| BAL-004 | fila de espera | BE | P1 | coberto |
| BAL-005 | seating de reserva/fila | BE | P1 | coberto |
| BAL-006 | snapshot offline balcão | MANUAL + BE indireto + FE-E2E | P1 | coberto |
| BAL-007 | conflito offline multi-dispositivo | FE-E2E | P1 | coberto |
| BAL-008 | frontend mesas/comandas/KDS | FE-E2E | P1 | coberto |

## 17. Rotas e logística

| ID | Cenário | Camada | Prioridade | Cobertura atual |
|---|---|---|---|---|
| ROUTE-001 | candidatos de rota delivery | BE | P1 | coberto |
| ROUTE-002 | candidatos de rota collection | BE | P1 | coberto |
| ROUTE-003 | planner frontend com filas reais | FE-E2E | P2 | coberto |

## 18. Analytics, relatórios e financeiro

| ID | Cenário | Camada | Prioridade | Cobertura atual |
|---|---|---|---|---|
| REP-001 | indicadores principais | BE | P1 | coberto |
| REP-002 | analytics por canal | BE | P1 | coberto |
| REP-003 | relatório de pedidos | BE | P1 | coberto |
| REP-004 | relatório de clientes | BE | P1 | coberto |
| REP-005 | recebíveis | BE | P1 | coberto |
| REP-006 | interações de cobrança | BE | P1 | coberto |
| REP-007 | conciliação financeira | BE | P1 | coberto |
| REP-008 | analytics frontend principal | FE-E2E | P2 | coberto |

## 19. Fiscal

| ID | Cenário | Camada | Prioridade | Cobertura atual |
|---|---|---|---|---|
| FISC-001 | CRUD regra tributária | BE | P1 | coberto |
| FISC-002 | CRUD perfil fiscal | BE | P1 | coberto |
| FISC-003 | readiness fiscal | BE | P1 | coberto |
| FISC-004 | preparar documento fiscal | BE | P1 | coberto |
| FISC-005 | preview fiscal do pedido | BE | P1 | coberto |
| FISC-006 | invalidar documento ao mudar pedido | BE | P1 | coberto |
| FISC-007 | frontend regras/perfis fiscais | FE-E2E | P2 | coberto |

## 20. Contabilidade externa

| ID | Cenário | Camada | Prioridade | Cobertura atual |
|---|---|---|---|---|
| ACC-001 | cadastro do contador | BE | P1 | coberto |
| ACC-002 | confirmação TOTP | BE | P1 | coberto |
| ACC-003 | login contador | BE | P1 | coberto |
| ACC-004 | solicitar acesso por CNPJ | BE | P1 | coberto |
| ACC-005 | aprovar e revogar acesso | BE | P1 | coberto |
| ACC-006 | mensagens contador x empresa | BE | P1 | coberto |
| ACC-007 | relatórios do contador | BE | P1 | coberto |
| ACC-008 | frontend contador completo | FE-E2E | P2 | coberto |

## 21. Integrações, webhooks e marketplace

| ID | Cenário | Camada | Prioridade | Cobertura atual |
|---|---|---|---|---|
| INT-001 | criar/revogar API key | BE | P1 | coberto |
| INT-002 | assinatura de webhook create/update/delete | BE | P1 | coberto |
| INT-003 | dispatch webhook assinado | BE | P1 | coberto |
| INT-004 | integração marketplace iFood end-to-end backend | BE | P1 | coberto |
| INT-005 | fila iFood frontend | FE-E2E | P2 | coberto |
| INT-006 | saúde Mercado Pago por comando | BE | P2 | coberto |

## 22. Suporte e observabilidade

| ID | Cenário | Camada | Prioridade | Cobertura atual |
|---|---|---|---|---|
| OPS-001 | healthcheck retorna saudável | BE | P0 | coberto |
| OPS-002 | healthcheck falha com dependência indisponível | BE | P0 | coberto |
| OPS-003 | CORS | BE | P1 | coberto |
| OPS-004 | CSP | BE | P1 | coberto |
| OPS-005 | suporte create/list/update | BE | P2 | coberto |
| OPS-006 | smoke pós-deploy público | FE-E2E + SMOKE | P0 | coberto |
| OPS-007 | smoke pós-deploy autenticado | FE-E2E + SMOKE | P1 | coberto por CI parametrizada |

## Fechamento

### Cobertura atual já existente

- backend: muito forte
- frontend E2E: base já ampliada e útil para release controlada
- smoke pós-deploy: disponível e parametrizável

### Próxima onda recomendada de automação executável

1. `GEO-001` a `GEO-004` navegação E2E dos cadastros geográficos principais  
2. `CRM-008` expansão do fluxo de clientes para edição e navegação associada  
3. `PROD-001` a `PROD-010` recorte inicial de catálogo interno com CRUD e disponibilidade  
4. `STOCK-001` e `STOCK-005` expansão do fluxo frontend para criação/edição de movimentações  
5. `ORD-002` a `ORD-013` expansão operacional do pedido interno além da criação manual  
6. `PORTAL-003` a `PORTAL-009` expansão do portal do cliente para favoritos, endereços e reorder

### Regra de atualização

Nenhuma funcionalidade nova deve entrar sem:

- ID novo neste catálogo
- indicação da camada alvo de teste
- estado inicial de cobertura (`coberto`, `parcial`, `pendente`)
