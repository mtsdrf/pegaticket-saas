# PegaTicket — Especificação Completa de Requisitos Funcionais e Não Funcionais

> Documento de referência para produto, arquitetura, desenvolvimento, testes, segurança, operação e evolução de uma plataforma SaaS completa de venda e gestão de ingressos.
>
> Escopo exclusivamente funcional e não funcional. Elementos de identidade visual, estilo, cores, tipografia e direção estética foram removidos.

---

## 1. Objetivo do produto

O PegaTicket deve ser uma plataforma SaaS multiempresa capaz de atender toda a cadeia operacional de eventos:

1. cadastro e homologação de organizadores;
2. criação e publicação de eventos;
3. configuração de inventário, ingressos, lugares, setores, lotes e canais;
4. divulgação, descoberta e aquisição de público;
5. reserva, compra e pagamento;
6. emissão, entrega, transferência e gestão de ingressos digitais;
7. atendimento ao comprador;
8. credenciamento, bilheteria e controle de acesso;
9. gestão financeira, fiscal, contábil e de repasses;
10. gestão operacional durante o evento;
11. análise de resultados, relacionamento e recompra após o evento;
12. integrações com serviços externos, parceiros, afiliados e sistemas corporativos.

A plataforma deve operar eventos gratuitos e pagos, presenciais, online e híbridos, de pequeno a grande porte, incluindo:

- shows, festas e festivais;
- teatros, cinemas, museus e atrações recorrentes;
- congressos, conferências, feiras e workshops;
- eventos esportivos;
- clubes, formaturas e eventos privados;
- cursos, treinamentos e transmissões online;
- experiências com horário marcado;
- eventos com mesas, assentos, camarotes ou setores;
- eventos multiatração, multidata e multilocal;
- temporadas e recorrências;
- eventos corporativos com convites e credenciamento;
- eventos públicos com gratuidades e regras específicas.

---

## 2. Visão competitiva e princípios de diferenciação

Plataformas concorrentes já oferecem recursos como check-in por QR Code, acompanhamento de entradas em tempo real, formulários personalizados por ingresso, virada automática de lote, mapas de assentos, listas de espera, cupons, ferramentas de marketing, carteira digital, transferência, revenda verificada, fila virtual e soluções de consumo cashless.

Para competir em nível elevado, o PegaTicket não deve ser apenas um emissor de ingressos. Deve combinar:

- plataforma de ticketing;
- marketplace de descoberta;
- checkout de alta conversão;
- infraestrutura de pagamentos;
- CRM e automação de marketing;
- controle de acesso resiliente;
- bilheteria e ponto de venda;
- antifraude e anti-bot;
- gestão financeira e repasses;
- analytics e inteligência operacional;
- API e ecossistema de integrações;
- atendimento omnichannel;
- gestão de consumo e receitas adicionais;
- arquitetura preparada para grandes picos de demanda.

### 2.1 Diferenciais estratégicos recomendados

O produto deve buscar superar concorrentes pelos seguintes eixos:

1. **Operação unificada:** venda, acesso, credenciamento, consumo, suporte e financeiro no mesmo ambiente.
2. **Automação:** lotes, campanhas, alertas, repasses, comunicações e conciliações automatizadas.
3. **Transparência financeira:** taxas, reservas, chargebacks, antecipações e repasses detalhados por pedido.
4. **Alta disponibilidade:** checkout, fila e check-in preparados para eventos de alta demanda.
5. **Segurança nativa:** QR rotativo, bloqueio de fraude, autenticação forte e trilha completa de auditoria.
6. **Dados acionáveis:** indicadores em tempo real e recomendações práticas de venda e operação.
7. **Flexibilidade comercial:** white-label, múltiplos canais, comissionamento, afiliados, promotores e parceiros.
8. **Experiência pós-compra:** transferência, revenda oficial, upgrade, carteira digital, suporte e comunicação contínua.
9. **Arquitetura aberta:** API pública, webhooks, SDKs e integrações sem aprisionamento.
10. **Operação offline:** validação e venda local resilientes mesmo com conectividade instável.

---

## 3. Perfis e atores do sistema

### 3.1 Comprador

Pessoa que pesquisa eventos, realiza pedidos, efetua pagamentos, recebe e administra ingressos.

### 3.2 Participante ou portador

Pessoa vinculada a um ingresso. Pode ser diferente do comprador.

### 3.3 Organizador

Pessoa física ou jurídica responsável por um ou mais eventos.

### 3.4 Administrador da organização

Responsável por configurações, equipe, financeiro, integrações, contratos e permissões da organização.

### 3.5 Produtor ou gestor de evento

Responsável pela configuração e operação de eventos específicos.

### 3.6 Operador financeiro

Responsável por conciliação, repasses, estornos, documentos e relatórios financeiros.

### 3.7 Operador de atendimento

Responsável por solicitações de compradores, pedidos, ingressos, reembolsos e ocorrências.

### 3.8 Operador de bilheteria

Responsável por vendas presenciais, impressão, retirada e atendimento local.

### 3.9 Operador de acesso

Responsável por leitura, validação, check-in e tratamento de exceções na portaria.

### 3.10 Supervisor de acesso

Responsável por portarias, dispositivos, permissões, bloqueios, liberações e acompanhamento em tempo real.

### 3.11 Promotor, comissário ou afiliado

Responsável por vendas atribuídas, links, cupons, listas e comissões.

### 3.12 Parceiro, patrocinador ou cortesia institucional

Responsável por cotas, convites, reservas ou distribuição controlada.

### 3.13 Administrador da plataforma

Responsável pelo SaaS, organizações, riscos, pagamentos, auditoria, suporte, planos e configurações globais.

### 3.14 Serviço externo

Gateway, adquirente, antifraude, mensageria, ERP, CRM, catraca, carteira digital, streaming, fiscal ou outro sistema integrado.

---

## 4. Arquitetura de produto multiempresa

### RF-MT-001 — Isolamento por organização

O sistema deve isolar dados, configurações, usuários, eventos, pedidos, ingressos, recebíveis e relatórios por organização.

### RF-MT-002 — Usuário em múltiplas organizações

Um mesmo usuário deve poder participar de múltiplas organizações com papéis distintos.

### RF-MT-003 — Hierarquia organizacional

Deve ser possível representar grupos econômicos, produtoras, filiais, marcas, unidades e projetos.

### RF-MT-004 — Configuração por organização

Cada organização deve possuir configurações próprias de:

- dados cadastrais;
- documentos;
- contas bancárias;
- responsáveis;
- contratos e taxas;
- meios de pagamento;
- políticas de cancelamento;
- domínio e canais;
- integrações;
- limites operacionais;
- comunicação;
- emissão fiscal;
- regras de privacidade;
- permissões.

### RF-MT-005 — White-label

A plataforma deve permitir operação white-label por organização ou evento, incluindo domínio personalizado, remetentes, política comercial, textos legais, canais de atendimento e credenciais de integração.

### RF-MT-006 — Planos e cobrança SaaS

Deve suportar:

- plano gratuito;
- mensalidade fixa;
- cobrança por uso;
- cobrança por ingresso;
- cobrança por evento;
- cobrança por dispositivo;
- cobrança por módulo;
- franquias e excedentes;
- descontos contratuais;
- planos personalizados;
- período de teste;
- suspensão por inadimplência;
- faturamento e notas da assinatura.

---

## 5. Cadastro, autenticação e identidade

### RF-ID-001 — Cadastro de usuário

Permitir cadastro por e-mail, telefone e provedores de identidade autorizados.

### RF-ID-002 — Verificação de identidade de contato

Validar e-mail e telefone por código, link ou desafio equivalente.

### RF-ID-003 — Login seguro

Suportar senha, login sem senha, passkeys e provedores OAuth/OIDC.

### RF-ID-004 — Autenticação multifator

Disponibilizar MFA por aplicativo autenticador, passkey, e-mail ou SMS, com exigência configurável por perfil de risco.

### RF-ID-005 — Sessões e dispositivos

Permitir consultar e revogar sessões e dispositivos conectados.

### RF-ID-006 — Recuperação de acesso

Disponibilizar recuperação segura de conta, com prevenção contra tomada de conta.

### RF-ID-007 — Perfil do usuário

Manter nome, nome social quando aplicável, documento, data de nascimento, telefone, e-mail, endereço, preferências, idioma, acessibilidade e consentimentos.

### RF-ID-008 — Identificação do participante

Permitir definir quais dados são obrigatórios para comprador e portador, por evento e por tipo de ingresso.

### RF-ID-009 — KYC/KYB do organizador

O onboarding do organizador deve suportar verificação de pessoa física ou jurídica, beneficiários, representantes, documentos, conta bancária e análise de risco.

### RF-ID-010 — Aprovação e revisão

Organizações podem possuir estados como rascunho, em análise, pendente, aprovada, restrita, suspensa e encerrada.

### RF-ID-011 — Consentimentos

Registrar versão, data, origem e finalidade de aceite de termos, políticas e comunicações.

---

## 6. Gestão de organizações, equipes e permissões

### RF-ORG-001 — Convite de membros

Administradores devem poder convidar usuários por e-mail ou link com prazo de validade.

### RF-ORG-002 — Controle de acesso baseado em papéis

Suportar RBAC com permissões granulares por recurso e ação.

### RF-ORG-003 — Escopo por evento

Usuários podem ter acesso a apenas eventos, locais, portarias ou módulos específicos.

### RF-ORG-004 — Papéis personalizados

A organização deve poder criar papéis próprios além dos papéis padrões.

### RF-ORG-005 — Segregação de funções

Permitir separar criação, aprovação, execução e auditoria de operações sensíveis.

### RF-ORG-006 — Aprovação em múltiplas etapas

Operações de alto risco devem poder exigir aprovação de outro usuário, como:

- alteração de conta bancária;
- antecipação;
- reembolso em massa;
- cortesia em massa;
- alteração de capacidade;
- liberação de bloqueios;
- exportação de dados pessoais;
- mudança de taxas;
- cancelamento de evento.

### RF-ORG-007 — Trilha de auditoria

Registrar autor, data, origem, valores anteriores e posteriores de ações administrativas.

---

## 7. Gestão de locais, espaços e estruturas

### RF-LOC-001 — Cadastro de local

Cadastrar nome, endereço, coordenadas, capacidade, contatos, regras, acessibilidade e informações operacionais.

### RF-LOC-002 — Ambientes internos

Um local pode conter múltiplos ambientes, palcos, salas, setores, pisos, portões e áreas.

### RF-LOC-003 — Planta e mapa de assentos

Permitir criação e versionamento de mapas com:

- setores;
- fileiras;
- assentos;
- mesas;
- cadeiras;
- camarotes;
- boxes;
- áreas livres;
- espaços PCD e acompanhantes;
- visibilidade parcial;
- bloqueios técnicos;
- entradas e portões;
- preços por posição;
- metadados de acessibilidade.

### RF-LOC-004 — Modelos reutilizáveis

Mapas e configurações de local devem ser reutilizáveis entre eventos.

### RF-LOC-005 — Versionamento de mapa

Alterações após início de vendas devem gerar versão controlada e preservar pedidos existentes.

### RF-LOC-006 — Visão do assento

Permitir associar imagem ou referência de visão por setor ou assento.

### RF-LOC-007 — Capacidade operacional

Controlar capacidades máximas por local, ambiente, setor, sessão e portaria.

### RF-LOC-008 — Rotas e portarias

Relacionar tipos de ingresso e setores às portarias permitidas.

---

## 8. Criação e gestão de eventos

### RF-EVT-001 — Cadastro de evento

O evento deve conter:

- título e descrição;
- categoria e subcategoria;
- organizador responsável;
- local ou ambiente online;
- fuso horário;
- data e horário de início e fim;
- política etária;
- capacidade;
- termos e regras;
- contatos;
- informações de acessibilidade;
- status de publicação;
- política de cancelamento;
- política de transferência;
- política de reentrada;
- regras de meia-entrada e benefícios;
- documentos exigidos;
- configurações fiscais e financeiras.

### RF-EVT-002 — Tipos de evento

Suportar presencial, online, híbrido, recorrente, temporada, sessão, multidata, multilocal, privado e por convite.

### RF-EVT-003 — Sessões

Um evento pode possuir múltiplas sessões, cada uma com data, horário, capacidade, preço e inventário próprios.

### RF-EVT-004 — Temporadas e recorrência

Permitir gerar sessões recorrentes por regras de calendário e editar uma ocorrência ou série completa.

### RF-EVT-005 — Evento pai e subeventos

Permitir festivais, congressos e competições com programação composta por subeventos.

### RF-EVT-006 — Estados do evento

No mínimo:

- rascunho;
- em revisão;
- agendado;
- publicado;
- vendas pausadas;
- esgotado;
- em andamento;
- encerrado;
- adiado;
- cancelado;
- arquivado.

### RF-EVT-007 — Fluxo de aprovação

Eventos podem exigir aprovação interna ou da plataforma antes da publicação.

### RF-EVT-008 — Duplicação

Permitir duplicar evento completo ou somente configurações selecionadas.

### RF-EVT-009 — Pré-visualização e validação

Antes da publicação, validar obrigatoriedades, capacidade, datas, taxas, financeiro e regras conflitantes.

### RF-EVT-010 — Publicação programada

Permitir programar publicação e despublicação.

### RF-EVT-011 — Evento oculto ou privado

Permitir acesso por link, senha, lista autorizada, domínio corporativo ou código.

### RF-EVT-012 — Alterações após venda

Mudanças relevantes devem gerar alerta, histórico e comunicação aos compradores afetados.

### RF-EVT-013 — Adiamento e cancelamento

Disponibilizar fluxos para nova data, aceite do comprador, crédito, troca ou reembolso conforme política e legislação.

### RF-EVT-014 — Lista de tarefas do evento

Permitir checklist operacional com responsáveis, prazos, anexos e status.

### RF-EVT-015 — Documentos e contratos

Anexar alvarás, contratos, mapas, autorizações e arquivos operacionais.

---

## 9. Inventário, ingressos, lotes e disponibilidade

### RF-INV-001 — Tipos de ingresso

Suportar:

- pago;
- gratuito;
- cortesia;
- convite;
- inteira;
- meia-entrada;
- social ou promocional;
- PCD e acompanhante;
- infantil, estudante, idoso ou categorias configuráveis;
- VIP;
- passaporte;
- combo;
- pacote de dias;
- assinatura ou temporada;
- ingresso nominal;
- ingresso transferível;
- ingresso corporativo;
- credencial de staff, imprensa, artista ou fornecedor.

### RF-INV-002 — Lotes

Cada tipo de ingresso pode ter múltiplos lotes com preço, quantidade, período e regras próprias.

### RF-INV-003 — Virada automática de lote

Permitir virada por:

- data e hora;
- quantidade vendida;
- esgotamento;
- percentual de ocupação;
- meta de receita;
- condição combinada;
- comando manual.

### RF-INV-004 — Inventário compartilhado

Permitir que vários tipos ou canais consumam a mesma capacidade física.

### RF-INV-005 — Cotas por canal

Reservar inventário para site, bilheteria, parceiros, promotores, patrocinadores, pré-venda e contingência.

### RF-INV-006 — Bloqueios e holds

Permitir bloquear temporária ou permanentemente assentos e quantidades, com motivo, responsável e expiração.

### RF-INV-007 — Limites de compra

Configurar mínimo e máximo por pedido, CPF, conta, cartão, telefone, endereço, dispositivo ou período.

### RF-INV-008 — Regras de elegibilidade

Restringir compra por:

- código de pré-venda;
- associação;
- documento;
- domínio de e-mail;
- grupo;
- histórico;
- região;
- convite;
- programa de fidelidade;
- faixa etária;
- titularidade de produto.

### RF-INV-009 — Pré-vendas

Suportar pré-venda de artista, fã-clube, patrocinador, banco, parceiro, cliente recorrente e lista segmentada.

### RF-INV-010 — Preço dinâmico

Permitir preço variável por demanda, ocupação, período, canal ou regra, respeitando transparência e limites configurados.

### RF-INV-011 — Taxas e composição de preço

Permitir configurar:

- preço base;
- taxa de serviço;
- taxa de processamento;
- taxa de conveniência;
- taxa de entrega;
- taxa local;
- seguro opcional;
- tributos;
- descontos;
- absorção total ou parcial de taxas pelo organizador;
- preço final máximo.

### RF-INV-012 — Parcelamento e acréscimos

Configurar quantidade de parcelas, juros, subsídio e valor mínimo por parcela.

### RF-INV-013 — Assentos marcados

A seleção deve garantir reserva atômica e impedir venda duplicada.

### RF-INV-014 — Melhor assento disponível

O sistema deve sugerir automaticamente melhores assentos com base em quantidade, proximidade e regras do local.

### RF-INV-015 — Assentos contíguos

Evitar assentos órfãos e aplicar regras de contiguidade configuráveis.

### RF-INV-016 — Lista de espera

Quando esgotado, permitir cadastro em lista de espera por tipo, setor ou sessão.

### RF-INV-017 — Liberação da lista de espera

Liberar vagas por ordem, prioridade ou sorteio, com janela exclusiva de compra.

### RF-INV-018 — Reservas administrativas

Permitir criar reservas sem pagamento imediato com prazo, responsável e condições.

### RF-INV-019 — Passaportes e combos

Um produto pode conceder acesso a vários dias, sessões, setores ou eventos.

### RF-INV-020 — Upgrade e downgrade

Permitir troca para categoria superior ou inferior, calculando diferença, taxas e elegibilidade.

---

## 10. Catálogo, descoberta e marketplace

### RF-MKT-001 — Catálogo público

Oferecer busca e navegação por eventos publicados.

### RF-MKT-002 — Busca

Pesquisar por título, artista, organizador, local, cidade, categoria, data e palavras-chave.

### RF-MKT-003 — Filtros

Filtrar por data, distância, categoria, faixa de preço, gratuito, acessibilidade, faixa etária, formato e disponibilidade.

### RF-MKT-004 — Geolocalização

Sugerir eventos próximos mediante consentimento.

### RF-MKT-005 — Recomendação personalizada

Recomendar eventos por interesse, localização, comportamento e histórico, com transparência e opção de controle.

### RF-MKT-006 — Favoritos

Permitir salvar eventos, artistas, locais e organizadores.

### RF-MKT-007 — Alertas de evento

Notificar abertura de vendas, virada de lote, baixa disponibilidade, sessão adicional e retorno de estoque.

### RF-MKT-008 — Calendário

Permitir adicionar evento a calendários externos.

### RF-MKT-009 — Compartilhamento

Gerar links rastreáveis para compartilhamento.

### RF-MKT-010 — SEO e indexação

Fornecer metadados, dados estruturados, URLs estáveis, sitemap, canonical e controles de indexação.

### RF-MKT-011 — Moderação

Eventos devem passar por regras automáticas e revisão manual quando necessário.

### RF-MKT-012 — Avaliação pós-evento

Permitir avaliação verificada do evento e coleta de feedback, com moderação.

---

## 11. Carrinho, reserva e checkout

### RF-CHK-001 — Carrinho

Permitir reunir ingressos, adicionais e produtos elegíveis.

### RF-CHK-002 — Reserva temporária

Itens devem ser reservados por prazo configurável durante checkout.

### RF-CHK-003 — Temporizador confiável

O prazo deve ser controlado no servidor e exibido ao cliente.

### RF-CHK-004 — Expiração

Ao expirar, liberar inventário de forma segura e idempotente.

### RF-CHK-005 — Checkout convidado

Permitir compra sem cadastro prévio, criando ou vinculando conta posteriormente.

### RF-CHK-006 — Identificação progressiva

Solicitar apenas os dados necessários em cada etapa.

### RF-CHK-007 — Formulários personalizados

Permitir perguntas por pedido, ingresso ou participante, com:

- texto curto e longo;
- número;
- data;
- seleção única;
- múltipla seleção;
- aceite;
- documento;
- upload;
- dependência condicional;
- validação personalizada;
- obrigatoriedade por ingresso.

### RF-CHK-008 — Validação em tempo real

Validar dados antes do envio final, sem depender apenas do cliente.

### RF-CHK-009 — Resumo transparente

Exibir preço base, descontos, taxas, juros, adicionais e total antes da confirmação.

### RF-CHK-010 — Cupom e benefício

Aplicar códigos promocionais, créditos, gift cards e benefícios elegíveis.

### RF-CHK-011 — Múltiplos meios de pagamento

Permitir combinações quando suportadas, como crédito + saldo ou gift card + Pix.

### RF-CHK-012 — Retentativa segura

Permitir tentar outro pagamento sem perder a reserva, dentro das regras.

### RF-CHK-013 — Recuperação de checkout

Identificar abandono e permitir retomada segura enquanto houver disponibilidade.

### RF-CHK-014 — Compra em alta demanda

Integrar-se à fila virtual e controlar taxa de entrada no checkout.

### RF-CHK-015 — Idempotência

Reenvios ou callbacks duplicados não podem gerar pedidos ou cobranças duplicadas.

### RF-CHK-016 — Confirmação

Após pagamento, informar estado do pedido, próximos passos e canais de suporte.

### RF-CHK-017 — Compra em grupo

Permitir informar dados de participantes depois da compra dentro de prazo configurado.

### RF-CHK-018 — Divisão de pagamento

Opcionalmente permitir que participantes paguem suas partes antes da emissão definitiva.

### RF-CHK-019 — Acessibilidade no checkout

O fluxo deve ser utilizável por teclado, leitor de tela, ampliação e tecnologias assistivas.

---

## 12. Pagamentos

### RF-PAY-001 — Métodos de pagamento

No Brasil, suportar ao menos:

- Pix;
- cartão de crédito;
- cartão de débito quando disponível;
- boleto quando aplicável;
- carteiras digitais;
- saldo interno, crédito ou gift card;
- pagamento presencial;
- faturamento corporativo.

### RF-PAY-002 — Múltiplos provedores

A arquitetura deve permitir múltiplos gateways, adquirentes e subadquirentes.

### RF-PAY-003 — Roteamento inteligente

Permitir roteamento por custo, disponibilidade, bandeira, país, risco, organização ou taxa de aprovação.

### RF-PAY-004 — Tokenização

Dados sensíveis de cartão devem ser tokenizados por provedor compatível, reduzindo escopo PCI.

### RF-PAY-005 — Autenticação de pagamento

Suportar mecanismos como 3-D Secure quando aplicável.

### RF-PAY-006 — Estados de pagamento

No mínimo:

- criado;
- aguardando;
- em análise;
- autorizado;
- capturado;
- aprovado;
- recusado;
- expirado;
- cancelado;
- parcialmente estornado;
- estornado;
- contestado;
- chargeback;
- recuperado.

### RF-PAY-007 — Autorização e captura

Permitir captura imediata ou posterior quando o provedor suportar.

### RF-PAY-008 — Pix

Gerar QR Code e código copia e cola, consultar expiração, receber confirmação assíncrona e reconciliar por identificador.

### RF-PAY-009 — Parcelamento

Calcular parcelas, juros e responsabilidade pelo custo.

### RF-PAY-010 — Split de pagamento

Distribuir valores entre plataforma, organizador e parceiros conforme contrato e regulamentação.

### RF-PAY-011 — Retenção e reserva

Manter reservas financeiras para riscos, reembolsos e chargebacks.

### RF-PAY-012 — Antifraude

Submeter transações a regras e provedor antifraude, com decisão automática ou revisão manual.

### RF-PAY-013 — Retentativas

Aplicar retentativas controladas em falhas transitórias sem duplicidade.

### RF-PAY-014 — Webhooks confiáveis

Processar notificações com assinatura, idempotência, ordenação tolerante e reprocessamento.

### RF-PAY-015 — Conciliação

Conciliar pedido, pagamento, taxa, recebível, liquidação, estorno e repasse.

### RF-PAY-016 — Falha de provedor

Permitir contingência e troca de rota sem corromper pedidos.

### RF-PAY-017 — Link de pagamento

Gerar link para reserva, pedido administrativo ou cobrança complementar.

### RF-PAY-018 — Pagamento presencial

Integrar terminais, TEF ou adquirentes para bilheteria e pontos móveis.

---

## 13. Pedidos

### RF-ORD-001 — Número e identificador

Cada pedido deve possuir identificador interno imutável e código amigável.

### RF-ORD-002 — Estados do pedido

No mínimo:

- rascunho;
- reservado;
- aguardando pagamento;
- em análise;
- confirmado;
- parcialmente atendido;
- cancelado;
- expirado;
- reembolsado parcialmente;
- reembolsado;
- contestado.

### RF-ORD-003 — Linha do tempo

Exibir histórico de criação, pagamentos, emissões, comunicações, alterações, acessos e suporte.

### RF-ORD-004 — Origem

Registrar canal, campanha, afiliado, dispositivo, operador e ponto de venda.

### RF-ORD-005 — Alterações administrativas

Alterações devem ser permissionadas, justificadas e auditadas.

### RF-ORD-006 — Pedido manual

Operadores autorizados podem gerar pedidos, reservas, cobranças, cortesias e vendas assistidas.

### RF-ORD-007 — Importação

Importar pedidos ou participantes de outros sistemas com validação, deduplicação e relatório de erros.

### RF-ORD-008 — Exportação

Exportar dados conforme permissão e privacidade.

### RF-ORD-009 — Documentos

Gerar comprovante, recibo, declaração, invoice ou documento fiscal conforme configuração.

### RF-ORD-010 — Busca operacional

Pesquisar por pedido, nome, e-mail, telefone, documento, ingresso, pagamento, cartão mascarado ou código.

---

## 14. Emissão e gestão de ingressos

### RF-TKT-001 — Ingresso único

Cada ingresso deve possuir identificador único e vínculo com pedido, evento, produto e portador.

### RF-TKT-002 — Código de validação

Emitir QR Code, barcode ou credencial NFC conforme configuração.

### RF-TKT-003 — QR seguro

Suportar token assinado, expiração, QR rotativo ou mecanismo equivalente para eventos de maior risco.

### RF-TKT-004 — Entrega

Disponibilizar ingresso por conta, aplicativo/PWA, e-mail, SMS, link seguro e carteira digital.

### RF-TKT-005 — Apple Wallet e Google Wallet

Permitir emissão de passes, atualização, invalidação e lembretes conforme APIs disponíveis.

### RF-TKT-006 — Funcionamento offline do portador

O ingresso deve poder ser acessado sem conexão após carregado ou salvo.

### RF-TKT-007 — Proteção contra captura de tela

Para eventos configurados, utilizar código dinâmico e sinalização que reduza reutilização de screenshots.

### RF-TKT-008 — Nominalidade

Permitir ingresso nominal obrigatório ou opcional.

### RF-TKT-009 — Alteração de titularidade

Permitir editar portador dentro de regras, prazo, custo e limite.

### RF-TKT-010 — Transferência

Permitir transferência segura para outra conta, com aceite, expiração e revogação antes do aceite.

### RF-TKT-011 — Reemissão

Ao transferir, trocar, cancelar ou detectar risco, invalidar credencial anterior e emitir nova.

### RF-TKT-012 — Revenda oficial

Permitir revenda verificada dentro da plataforma, com regras de preço, taxas, elegibilidade e pagamento ao vendedor.

### RF-TKT-013 — Revenda a valor nominal

Oferecer opção de limitar revenda ao valor original ou teto definido.

### RF-TKT-014 — Bloqueio de transferência e revenda

Configurar por evento, tipo, período, categoria ou legislação aplicável.

### RF-TKT-015 — Ingresso multiacesso

Permitir limite de entradas, dias, sessões ou áreas.

### RF-TKT-016 — Reentrada

Configurar saída e reentrada com contagem, intervalo ou validação adicional.

### RF-TKT-017 — Ingresso de grupo

Permitir um comprador administrar múltiplos ingressos, mantendo identidade individual quando exigida.

### RF-TKT-018 — Certificado e comprovante de participação

Emitir certificado após critérios como check-in, permanência ou conclusão.

### RF-TKT-019 — Status do ingresso

No mínimo:

- reservado;
- emitido;
- transferindo;
- transferido;
- anunciado para revenda;
- revendido;
- bloqueado;
- cancelado;
- expirado;
- parcialmente utilizado;
- utilizado.

---

## 15. Cupons, promoções, convites e cortesias

### RF-PRM-001 — Cupons

Criar cupom percentual, valor fixo, isenção de taxa, preço especial ou produto gratuito.

### RF-PRM-002 — Restrições

Restringir por evento, ingresso, lote, canal, quantidade, usuário, CPF, domínio, período e valor mínimo.

### RF-PRM-003 — Uso único ou múltiplo

Definir limite global e por usuário.

### RF-PRM-004 — Códigos em massa

Gerar lotes de códigos únicos e importar códigos externos.

### RF-PRM-005 — Links promocionais

Aplicar benefício automaticamente por link rastreável.

### RF-PRM-006 — Convites

Distribuir convites com aceite, identificação e prazo.

### RF-PRM-007 — Cortesias

Emitir cortesias individualmente ou em massa, com motivo, cota e aprovação.

### RF-PRM-008 — Lista de convidados

Gerenciar lista por nome, documento, categoria, acompanhante e status de entrada.

### RF-PRM-009 — Campanhas condicionais

Oferecer desconto por compra antecipada, volume, grupo, fidelidade ou recuperação de abandono.

### RF-PRM-010 — Compre e ganhe

Suportar regras do tipo leve X, pague Y, ingresso + adicional, grupo e pacote.

---

## 16. Afiliados, promotores, comissários e canais

### RF-AFF-001 — Cadastro de afiliado

Cadastrar usuário, contrato, documento, dados de pagamento e escopo.

### RF-AFF-002 — Link e código rastreável

Gerar identificadores por afiliado, evento e campanha.

### RF-AFF-003 — Atribuição

Definir modelo de atribuição, janela, prioridade e tratamento de conflito.

### RF-AFF-004 — Comissão

Calcular comissão fixa, percentual, por faixa, meta, ingresso ou receita líquida.

### RF-AFF-005 — Painel do afiliado

Exibir cliques, pedidos, vendas, cancelamentos, comissões e pagamentos.

### RF-AFF-006 — Metas e ranking

Permitir metas individuais ou de equipe, sem expor dados indevidos.

### RF-AFF-007 — Cotas

Reservar inventário ou limites de venda por parceiro.

### RF-AFF-008 — Liquidação

Gerenciar comissão pendente, aprovada, retida, cancelada e paga.

### RF-AFF-009 — Venda assistida

Promotores autorizados podem criar carrinhos ou links personalizados sem acessar dados financeiros completos.

---

## 17. Marketing, CRM e relacionamento

### RF-CRM-001 — Perfil unificado

Consolidar contatos, compras, presença, preferências, consentimentos, suporte e origem.

### RF-CRM-002 — Segmentação

Segmentar por comportamento, localização, evento, categoria, compra, presença, valor, frequência e engajamento.

### RF-CRM-003 — Audiências

Criar audiências dinâmicas e estáticas.

### RF-CRM-004 — Campanhas

Enviar e-mail, SMS, push e mensagens por integrações autorizadas.

### RF-CRM-005 — Automação

Criar jornadas baseadas em gatilhos, por exemplo:

- cadastro sem compra;
- abandono de checkout;
- pagamento pendente;
- compra confirmada;
- proximidade do evento;
- alteração de evento;
- transferência pendente;
- pós-evento;
- aniversário;
- retorno de estoque;
- virada de lote;
- nova sessão.

### RF-CRM-006 — Preferências e descadastro

Respeitar consentimentos, canais, finalidades e opt-out.

### RF-CRM-007 — UTM e origem

Registrar parâmetros de campanha e atribuição.

### RF-CRM-008 — Pixels e conversões

Integrar pixels e APIs de conversão com controle de consentimento.

### RF-CRM-009 — Remarketing

Disponibilizar públicos e eventos de conversão, respeitando privacidade.

### RF-CRM-010 — Testes A/B

Permitir testar campanhas, assuntos, ofertas e fluxos quando aplicável.

### RF-CRM-011 — Recomendações

Sugerir eventos e adicionais com base em afinidade e elegibilidade.

### RF-CRM-012 — Fidelidade

Suportar pontos, níveis, benefícios, cashback promocional e acesso antecipado.

### RF-CRM-013 — Indicação

Oferecer programa de indicação com rastreamento e recompensa.

### RF-CRM-014 — Pesquisa

Enviar NPS, CSAT e pesquisas personalizadas.

---

## 18. Comunicação transacional

### RF-COM-001 — Eventos de comunicação

Enviar notificações para cadastro, pedido, pagamento, emissão, transferência, reembolso, alteração e acesso.

### RF-COM-002 — Múltiplos canais

Suportar e-mail, SMS, push, mensageria autorizada e notificações internas.

### RF-COM-003 — Templates

Templates devem ser versionados, localizados e personalizáveis por organização dentro de limites.

### RF-COM-004 — Preferência de idioma

Usar idioma do usuário ou evento.

### RF-COM-005 — Fallback

Aplicar canal alternativo para mensagens críticas quando permitido.

### RF-COM-006 — Rastreamento

Registrar envio, entrega, falha, abertura e clique quando permitido.

### RF-COM-007 — Reenvio

Permitir reenvio manual seguro sem expor dados indevidos.

### RF-COM-008 — Alertas operacionais

Notificar equipe sobre fraude, pico, falha, estoque, check-in, repasse e incidentes.

---

## 19. Check-in e controle de acesso

### RF-ACC-001 — Aplicativo ou PWA de acesso

Disponibilizar ferramenta dedicada para leitura e gestão de entrada.

### RF-ACC-002 — Leitura de códigos

Ler QR Code, barcode e credenciais suportadas.

### RF-ACC-003 — Validação online e offline

Operar com ou sem internet, sincronizando dados de forma segura.

### RF-ACC-004 — Base offline protegida

Dados baixados devem ser minimizados, criptografados e expirar após o evento.

### RF-ACC-005 — Prevenção de uso duplicado

Bloquear segundo uso e informar primeira entrada, dispositivo, horário e portaria conforme permissão.

### RF-ACC-006 — Sincronização entre dispositivos

Propagar check-ins rapidamente para reduzir duplicidade em múltiplas portarias.

### RF-ACC-007 — Resolução de conflitos offline

Definir estratégia para leituras concorrentes durante falta de conexão e sinalizar conflitos.

### RF-ACC-008 — Portarias e zonas

Configurar quais ingressos podem entrar em cada portaria, área ou horário.

### RF-ACC-009 — Turnos e operadores

Vincular dispositivos e usuários a turnos, equipes e pontos de acesso.

### RF-ACC-010 — Check-in manual

Buscar participante e validar manualmente mediante permissão e justificativa.

### RF-ACC-011 — Desfazer check-in

Permitir reversão controlada com auditoria.

### RF-ACC-012 — Reentrada e saída

Registrar entrada, saída e reentrada conforme política.

### RF-ACC-013 — Validação por documento

Permitir conferência de documento, benefício, idade ou titularidade.

### RF-ACC-014 — Credenciais especiais

Validar staff, fornecedor, artista, imprensa e áreas restritas.

### RF-ACC-015 — Contagem em tempo real

Exibir entradas, saídas, ocupação, pendentes, recusas e velocidade por portaria.

### RF-ACC-016 — Motivos de recusa

Classificar ingresso inválido, cancelado, já utilizado, portaria incorreta, fora do horário, benefício não comprovado e outros.

### RF-ACC-017 — Alertas

Sinalizar padrões suspeitos, excesso de recusas, dispositivo sem sincronização e lotação.

### RF-ACC-018 — Integração com catracas

Oferecer API, SDK ou conector para catracas, torniquetes, leitores e controladoras.

### RF-ACC-019 — NFC e aproximação

Suportar validação por NFC quando infraestrutura permitir.

### RF-ACC-020 — Reconhecimento facial opcional

Somente quando legalmente permitido, necessário, consentido e acompanhado de alternativa não biométrica.

### RF-ACC-021 — Métricas de fluxo

Medir tempo médio, leituras por minuto, gargalos e distribuição de chegadas.

### RF-ACC-022 — Modo contingência

Permitir listas seguras, códigos de emergência e procedimentos controlados para incidente crítico.

---

## 20. Bilheteria e ponto de venda

### RF-POS-001 — Venda presencial

Operadores podem vender ingressos disponíveis no inventário central.

### RF-POS-002 — Pagamento presencial

Aceitar métodos configurados e integrar terminal de pagamento.

### RF-POS-003 — Impressão

Imprimir ingresso, comprovante, pulseira, etiqueta ou credencial.

### RF-POS-004 — Retirada

Registrar retirada de ingresso comprado online.

### RF-POS-005 — Caixa

Abrir, movimentar, sangrar, suprir e fechar caixa por operador e ponto.

### RF-POS-006 — Conferência

Comparar vendas, pagamentos, dinheiro, cancelamentos e diferença de caixa.

### RF-POS-007 — Modo offline controlado

Permitir contingência limitada com sincronização e prevenção de estoque negativo.

### RF-POS-008 — Venda móvel

Operar em tablets ou dispositivos móveis para reduzir filas.

### RF-POS-009 — Troca e upgrade

Realizar troca de sessão, setor ou categoria conforme regras.

### RF-POS-010 — Atendimento de exceção

Reemitir, localizar pedido, corrigir portador e encaminhar suporte.

### RF-POS-011 — Permissões por estação

Restringir operações por dispositivo, local, turno e usuário.

---

## 21. Credenciamento e eventos corporativos

### RF-CRD-001 — Inscrição

Permitir fluxo de inscrição com análise, aprovação, espera ou pagamento.

### RF-CRD-002 — Categorias de participante

Gerenciar palestrante, congressista, expositor, imprensa, staff, patrocinador e convidado.

### RF-CRD-003 — Aprovação

Aplicar critérios, revisão e comunicação de aprovação ou recusa.

### RF-CRD-004 — Credenciais

Gerar crachá, etiqueta, QR, pulseira ou cartão.

### RF-CRD-005 — Impressão sob demanda

Imprimir credencial no local após validação.

### RF-CRD-006 — Check-in por atividade

Registrar presença em palestras, salas, workshops e sessões.

### RF-CRD-007 — Capacidade por atividade

Controlar vagas e lista de espera por atividade.

### RF-CRD-008 — Certificação

Emitir certificado por presença ou carga horária.

### RF-CRD-009 — Lead retrieval

Permitir expositores capturarem leads mediante regras e consentimento.

### RF-CRD-010 — Agenda personalizada

Participante pode montar agenda e receber alertas.

---

## 22. Eventos online e híbridos

### RF-ONL-001 — Acesso autenticado

Disponibilizar acesso ao conteúdo com ingresso válido.

### RF-ONL-002 — Provedores de streaming

Integrar provedores externos ou infraestrutura própria.

### RF-ONL-003 — Links protegidos

Não expor URLs permanentes; usar tokens, sessões e expiração.

### RF-ONL-004 — Limite de dispositivos

Controlar sessões simultâneas e compartilhamento indevido.

### RF-ONL-005 — Check-in digital

Registrar acesso ao ambiente online com regras claras.

### RF-ONL-006 — Conteúdo sob demanda

Definir janela de disponibilidade, expiração e permissões.

### RF-ONL-007 — Interação

Suportar chat, perguntas, enquete e moderação por integração ou módulo.

### RF-ONL-008 — Métricas

Medir acessos, permanência, concorrência, conclusão e falhas.

### RF-ONL-009 — Híbrido

Unificar participante presencial e remoto sem duplicar registros indevidamente.

---

## 23. Adicionais, produtos, estacionamento e pacotes

### RF-ADD-001 — Adicionais

Vender estacionamento, alimentos, bebidas, merchandise, experiências, upgrades e serviços.

### RF-ADD-002 — Inventário próprio

Cada adicional pode ter estoque, período, preço, canal e elegibilidade.

### RF-ADD-003 — Associação ao ingresso

Vincular adicional a evento, sessão, ingresso, portador ou pedido.

### RF-ADD-004 — Upsell

Oferecer adicionais no checkout, pós-compra e pré-evento.

### RF-ADD-005 — Bundles

Combinar ingressos e adicionais com preço e regras próprias.

### RF-ADD-006 — Resgate

Emitir código ou credencial para retirada ou utilização.

### RF-ADD-007 — Estacionamento

Gerenciar placas, vagas, acessos, horários e validação.

### RF-ADD-008 — Estoque e logística

Registrar retirada, entrega, cancelamento e indisponibilidade.

---

## 24. Consumo cashless e operação interna

### RF-CSH-001 — Conta de consumo

Permitir associar saldo ou meio de pagamento a pulseira, cartão, QR ou conta.

### RF-CSH-002 — Recarga

Suportar recarga antecipada e no local.

### RF-CSH-003 — Pagamento rápido

Processar consumo com baixa latência e confirmação segura.

### RF-CSH-004 — Operação offline

Pontos de venda devem suportar contingência com limites de risco.

### RF-CSH-005 — Estabelecimentos e terminais

Gerenciar bares, lojas, caixas, terminais, operadores e cardápios.

### RF-CSH-006 — Produtos e estoque

Controlar catálogo, preço, disponibilidade e estoque opcional.

### RF-CSH-007 — Estorno e cancelamento

Permitir estorno controlado com auditoria.

### RF-CSH-008 — Saldo remanescente

Gerenciar devolução, expiração ou doação conforme política e legislação.

### RF-CSH-009 — Relatórios

Exibir vendas por produto, terminal, horário, operador, setor e evento.

### RF-CSH-010 — Patrocínio e mídia

Permitir campanhas, benefícios e ativações vinculadas ao consumo.

---

## 25. Atendimento e suporte

### RF-SUP-001 — Central de ajuda

Disponibilizar artigos por comprador, participante, organizador e operador.

### RF-SUP-002 — Tickets de suporte

Registrar solicitações com categoria, prioridade, SLA, responsável, anexos e histórico.

### RF-SUP-003 — Contexto automático

Associar pedido, evento, ingresso, pagamento e usuário ao atendimento.

### RF-SUP-004 — Omnichannel

Consolidar canais disponíveis em uma visão única quando integrado.

### RF-SUP-005 — Respostas prontas

Oferecer modelos versionados e personalizáveis.

### RF-SUP-006 — Ações assistidas

Operador autorizado pode reenviar ingresso, atualizar dados, iniciar reembolso, transferir caso e registrar exceção.

### RF-SUP-007 — Aprovação

Ações financeiras ou sensíveis podem exigir aprovação.

### RF-SUP-008 — SLA

Configurar prazos por contrato, canal, prioridade e proximidade do evento.

### RF-SUP-009 — Escalonamento

Escalonar automaticamente casos críticos ou vencidos.

### RF-SUP-010 — Status público

Disponibilizar página de status para incidentes relevantes.

### RF-SUP-011 — Disputas

Manter evidências e fluxo para chargeback, reclamação e mediação.

### RF-SUP-012 — Assistente inteligente

Opcionalmente sugerir respostas e classificar casos, sempre com controle humano para ações sensíveis.

---

## 26. Cancelamentos, trocas, créditos e reembolsos

### RF-REF-001 — Solicitação de cancelamento

Comprador pode solicitar conforme prazo, política e legislação.

### RF-REF-002 — Reembolso total ou parcial

Permitir reembolso de pedido, ingresso, taxa ou adicional conforme regra.

### RF-REF-003 — Método de devolução

Preferir o mesmo meio de pagamento ou alternativa permitida.

### RF-REF-004 — Taxas

Calcular valores reembolsáveis e não reembolsáveis com transparência.

### RF-REF-005 — Cancelamento em massa

Processar eventos cancelados em lotes resilientes, com acompanhamento e retentativa.

### RF-REF-006 — Adiamento

Permitir aceitar nova data, trocar sessão, receber crédito ou solicitar reembolso.

### RF-REF-007 — Crédito

Emitir crédito com valor, validade, titularidade, escopo e saldo.

### RF-REF-008 — Gift card

Emitir, vender, resgatar, transferir e cancelar gift cards conforme regras.

### RF-REF-009 — Troca

Trocar ingresso por outro evento, sessão, setor ou data, calculando diferenças.

### RF-REF-010 — Automação

Pedidos elegíveis podem ser reembolsados automaticamente; exceções seguem para análise.

### RF-REF-011 — Comunicação

Informar estado, prazo e valor de cada reembolso.

### RF-REF-012 — Reconciliação

Reembolso deve refletir em pedido, pagamento, recebíveis, repasses, comissão e relatórios.

---

## 27. Gestão financeira e repasses

### RF-FIN-001 — Livro razão

Manter ledger de dupla entrada ou modelo equivalente para todos os movimentos financeiros.

### RF-FIN-002 — Saldos

Exibir saldo previsto, disponível, reservado, bloqueado, antecipado e pago.

### RF-FIN-003 — Recebíveis

Detalhar por pedido, parcela, meio, agenda, taxa, retenção e evento.

### RF-FIN-004 — Repasse

Configurar repasse por evento, organização, contrato, calendário e condição.

### RF-FIN-005 — Conta bancária

Cadastrar e validar conta de titularidade compatível, com aprovação para alterações.

### RF-FIN-006 — Antecipação

Permitir simular, solicitar, analisar, aprovar e liquidar antecipação.

### RF-FIN-007 — Reserva de risco

Reter percentual ou valor conforme perfil, prazo e exposição.

### RF-FIN-008 — Chargeback

Debitar, bloquear ou provisionar valores e registrar ciclo da contestação.

### RF-FIN-009 — Split e beneficiários

Distribuir valores entre partes e registrar obrigações.

### RF-FIN-010 — Comissões

Provisionar e liquidar comissões apenas após condições definidas.

### RF-FIN-011 — Conciliação bancária

Importar ou integrar extratos e identificar divergências.

### RF-FIN-012 — Borderô

Gerar borderô detalhado do evento.

### RF-FIN-013 — Demonstrativos

Gerar demonstrativos de vendas, taxas, reembolsos, chargebacks, impostos, reservas e repasses.

### RF-FIN-014 — Fechamento

Permitir fechamento de período e bloquear alterações retroativas não autorizadas.

### RF-FIN-015 — Ajustes

Ajustes manuais exigem motivo, permissão e auditoria.

### RF-FIN-016 — Múltiplas moedas

Arquitetura preparada para moeda, câmbio, arredondamento e regras por país.

### RF-FIN-017 — Exportação contábil

Exportar lançamentos para ERP ou contabilidade.

### RF-FIN-018 — Previsão de caixa

Projetar entradas, saídas, reservas e obrigações.

---

## 28. Fiscal, tributário e documentos

### RF-FIS-001 — Configuração fiscal

Permitir regras por organização, município, produto e operação.

### RF-FIS-002 — Emissão de documento fiscal

Integrar provedores de nota fiscal quando aplicável.

### RF-FIS-003 — Responsabilidade fiscal

Definir se emissão cabe à plataforma, organizador ou parceiro.

### RF-FIS-004 — Cancelamento e correção

Refletir estornos, cancelamentos e ajustes nos documentos fiscais.

### RF-FIS-005 — Armazenamento

Guardar referências, XML/PDF quando aplicável e status de emissão.

### RF-FIS-006 — Relatórios tributários

Consolidar bases, taxas, comissões e repasses.

### RF-FIS-007 — Integração ERP

Enviar pedidos, clientes, serviços, recebimentos e documentos.

---

## 29. Relatórios, dashboards e analytics

### RF-ANA-001 — Indicadores em tempo real

Exibir vendas, receita, ocupação, pedidos, conversão, pagamentos e check-ins.

### RF-ANA-002 — Funil

Medir visualização, seleção, carrinho, checkout, tentativa, aprovação e emissão.

### RF-ANA-003 — Origem e campanha

Atribuir receita e conversão por canal, campanha, afiliado, cupom e dispositivo.

### RF-ANA-004 — Inventário

Exibir vendido, reservado, bloqueado, disponível, cortesia, cancelado e devolvido.

### RF-ANA-005 — Financeiro

Exibir bruto, líquido, taxas, impostos, comissões, reembolsos, chargebacks e repasses.

### RF-ANA-006 — Público

Analisar recorrência, localização, perfil autorizado, coortes, frequência e ticket médio.

### RF-ANA-007 — Acesso

Analisar chegada, pico, portaria, recusas, taxa de presença e ocupação.

### RF-ANA-008 — Comparação

Comparar eventos, sessões, períodos, canais e edições anteriores.

### RF-ANA-009 — Metas e alertas

Configurar metas e alertas de desempenho ou risco.

### RF-ANA-010 — Relatórios personalizados

Selecionar dimensões, métricas, filtros, agrupamentos e colunas.

### RF-ANA-011 — Agendamento

Enviar relatórios periodicamente para destinatários autorizados.

### RF-ANA-012 — Exportação

Exportar CSV, XLSX, PDF ou via API, conforme permissão.

### RF-ANA-013 — Dados quase em tempo real

Indicadores operacionais devem possuir baixa defasagem e informar horário da atualização.

### RF-ANA-014 — Recomendações

Sugerir ações como abrir lote, reforçar portaria, recuperar pagamentos ou criar sessão, com explicação da base usada.

### RF-ANA-015 — Data warehouse

Disponibilizar integração segura para BI e warehouse de clientes enterprise.

---

## 30. Antifraude, anti-bot e gestão de risco

### RF-RSK-001 — Motor de regras

Avaliar cadastro, login, compra, transferência, revenda, acesso, saque e alteração bancária.

### RF-RSK-002 — Sinais de risco

Considerar velocidade, dispositivo, IP, conta, cartão tokenizado, documento, comportamento, geografia e histórico.

### RF-RSK-003 — Decisões

Permitir aprovar, negar, desafiar, limitar, reter ou enviar para revisão.

### RF-RSK-004 — Revisão manual

Oferecer fila com evidências, prioridade e decisão auditável.

### RF-RSK-005 — Anti-bot

Aplicar rate limiting, reputação, desafios adaptativos, detecção de automação e limites por identidade.

### RF-RSK-006 — Fila virtual

Controlar entrada em vendas de alta demanda, distribuir posições de forma justa e proteger o checkout.

### RF-RSK-007 — Pré-cadastro ou registro de interesse

Permitir registro prévio para medir demanda e limitar fraude em pré-vendas.

### RF-RSK-008 — Proteção de inventário

Detectar reservas abusivas, carrinhos repetidos, múltiplas contas e tentativa de esgotamento artificial.

### RF-RSK-009 — QR dinâmico

Reduzir cópia e revenda externa não autorizada em eventos configurados.

### RF-RSK-010 — Transferência segura

Exigir conta verificada, limites e período de bloqueio quando necessário.

### RF-RSK-011 — Revenda verificada

Cancelar credencial anterior, emitir nova e garantir autenticidade.

### RF-RSK-012 — Ações administrativas anômalas

Alertar sobre cortesias, descontos, reembolsos, exportações e alterações fora do padrão.

### RF-RSK-013 — Listas

Manter listas de confiança, restrição e observação com governança.

### RF-RSK-014 — Chargebacks

Organizar evidências, prazos, contestação e análise de causa.

### RF-RSK-015 — Limites adaptativos

Aplicar limites por risco, evento e demanda.

---

## 31. Administração global da plataforma

### RF-ADM-001 — Gestão de organizações

Consultar, aprovar, restringir, suspender e encerrar organizações.

### RF-ADM-002 — Configurações comerciais

Gerenciar planos, taxas, contratos, limites e módulos.

### RF-ADM-003 — Gestão de eventos

Revisar, moderar, suspender e acompanhar eventos.

### RF-ADM-004 — Visão operacional

Consultar saúde de pagamentos, mensageria, filas, webhooks e integrações.

### RF-ADM-005 — Suporte assistido

Acessar contexto de cliente com permissão, justificativa e auditoria.

### RF-ADM-006 — Impersonação controlada

Quando indispensável, permitir acesso assistido temporário, explícito e auditado, sem revelar segredos.

### RF-ADM-007 — Feature flags

Ativar recursos por ambiente, organização, plano, percentual ou coorte.

### RF-ADM-008 — Configuração global

Gerenciar catálogos, categorias, países, moedas, provedores e políticas.

### RF-ADM-009 — Gestão de incidentes

Registrar incidente, impacto, eventos afetados, comunicação e resolução.

### RF-ADM-010 — Auditoria global

Pesquisar trilhas administrativas, financeiras e de segurança.

---

## 32. API, webhooks, SDKs e integrações

### RF-API-001 — API pública versionada

Disponibilizar API REST, GraphQL ou ambas, com versionamento e documentação.

### RF-API-002 — Autenticação

Suportar OAuth 2.0/OIDC, chaves de API e credenciais de serviço conforme caso.

### RF-API-003 — Escopos

Credenciais devem possuir escopos granulares por recurso e ação.

### RF-API-004 — Webhooks

Publicar eventos de domínio como:

- evento criado ou alterado;
- pedido criado;
- pagamento atualizado;
- ingresso emitido, transferido ou cancelado;
- check-in realizado;
- reembolso atualizado;
- repasse realizado.

### RF-API-005 — Entrega confiável

Webhooks devem usar assinatura, tentativas, backoff, idempotência e histórico.

### RF-API-006 — Reprocessamento

Cliente pode reenviar eventos dentro de período permitido.

### RF-API-007 — Sandbox

Oferecer ambiente de testes com credenciais e dados isolados.

### RF-API-008 — SDKs

Fornecer SDKs para linguagens prioritárias e controle de acesso.

### RF-API-009 — Importação e exportação

Permitir operações em lote assíncronas.

### RF-API-010 — Rate limits

Aplicar limites por cliente, rota, plano e risco, com cabeçalhos informativos.

### RF-API-011 — Portal do desenvolvedor

Disponibilizar documentação, exemplos, logs, segredos, webhooks e métricas.

### RF-API-012 — Integrações nativas

Priorizar:

- gateways e adquirentes;
- antifraude;
- CRM e automação;
- ERP e contabilidade;
- emissão fiscal;
- carteiras digitais;
- e-mail, SMS e push;
- analytics e pixels;
- streaming;
- catracas e leitores;
- calendários;
- mapas;
- atendimento;
- BI e data warehouse;
- cashless e terminais.

### RF-API-013 — Marketplace de integrações

Permitir instalação, autorização, configuração, revisão e revogação de aplicativos parceiros.

---

## 33. Migração e interoperabilidade

### RF-MIG-001 — Importação de eventos

Importar eventos, lotes, assentos, participantes, pedidos e ingressos de sistemas externos.

### RF-MIG-002 — Mapeamento

Permitir mapear campos, valores e identificadores.

### RF-MIG-003 — Validação prévia

Executar simulação antes da importação definitiva.

### RF-MIG-004 — Idempotência

Reimportação não deve duplicar registros.

### RF-MIG-005 — Relatório de erros

Informar linha, campo, motivo e ação recomendada.

### RF-MIG-006 — Rollback lógico

Permitir desfazer importações quando seguro.

### RF-MIG-007 — Exportação completa

Organizadores devem conseguir exportar seus dados conforme contrato, permissão e legislação.

---

## 34. Internacionalização e localização

### RF-I18N-001 — Idiomas

A arquitetura deve suportar múltiplos idiomas e conteúdo traduzível.

### RF-I18N-002 — Fuso horário

Datas devem ser armazenadas de forma inequívoca e exibidas no fuso correto.

### RF-I18N-003 — Moeda

Formatar e calcular moedas sem assumir duas casas decimais universalmente.

### RF-I18N-004 — Endereços e documentos

Permitir formatos por país.

### RF-I18N-005 — Regras locais

Separar políticas fiscais, pagamento, privacidade e reembolso por jurisdição.

---

## 35. Acessibilidade funcional

### RNF-ACC-001 — Conformidade

As interfaces públicas e administrativas devem buscar conformidade WCAG 2.2 nível AA.

### RNF-ACC-002 — Navegação

Todas as funções essenciais devem ser utilizáveis por teclado.

### RNF-ACC-003 — Leitores de tela

Campos, estados, tabelas, mapas e mensagens devem possuir semântica acessível.

### RNF-ACC-004 — Estados não dependentes de cor

Erro, sucesso, bloqueio e disponibilidade devem possuir texto ou símbolo alternativo.

### RNF-ACC-005 — Tempo

Temporizadores devem avisar, permitir extensão quando apropriado e não surpreender usuários.

### RNF-ACC-006 — Conteúdo e atendimento

Organizador deve informar acessibilidade física e canais para necessidades específicas.

### RNF-ACC-007 — Assentos acessíveis

Mapa deve tratar assentos PCD e acompanhantes com elegibilidade e regras claras.

---

## 36. Privacidade e proteção de dados

### RNF-PRV-001 — LGPD

O sistema deve suportar obrigações de controlador e operador conforme o papel de cada parte.

### RNF-PRV-002 — Minimização

Coletar apenas dados necessários às finalidades declaradas.

### RNF-PRV-003 — Base legal e finalidade

Registrar base legal, finalidade, origem e compartilhamentos relevantes.

### RNF-PRV-004 — Consentimento

Consentimentos devem ser específicos, revogáveis e versionados quando usados.

### RNF-PRV-005 — Direitos do titular

Disponibilizar processo para acesso, confirmação, correção, portabilidade, anonimização, oposição e eliminação quando aplicável.

### RNF-PRV-006 — Retenção

Definir prazos por categoria, obrigação e contrato.

### RNF-PRV-007 — Exclusão e anonimização

Executar workflows seguros sem apagar registros que devam ser mantidos legalmente.

### RNF-PRV-008 — Compartilhamento

Registrar destinatários e limitar dados compartilhados com organizadores e parceiros.

### RNF-PRV-009 — Dados sensíveis e biometria

Exigir controles reforçados, avaliação de necessidade e alternativa quando aplicável.

### RNF-PRV-010 — Privacidade por padrão

Novos recursos devem adotar configurações restritivas por padrão.

### RNF-PRV-011 — Avaliação de impacto

Recursos de alto risco devem passar por avaliação de impacto de privacidade.

### RNF-PRV-012 — Incidentes

Manter processo de detecção, contenção, avaliação, registro e comunicação.

---

## 37. Segurança da informação

### RNF-SEC-001 — Programa de segurança

Manter políticas, responsáveis, riscos, controles, testes e melhoria contínua.

### RNF-SEC-002 — Segurança por arquitetura

Aplicar menor privilégio, defesa em profundidade, isolamento e confiança zero quando adequado.

### RNF-SEC-003 — Criptografia em trânsito

Usar TLS moderno em comunicações externas e internas sensíveis.

### RNF-SEC-004 — Criptografia em repouso

Criptografar bancos, backups, objetos e segredos conforme criticidade.

### RNF-SEC-005 — Segredos

Armazenar chaves e credenciais em gerenciador próprio, com rotação e acesso auditado.

### RNF-SEC-006 — Senhas

Usar algoritmo de hash resistente e política baseada em risco, sem armazenar senha reversível.

### RNF-SEC-007 — MFA administrativa

Exigir MFA para perfis privilegiados.

### RNF-SEC-008 — PCI DSS

Operações com cartão devem seguir a versão aplicável do PCI DSS e reduzir o escopo por tokenização e terceirização segura.

### RNF-SEC-009 — Desenvolvimento seguro

Adotar revisão, análise estática, análise de dependências, testes e gestão de vulnerabilidades.

### RNF-SEC-010 — OWASP

Proteger contra categorias relevantes do OWASP Top 10 para web, API e mobile.

### RNF-SEC-011 — WAF e proteção DDoS

Utilizar controles para ataques de camada de aplicação e volumétricos.

### RNF-SEC-012 — Rate limiting

Aplicar limites em login, checkout, emissão, consulta, API e ações sensíveis.

### RNF-SEC-013 — Auditoria

Logs de segurança devem ser imutáveis ou protegidos contra alteração.

### RNF-SEC-014 — Detecção

Monitorar autenticação, privilégios, dados, fraude, exfiltração e comportamento anômalo.

### RNF-SEC-015 — Resposta a incidentes

Manter playbooks, contatos, severidade, comunicação e pós-incidente.

### RNF-SEC-016 — Pentest

Realizar testes periódicos e antes de mudanças críticas.

### RNF-SEC-017 — Gestão de fornecedores

Avaliar riscos de provedores que tratam dados ou funções críticas.

### RNF-SEC-018 — Segurança mobile

Proteger armazenamento local, sessão, integridade e comunicações do app de check-in.

### RNF-SEC-019 — Acesso de suporte

Acesso de equipe interna a dados de clientes deve ser mínimo, temporário e auditado.

### RNF-SEC-020 — Backups protegidos

Backups devem ser criptografados, testados e isolados contra exclusão maliciosa.

---

## 38. Desempenho e capacidade

### RNF-PER-001 — Metas de latência

Definir SLOs por jornada. Como referência inicial:

- páginas públicas cacheáveis: p95 menor que 1,5 segundo no backend;
- buscas: p95 menor que 1 segundo em condições normais;
- operações administrativas comuns: p95 menor que 2 segundos;
- validação online de ingresso: p95 menor que 500 ms;
- atualização operacional em tempo real: atraso típico menor que 5 segundos;
- criação de reserva atômica: p95 menor que 1 segundo.

As metas finais devem ser validadas por carga real.

### RNF-PER-002 — Checkout

O checkout deve permanecer funcional sob picos previstos e degradação parcial de dependências.

### RNF-PER-003 — Escalabilidade horizontal

Serviços críticos devem escalar horizontalmente.

### RNF-PER-004 — Processamento assíncrono

E-mails, PDFs, exportações, webhooks, reembolsos em massa e relatórios pesados devem ser processados em filas.

### RNF-PER-005 — Backpressure

Consumidores devem suportar controle de pressão, retentativas e dead-letter queue.

### RNF-PER-006 — Cache

Usar cache com invalidação coerente, sem comprometer inventário ou autorização.

### RNF-PER-007 — Testes de carga

Realizar testes de carga, pico, estresse, soak e falha antes de grandes vendas.

### RNF-PER-008 — Planejamento por evento

Eventos de alta demanda devem possuir previsão, capacidade reservada e sala de operação.

### RNF-PER-009 — Fila virtual

Quando demanda ultrapassar capacidade segura, controlar acesso em vez de deixar o sistema falhar.

---

## 39. Disponibilidade, resiliência e continuidade

### RNF-AVL-001 — SLO de disponibilidade

Definir SLOs por serviço. Checkout, pagamento e acesso devem possuir metas superiores aos módulos não críticos.

### RNF-AVL-002 — Eliminação de ponto único

Componentes críticos não devem depender de uma única instância ou zona quando o risco justificar.

### RNF-AVL-003 — Degradação graciosa

Falhas de recomendação, analytics ou marketing não podem derrubar compra e acesso.

### RNF-AVL-004 — Circuit breaker e timeout

Integrações externas devem usar timeout, circuit breaker, retentativa controlada e fallback.

### RNF-AVL-005 — Idempotência

Operações financeiras, emissão, reservas e webhooks devem ser idempotentes.

### RNF-AVL-006 — Recuperação de desastre

Definir RTO e RPO por domínio e testar recuperação.

### RNF-AVL-007 — Multi-região ou contingência

Planejar estratégia para eventos críticos conforme escala e risco.

### RNF-AVL-008 — Check-in offline

Controle de acesso deve continuar durante indisponibilidade temporária da internet ou backend.

### RNF-AVL-009 — Status e comunicação

Incidentes devem possuir comunicação interna, para organizadores e, quando necessário, pública.

### RNF-AVL-010 — Chaos testing

Testar falhas de dependências e infraestrutura em ambientes controlados.

---

## 40. Consistência, concorrência e integridade

### RNF-DAT-001 — Venda sem overselling

Inventário deve usar concorrência segura, transações, locks ou modelo equivalente.

### RNF-DAT-002 — Fonte de verdade

Definir sistema de registro para pedido, pagamento, ingresso, inventário e financeiro.

### RNF-DAT-003 — Consistência eventual explícita

Quando houver consistência eventual, informar limites e estados intermediários.

### RNF-DAT-004 — Eventos de domínio

Eventos devem possuir identificadores, versões e ordenação suficiente para reprocessamento.

### RNF-DAT-005 — Reconciliação

Executar jobs de reconciliação para corrigir divergências detectáveis.

### RNF-DAT-006 — Migrações

Migrações de banco devem ser reversíveis quando possível, compatíveis com deploy sem parada e testadas em escala.

### RNF-DAT-007 — Histórico

Dados financeiros, auditoria e estados relevantes devem preservar histórico.

---

## 41. Observabilidade e operação

### RNF-OBS-001 — Logs estruturados

Registrar correlação, serviço, organização, evento, pedido e resultado sem expor dados sensíveis.

### RNF-OBS-002 — Métricas

Coletar métricas técnicas e de negócio.

### RNF-OBS-003 — Tracing distribuído

Rastrear jornadas entre serviços e integrações.

### RNF-OBS-004 — Correlação

Cada requisição e operação assíncrona deve possuir correlation ID.

### RNF-OBS-005 — Alertas acionáveis

Alertas devem indicar impacto, contexto e procedimento.

### RNF-OBS-006 — SLI/SLO

Acompanhar disponibilidade, latência, erro, saturação, fila, aprovação e validação.

### RNF-OBS-007 — Painel de venda crítica

Durante vendas de alta demanda, exibir fila, taxa de entrada, reservas, pagamentos, erros, estoque e fraude.

### RNF-OBS-008 — Painel de evento

Durante o evento, exibir acesso, dispositivos, sincronização, ocupação e incidentes.

### RNF-OBS-009 — Auditoria de integração

Manter requisições, respostas mascaradas, tentativas e estado de webhooks.

### RNF-OBS-010 — Runbooks

Manter procedimentos para falha de pagamento, QR, check-in, fila, mensageria, banco e infraestrutura.

---

## 42. Manutenibilidade e engenharia

### RNF-ENG-001 — Arquitetura modular

Separar domínios de identidade, catálogo, inventário, checkout, pagamentos, ingressos, acesso, financeiro e comunicação.

### RNF-ENG-002 — Contratos claros

APIs e eventos devem possuir schemas versionados.

### RNF-ENG-003 — Compatibilidade retroativa

Mudanças não devem quebrar consumidores sem estratégia de migração.

### RNF-ENG-004 — Feature flags

Novas funcionalidades devem poder ser ativadas gradualmente.

### RNF-ENG-005 — CI/CD

Automatizar build, testes, análise, migração e deploy.

### RNF-ENG-006 — Deploy seguro

Utilizar rolling, canary ou blue-green conforme criticidade.

### RNF-ENG-007 — Ambientes

Separar desenvolvimento, teste, homologação, sandbox e produção.

### RNF-ENG-008 — Infraestrutura como código

Versionar infraestrutura e configurações não secretas.

### RNF-ENG-009 — Documentação

Manter documentação de domínio, arquitetura, APIs, operação e decisões.

### RNF-ENG-010 — Dívida técnica

Registrar, priorizar e revisar dívida técnica.

---

## 43. Qualidade e testes

### RNF-QA-001 — Pirâmide de testes

Manter testes unitários, integração, contrato, componente e ponta a ponta.

### RNF-QA-002 — Testes obrigatórios por mutação

Validar sucesso, erro de validação, autorização, tenant, inexistência, concorrência, idempotência e auditoria.

### RNF-QA-003 — Pagamentos

Cobrir aprovação, recusa, timeout, callback duplicado, callback fora de ordem, estorno e chargeback.

### RNF-QA-004 — Inventário

Cobrir concorrência, expiração, hold, mapa, lote e limite de compra.

### RNF-QA-005 — Check-in

Cobrir online, offline, duplicidade, conflito, sincronização e reentrada.

### RNF-QA-006 — Segurança

Executar SAST, DAST, análise de dependências, secrets scanning e testes de autorização.

### RNF-QA-007 — Acessibilidade

Combinar testes automatizados e manuais com tecnologia assistiva.

### RNF-QA-008 — Performance

Manter cenários representativos e limites de regressão.

### RNF-QA-009 — Resiliência

Testar indisponibilidade e lentidão de provedores.

### RNF-QA-010 — Dados de teste

Usar factories e dados sintéticos, sem copiar dados pessoais de produção sem proteção.

### RNF-QA-011 — Testes em produção controlados

Usar smoke tests, synthetic monitoring e canários sem afetar clientes.

### RNF-QA-012 — Critérios de aceite

Todo requisito priorizado deve possuir critérios verificáveis.

---

## 44. Requisitos específicos para alta demanda

### RF-HDP-001 — Sala de espera

Usuários entram em sala de espera antes da abertura configurada.

### RF-HDP-002 — Distribuição de posição

Definir regra justa de posição para usuários presentes antes da abertura.

### RF-HDP-003 — Controle de admissão

A plataforma libera usuários na taxa segura para catálogo, mapa e checkout.

### RF-HDP-004 — Token de fila

Posição e acesso devem usar token assinado e resistente a manipulação.

### RF-HDP-005 — Comunicação em tempo real

Informar posição aproximada, andamento, disponibilidade e incidentes.

### RF-HDP-006 — Proteção contra múltiplas sessões

Aplicar política por conta, dispositivo e identidade.

### RF-HDP-007 — Prioridades legítimas

Suportar pré-vendas e grupos elegíveis sem comprometer isolamento.

### RF-HDP-008 — Escalonamento automático

Escalar serviços e filas antes do início.

### RF-HDP-009 — Freeze de mudanças

Restringir alterações de risco durante janela crítica.

### RF-HDP-010 — Plano operacional

Gerar checklist, responsáveis, contatos, dashboards e critérios de abortar ou pausar.

### RF-HDP-011 — Pausa de venda

Permitir pausar admissão ou venda sem perder estado válido.

### RF-HDP-012 — Pós-venda

Produzir relatório de demanda, conversão, falhas, fraude e capacidade.

---

## 45. Regras de negócio essenciais

### RB-001 — Reserva não é venda

Inventário reservado só se torna vendido após condição de confirmação definida.

### RB-002 — Pagamento aprovado deve ser reconciliado

Se houver pagamento aprovado sem pedido confirmado, uma rotina deve identificar e corrigir ou encaminhar exceção.

### RB-003 — Pedido confirmado deve possuir cobertura financeira

Pedido pago deve estar ligado a pagamento ou saldo válido, salvo cortesia ou faturamento autorizado.

### RB-004 — Ingresso cancelado não pode validar

Toda credencial anterior deve ser invalidada.

### RB-005 — Transferência gera nova cadeia de custódia

Deve existir histórico de proprietário e portador sem permitir uso da credencial antiga.

### RB-006 — Revenda oficial reemite o ingresso

A venda secundária deve cancelar o ingresso do vendedor e emitir outro ao comprador.

### RB-007 — Check-in é idempotente

Repetir a mesma requisição não deve multiplicar entradas.

### RB-008 — Capacidade não pode ser excedida

Soma de vendido, reservado válido, bloqueios relevantes e acessos deve respeitar regras de capacidade.

### RB-009 — Reembolso afeta toda a cadeia financeira

Pedido, ingresso, pagamento, recebível, comissão, repasse e fiscal devem permanecer coerentes.

### RB-010 — Alterações críticas são auditadas

Nenhuma ação financeira, de acesso, inventário ou permissão privilegiada pode ocorrer sem registro.

### RB-011 — Taxas são transparentes

O comprador deve conhecer o preço total antes de concluir.

### RB-012 — Dados são compartilhados por finalidade

Organizadores recebem apenas dados necessários e permitidos.

### RB-013 — Offline possui janela e limites

Credenciais e dados offline devem expirar e sincronizar assim que possível.

### RB-014 — Conta bancária alterada não recebe imediatamente sem controle

Aplicar validação, carência ou aprovação para mitigar fraude.

### RB-015 — Regras vigentes são versionadas

Pedido e ingresso devem referenciar versões de preço, política e termos aplicados no momento da compra.

---

## 46. Modelo de dados conceitual mínimo

O domínio deve contemplar, no mínimo, as entidades:

- User;
- UserIdentity;
- Session;
- Consent;
- Organization;
- OrganizationMember;
- Role;
- Permission;
- Venue;
- VenueArea;
- Gate;
- SeatMap;
- Seat;
- Event;
- EventSession;
- Activity;
- TicketType;
- Batch/Lot;
- InventoryPool;
- InventoryAllocation;
- Hold;
- PriceRule;
- FeeRule;
- EligibilityRule;
- Cart;
- Reservation;
- Order;
- OrderItem;
- Attendee;
- Form;
- FormField;
- FormResponse;
- Payment;
- PaymentAttempt;
- Refund;
- Dispute;
- Receivable;
- LedgerAccount;
- LedgerEntry;
- Settlement;
- Payout;
- Ticket;
- TicketCredential;
- TicketTransfer;
- ResaleListing;
- CheckIn;
- AccessDevice;
- AccessZone;
- Coupon;
- Promotion;
- Invite;
- Affiliate;
- Attribution;
- Commission;
- AddOn;
- Fulfillment;
- Campaign;
- Audience;
- Message;
- SupportCase;
- AuditLog;
- RiskAssessment;
- RiskRule;
- WebhookEndpoint;
- WebhookDelivery;
- Integration;
- ApiCredential;
- FiscalDocument;
- ImportJob;
- ExportJob;
- FeatureFlag;
- Incident.

Todas as entidades transacionais devem possuir identificadores estáveis, timestamps, versão ou controle de concorrência quando necessário e vínculo de tenant.

---

## 47. Eventos de domínio recomendados

- OrganizationCreated;
- OrganizationApproved;
- BankAccountChanged;
- EventCreated;
- EventPublished;
- EventUpdated;
- EventPostponed;
- EventCancelled;
- SalesOpened;
- SalesPaused;
- LotChanged;
- InventoryHeld;
- InventoryReleased;
- CartAbandoned;
- OrderCreated;
- PaymentPending;
- PaymentApproved;
- PaymentFailed;
- OrderConfirmed;
- TicketIssued;
- TicketTransferred;
- TicketListedForResale;
- TicketResold;
- TicketCancelled;
- CheckInAccepted;
- CheckInRejected;
- RefundRequested;
- RefundCompleted;
- ChargebackOpened;
- PayoutScheduled;
- PayoutCompleted;
- WaitlistJoined;
- WaitlistOfferCreated;
- MessageSent;
- IntegrationFailed;
- RiskAlertCreated;
- IncidentOpened;
- IncidentResolved.

---

## 48. Estados e máquinas de estado

Devem existir máquinas de estado explícitas para:

- organização;
- evento;
- sessão;
- lote;
- reserva;
- pedido;
- pagamento;
- reembolso;
- ingresso;
- transferência;
- revenda;
- check-in;
- recebível;
- repasse;
- suporte;
- importação;
- webhook.

Transições inválidas devem ser rejeitadas, auditadas e testadas.

---

## 49. Critérios mínimos de lançamento por fase

### Fase 1 — Núcleo comercial e operacional

Obrigatório:

- SaaS multiempresa;
- autenticação e RBAC;
- onboarding de organizador;
- eventos presenciais simples;
- tipos de ingresso e lotes;
- estoque geral;
- checkout responsivo;
- Pix e cartão;
- pedidos e ingressos QR;
- e-mail transacional;
- check-in online e offline;
- vendas e entradas em tempo real;
- cupons;
- cancelamento e reembolso básico;
- financeiro e repasse básico;
- auditoria;
- LGPD e segurança essenciais;
- API/webhooks básicos;
- monitoramento e suporte.

### Fase 2 — Competitividade nacional

Adicionar:

- sessões e recorrência;
- mapas e assentos marcados;
- virada automática de lote;
- formulários por ingresso;
- bilheteria/POS;
- promotores e comissões;
- CRM e automações;
- lista de espera;
- carteiras digitais;
- transferência;
- adicionais e estacionamento;
- antecipação;
- conciliação avançada;
- fiscal;
- integrações nativas;
- dashboards avançados;
- fila virtual inicial;
- antifraude avançado.

### Fase 3 — Superação de concorrentes

Adicionar:

- revenda oficial verificada;
- QR rotativo e NFC;
- preço dinâmico;
- fila virtual enterprise;
- múltiplos gateways com roteamento;
- cashless;
- credenciamento corporativo completo;
- eventos híbridos e on-demand;
- marketplace personalizado;
- recomendação e inteligência preditiva;
- programa de fidelidade;
- white-label enterprise;
- API e marketplace de apps;
- BI/warehouse;
- operação internacional;
- SLOs enterprise e contingência multi-região.

---

## 50. Priorização MoSCoW consolidada

### Must have

- multi-tenant e RBAC;
- criação de evento;
- inventário e lotes;
- reserva atômica;
- checkout;
- Pix e cartão;
- pedidos;
- emissão de ingresso;
- check-in online/offline;
- financeiro e repasse;
- reembolso;
- auditoria;
- segurança;
- privacidade;
- observabilidade;
- suporte;
- API e webhooks mínimos.

### Should have

- assentos;
- bilheteria;
- formulários;
- transferência;
- carteiras digitais;
- afiliados;
- CRM;
- lista de espera;
- adicionais;
- antecipação;
- fila virtual;
- antifraude avançado;
- fiscal e ERP.

### Could have

- revenda;
- cashless;
- NFC;
- preço dinâmico;
- fidelidade;
- reconhecimento facial opcional;
- recomendações por IA;
- marketplace de integrações;
- internacionalização completa.

### Won't have inicialmente

- infraestrutura própria de adquirência;
- hardware proprietário obrigatório;
- biometria como único meio de acesso;
- dependência exclusiva de aplicativo nativo para ingresso;
- arquitetura global multi-região antes de necessidade comprovada.

---

## 51. KPIs de produto e operação

### Aquisição e descoberta

- visitantes únicos;
- CTR por canal;
- custo por visita;
- favoritos e alertas;
- conversão de descoberta para página do evento.

### Conversão

- conversão por etapa;
- abandono de carrinho;
- abandono de pagamento;
- taxa de aprovação por meio e provedor;
- tempo para concluir compra;
- receita por visitante;
- ticket médio;
- uso de cupom;
- upsell por pedido.

### Inventário

- sell-through;
- velocidade de venda;
- ocupação;
- estoque preso em hold;
- assentos órfãos;
- viradas de lote;
- taxa de lista de espera convertida.

### Operação

- leituras por minuto;
- tempo de entrada;
- taxa de duplicidade;
- taxa de recusa;
- dispositivos offline;
- presença real versus vendida;
- ocupação por área.

### Financeiro

- receita bruta e líquida;
- take rate;
- custo de processamento;
- chargeback;
- reembolso;
- prazo médio de repasse;
- divergência de conciliação;
- antecipação;
- inadimplência SaaS.

### Relacionamento

- recompra;
- frequência;
- NPS;
- CSAT;
- tempo de primeira resposta;
- resolução no primeiro contato;
- descadastro;
- conversão de campanha.

### Plataforma

- disponibilidade;
- latência;
- taxa de erro;
- fila acumulada;
- falha de webhook;
- incidentes;
- tempo de recuperação;
- regressões;
- vulnerabilidades abertas.

---

## 52. Requisitos de aceite para um evento de grande porte

Antes de liberar uma venda crítica, deve ser comprovado:

1. inventário e mapa validados;
2. limites e elegibilidades testados;
3. contratos e taxas aprovados;
4. contas e repasses verificados;
5. checkout testado em todos os meios;
6. fila virtual configurada;
7. carga simulada acima do pico previsto;
8. antifraude e anti-bot calibrados;
9. comunicação transacional validada;
10. dashboards e alertas ativos;
11. runbook e contatos definidos;
12. rollback ou pausa de venda testados;
13. suporte escalado;
14. dispositivos e operação de acesso testados;
15. contingência offline preparada;
16. plano de incidentes aprovado;
17. monitoramento de provedores ativo;
18. reconciliação automática validada;
19. proteção de dados e permissões revisadas;
20. relatório pós-operação programado.

---

## 53. Critérios para considerar o produto competitivo

O PegaTicket poderá ser considerado competitivo quando atender simultaneamente aos seguintes critérios:

- organizador consegue criar, publicar, vender, operar e receber sem depender de suporte manual;
- comprador conclui a compra com poucos atritos e total transparência;
- inventário não sofre venda duplicada mesmo em concorrência elevada;
- pagamentos são reconciliados automaticamente;
- ingressos são seguros, acessíveis e administráveis após a compra;
- transferência e reembolso possuem autosserviço controlado;
- check-in continua operando com internet instável;
- equipe acompanha vendas e entradas em tempo real;
- financeiro explica cada centavo entre compra e repasse;
- sistema suporta campanhas, afiliados e automações mensuráveis;
- integrações possuem API e webhooks confiáveis;
- segurança, LGPD e auditoria fazem parte da arquitetura;
- grandes vendas usam fila virtual e proteção anti-bot;
- incidentes podem ser detectados, contidos e comunicados rapidamente;
- módulos avançados podem ser ativados sem reconstrução do núcleo.

---

## 54. Fontes de benchmark utilizadas

A ampliação deste documento considerou recursos públicos apresentados por plataformas e tecnologias do mercado, incluindo:

- Sympla: criação e gestão, check-in, formulários personalizados, dashboards, virada automática de lote, QR Code e repasses;
- Eventbrite: registro, marketing, cupons, lista de espera, assentos marcados e aplicativo de check-in;
- Ticketmaster: mapas interativos, checkout de alta escala, fila virtual, transferência, revenda verificada, adicionais e segurança de ingressos móveis;
- Ingresse e soluções associadas: ticketing, operação de eventos e consumo cashless;
- Google Wallet: passes de evento, QR/barcode, NFC, atualização e notificações;
- práticas de segurança, privacidade, acessibilidade e engenharia aplicáveis a plataformas SaaS transacionais.

As funcionalidades de concorrentes mudam com o tempo. O backlog deve ser revisado periodicamente com pesquisa de mercado, entrevistas com organizadores, compradores, operadores de acesso e parceiros financeiros.

---

## 55. Conclusão

O sistema completo deve ser desenvolvido como uma plataforma transacional e operacional de missão crítica, não como um simples catálogo com checkout.

O núcleo mais importante é a consistência entre:

> inventário → reserva → pagamento → pedido → ingresso → acesso → financeiro → repasse.

Sobre esse núcleo devem ser construídos os diferenciais de mercado:

> descoberta → marketing → personalização → transferência → revenda → adicionais → cashless → inteligência.

A prioridade técnica deve ser garantir segurança, disponibilidade, integridade financeira e operação offline antes de funcionalidades promocionais. A prioridade de produto deve ser eliminar atritos tanto para o organizador quanto para o comprador, oferecendo autosserviço, transparência e dados em tempo real.

Este documento deve servir como base para:

- visão de produto;
- roadmap;
- épicos e histórias;
- modelagem de domínio;
- arquitetura de software;
- contratos de API;
- critérios de aceite;
- plano de testes;
- plano de segurança;
- operação e suporte;
- homologação de eventos críticos.
