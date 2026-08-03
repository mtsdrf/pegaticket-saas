# Especificação Funcional — Plataforma SaaS de Venda de Ingressos para Clubes

**Versão:** 1.0  
**Status:** Documento base para produto, design, desenvolvimento e QA  
**Escopo:** Funcionalidades específicas do domínio de ingressos, inscrições, mesas, estacionamento, pagamentos, emissão e controle de acesso  
**Fora do escopo:** Autenticação, usuários, grupos, permissões e demais recursos administrativos genéricos já existentes na base SaaS

---

## 1. Visão do produto

A plataforma será uma solução SaaS white-label para clubes, associações e espaços de eventos venderem:

- ingressos;
- inscrições;
- mesas completas;
- lugares individuais em mesas;
- assentos numerados;
- estacionamento;
- produtos e serviços adicionais.

Cada clube terá sua própria loja pública, identidade visual, catálogo de eventos, conta de pagamento e operação independente.

### 1.1 Proposta de valor

A plataforma deverá oferecer:

1. página pública moderna e personalizável;
2. seleção visual de mesas e assentos diretamente no mapa;
3. checkout guiado e fácil de entender;
4. recebimento direto na conta PagBank do clube;
5. meios de pagamento configuráveis por clube e evento;
6. emissão de ingressos digitais com QR Code;
7. controle de entrada e check-in;
8. relatórios claros para tomada de decisão;
9. suporte a eventos públicos, privados e exclusivos para associados;
10. operação web responsiva, sem obrigatoriedade de aplicativo.

---

## 2. Premissas do projeto

- A plataforma existente já possui autenticação, usuários, permissões e estrutura multi-tenant.
- Cada tenant representará um clube ou organização.
- Cada clube poderá conectar sua própria conta PagBank.
- O valor das vendas deverá ser recebido diretamente pelo clube.
- A primeira versão não emitirá nota fiscal.
- Cancelamentos e estornos financeiros serão realizados externamente pelo clube no PagBank.
- O sistema deverá permitir registrar o estorno externo para manter ingressos, lugares e relatórios consistentes.
- O prazo padrão para finalização da compra será de 15 minutos.
- O prazo deverá ser configurável por clube e, opcionalmente, por evento.
- O sistema deverá ser preparado para operar múltiplos eventos simultaneamente.

---

## 3. Benchmarking resumido

### 3.1 PagTickets

Referência principal informada pelo cliente.

Características observadas no segmento:

- gestão de eventos;
- venda de ingressos e inscrições;
- configuração de setores, mesas e assentos;
- escolha de lugares;
- controle de estoque e participantes;
- checkout e pagamento online.

Oportunidades de melhoria identificadas pelo cliente:

- home pública mais atraente;
- checkout com mais instruções;
- seleção direta da mesa no mapa;
- relatórios mais claros e gerenciais.

### 3.2 Sympla

Referências relevantes:

- criação de eventos gratuitos e pagos;
- lotes e tipos de ingresso;
- ingressos com lugar marcado;
- mapa de assentos;
- check-in por QR Code;
- relatórios de vendas e conversão;
- controle por portaria e perfil de operador.

### 3.3 Ingresse e Total Acesso

Referências relevantes:

- carteira de ingressos;
- transferência de titularidade;
- segurança de acesso;
- QR Code;
- acompanhamento de movimentações;
- operação de grandes eventos.

Essas funcionalidades não são obrigatórias no MVP, mas a arquitetura não deve impedir sua implementação futura.

### 3.4 Ticket Sports

Referências relevantes para eventos de inscrição:

- questionários personalizados;
- produtos adicionais;
- lotes automáticos;
- upload de documentos;
- inscrição individual ou por equipe;
- área do participante.

---

## 4. Estrutura funcional do domínio

```text
Clube
└── Categoria
    └── Evento
        ├── Sessões
        ├── Local
        ├── Mapa do espaço
        ├── Setores
        ├── Mesas
        ├── Assentos
        ├── Tipos de ingresso ou inscrição
        ├── Lotes
        ├── Estacionamento
        ├── Produtos adicionais
        ├── Cupons
        ├── Vendas
        ├── Pagamentos
        ├── Participantes
        ├── Ingressos
        └── Check-ins
```

---

## 5. Módulos do sistema

## 5.1 Configuração da loja do clube

Cada clube deverá possuir configurações próprias.

### Identidade visual

- nome comercial;
- logotipo;
- favicon;
- cores principais e secundárias;
- banner principal;
- imagem padrão de evento;
- imagem para desktop;
- imagem para dispositivos móveis;
- descrição institucional;
- redes sociais;
- endereço;
- telefone;
- WhatsApp;
- e-mail de suporte.

### Loja pública

- subdomínio próprio;
- possibilidade futura de domínio personalizado;
- título e descrição para SEO;
- eventos em destaque;
- categorias exibidas;
- ordem dos blocos da home;
- termos de uso;
- política de privacidade;
- política de cancelamento;
- perguntas frequentes;
- conteúdo do rodapé.

### Configurações comerciais

- conta PagBank conectada;
- formas de pagamento habilitadas;
- número máximo de parcelas;
- valor mínimo da parcela;
- definição de quem paga os juros;
- taxa de serviço;
- definição de quem paga a taxa;
- prazo padrão de reserva;
- prazo de expiração do Pix;
- suporte a venda interna;
- configuração de envio de e-mails.

---

## 5.2 Categorias de eventos

As categorias organizarão a loja pública.

### Campos

- nome;
- slug;
- descrição;
- imagem;
- ordem de exibição;
- status ativo ou inativo;
- visibilidade pública ou oculta;
- destaque na home;
- início da exibição;
- fim da exibição.

### Exemplos

- eventos sociais;
- shows;
- esportes;
- festas;
- cursos;
- competições;
- eventos infantis;
- inscrições;
- reservas.

### Regras

- Categorias inativas não podem receber novos eventos.
- Categorias ocultas não aparecem na navegação pública.
- Uma categoria oculta poderá ser acessível por link quando configurado.
- Categorias com histórico não devem ser excluídas fisicamente.

---

## 5.3 Eventos

### Identificação

- categoria;
- nome;
- slug;
- tipo do evento;
- descrição curta;
- descrição completa;
- imagem de capa;
- galeria;
- vídeo opcional;
- organizador;
- contato de suporte;
- classificação indicativa;
- termos específicos.

### Tipos de evento

- ingresso;
- inscrição;
- reserva de mesa;
- assento numerado;
- misto.

### Local e datas

- local;
- endereço completo;
- ponto de referência;
- coordenadas;
- data e hora de início;
- data e hora de término;
- horário de abertura dos portões;
- início das vendas;
- término das vendas;
- múltiplas sessões;
- evento recorrente;
- fuso horário.

### Visibilidade

- público;
- oculto por link;
- privado com código;
- exclusivo para associados;
- exclusivo para convidados;
- destacado na home.

### Status

```text
RASCUNHO
EM_CONFIGURACAO
AGENDADO
PUBLICADO
VENDAS_PAUSADAS
ESGOTADO
ENCERRADO
CANCELADO
ARQUIVADO
```

### Configurações de venda

- quantidade mínima por compra;
- quantidade máxima por compra;
- quantidade máxima por CPF;
- permitir compra sem login;
- exigir identificação do comprador;
- exigir identificação de todos os participantes;
- exigir CPF;
- exigir número de associado;
- permitir cupons;
- permitir cortesias;
- permitir estacionamento;
- permitir adicionais;
- permitir mapa de mesas ou assentos;
- exibir disponibilidade restante;
- exibir aviso de últimos ingressos;
- encerrar automaticamente ao atingir a capacidade;
- prazo de reserva específico do evento;
- política de cancelamento específica.

### Regras

- Evento com vendas ou ingressos emitidos não poderá ser excluído.
- Eventos com histórico deverão ser arquivados.
- Alterações relevantes após vendas deverão gerar registro de auditoria.
- Alterações de data, horário ou local deverão permitir comunicação aos compradores.

---

## 5.4 Sessões

Um evento poderá possuir uma ou várias sessões.

### Campos

- evento;
- nome da sessão;
- data e hora de início;
- data e hora de término;
- abertura dos portões;
- capacidade;
- mapa associado;
- status;
- início das vendas;
- término das vendas.

### Regras

- O estoque poderá ser compartilhado ou separado por sessão.
- Mesas, assentos, tipos de ingresso e preços poderão variar por sessão.
- Uma sessão com vendas não poderá ser removida sem procedimento de cancelamento.

---

## 5.5 Tipos de ingresso e inscrição

### Exemplos

- associado;
- não associado;
- infantil;
- inteira;
- meia-entrada;
- camarote;
- mesa completa;
- lugar individual;
- inscrição individual;
- inscrição por equipe;
- cortesia;
- estacionamento.

### Campos

- evento;
- sessão, quando aplicável;
- nome;
- descrição;
- classificação como ingresso ou inscrição;
- valor base;
- quantidade disponível;
- quantidade mínima por compra;
- quantidade máxima por compra;
- quantidade máxima por CPF;
- início da venda;
- término da venda;
- visibilidade;
- ordem de exibição;
- setor;
- lote;
- exigência de mesa ou assento;
- exigência de identificação;
- exigência de documento;
- exigência de comprovação;
- exclusividade para associado;
- taxa de serviço;
- status.

### Regras

- O valor utilizado na venda deve ser copiado para o item da venda.
- Alterações posteriores no ingresso não devem modificar vendas existentes.
- Tipos com vendas não deverão ser excluídos fisicamente.

---

## 5.6 Lotes e preços

### Formas de virada de lote

- por data e hora;
- por quantidade vendida;
- por esgotamento;
- manualmente.

### Campos

- tipo de ingresso;
- nome;
- valor;
- quantidade;
- início;
- término;
- prioridade;
- troca automática;
- status;
- limite por comprador;
- valor promocional opcional.

### Regras

- O próximo lote poderá ser ativado automaticamente.
- O administrador poderá antecipar, pausar ou encerrar um lote.
- O sistema deverá impedir venda além da quantidade do lote ou do estoque total.

---

## 5.7 Mapa visual de espaços, mesas e assentos

O mapa visual é uma funcionalidade central do produto.

### Editor administrativo

Deverá permitir:

- criar mapas reutilizáveis;
- enviar uma imagem da planta do local;
- criar setores;
- inserir mesas;
- inserir assentos;
- inserir camarotes;
- inserir áreas sem lugar marcado;
- inserir palco, entrada, saída, bar e banheiros;
- arrastar e posicionar elementos;
- redimensionar elementos;
- rotacionar elementos;
- configurar formato da mesa;
- configurar quantidade de lugares;
- numerar mesas e assentos;
- associar preços;
- bloquear lugares;
- marcar lugares acessíveis;
- duplicar mapas;
- versionar mapas.

### Estados dos elementos

```text
DISPONIVEL
SELECIONADO_PELO_CLIENTE
RESERVADO_TEMPORARIAMENTE
VENDIDO
BLOQUEADO
CORTESIA
INDISPONIVEL
```

### Jornada do comprador

1. selecionar o evento;
2. selecionar a sessão;
3. abrir o mapa;
4. visualizar setores, preços e disponibilidade;
5. clicar ou tocar na mesa;
6. visualizar lugares disponíveis;
7. escolher a mesa completa ou lugares individuais;
8. confirmar a seleção;
9. criar reserva temporária no servidor;
10. iniciar o prazo de 15 minutos.

### Regras críticas

- A reserva deverá ser registrada no servidor.
- Um lugar não poderá possuir duas reservas ativas.
- Um lugar não poderá ser vendido para dois vendas.
- O bloqueio deverá possuir data e hora de expiração.
- Reservas expiradas deverão liberar automaticamente o estoque.
- Um pagamento em processamento poderá receber tolerância configurável.
- A versão do mapa utilizada pelo evento deverá ser preservada.
- Alterar um mapa com lugares vendidos exigirá processo controlado.

---

## 5.8 Estacionamento e produtos adicionais

### Estacionamento

O estacionamento será vendido junto ao evento como item adicional.

#### Campos

- nome;
- descrição;
- valor;
- quantidade de vagas;
- início e fim das vendas;
- máximo por compra;
- sessão relacionada;
- exigir placa;
- exigir modelo;
- exigir cor;
- possuir QR Code próprio;
- permitir uma ou várias entradas;
- visibilidade;
- status.

### Produtos adicionais futuros

- camiseta;
- kit do evento;
- alimentação;
- transporte;
- consumação;
- pulseira premium;
- doação;
- reserva de espaço.

### Regras

- O estoque do estacionamento será independente do estoque de ingressos.
- O estacionamento deverá aparecer no mesma venda.
- O estacionamento poderá gerar ingresso ou voucher próprio.
- Um evento poderá possuir vários tipos de estacionamento.

---

## 5.9 Carrinho e reserva temporária

### Status do carrinho

```text
ATIVO
RESERVADO
EXPIRADO
CONVERTIDO_EM_PEDIDO
ABANDONADO
```

### Comportamento

- O prazo começa quando o estoque é efetivamente reservado.
- O prazo padrão será de 15 minutos.
- O tempo restante deverá ser calculado a partir do servidor.
- Recarregar a página não reinicia o prazo.
- O sistema deverá alertar quando restarem 5 minutos.
- O sistema deverá alertar novamente quando restar 1 minuto.
- Ao expirar, o checkout deverá ser invalidado.
- Mesas, assentos, ingressos e adicionais deverão ser liberados.
- Uma venda expirado não poderá ser pago sem nova validação de estoque.

### Dados da reserva

- tenant;
- cliente ou sessão anônima;
- evento;
- sessão;
- itens;
- mesa;
- assentos;
- data de criação;
- data de expiração;
- status;
- origem.

---

## 5.10 Checkout orientado

O checkout deverá utilizar etapas claras, linguagem simples e instruções contextuais.

### Etapa 1 — Seleção

- sessão;
- ingressos;
- mesa;
- assentos;
- estacionamento;
- adicionais.

### Etapa 2 — Identificação

Dados do comprador:

- nome;
- CPF;
- e-mail;
- telefone;
- data de nascimento quando necessário;
- número de associado quando necessário.

Dados dos participantes:

- nome;
- CPF ou documento;
- tipo de ingresso;
- respostas de questionário;
- informações adicionais.

### Etapa 3 — Conferência

Exibir:

- evento;
- sessão;
- data;
- horário;
- local;
- mesa e assentos;
- participantes;
- estacionamento;
- adicionais;
- subtotal;
- descontos;
- taxas;
- total;
- política de cancelamento;
- tempo restante.

### Etapa 4 — Pagamento

- Pix;
- cartão de crédito;
- cartão de débito, quando habilitado;
- parcelamento;
- instruções de segurança;
- prazo restante da reserva.

### Etapa 5 — Confirmação

- número da venda;
- status do pagamento;
- instruções de acesso;
- acesso aos ingressos;
- envio por e-mail;
- compartilhamento;
- contato de suporte;
- orientação para cancelamento e estorno.

---

## 5.11 Integração PagBank

### Objetivo

Cada clube deverá conectar sua própria conta PagBank, garantindo que os valores sejam recebidos diretamente em sua conta.

### Formas de pagamento configuráveis

- Pix;
- cartão de crédito;
- cartão de débito, conforme disponibilidade e requisitos técnicos;
- outras formas poderão ser adicionadas futuramente.

### Configurações

- habilitar ou desabilitar cada forma;
- máximo de parcelas;
- valor mínimo da parcela;
- juros pagos pelo comprador ou clube;
- prazo do Pix;
- credenciais por tenant;
- ambiente de homologação ou produção;
- status da integração.

### Fluxo

```text
Cliente finaliza o checkout
→ sistema cria a venda local
→ sistema cria a cobrança no PagBank
→ cliente efetua o pagamento
→ PagBank envia webhook
→ sistema valida a notificação
→ pagamento é atualizado
→ estoque é confirmado
→ ingressos são emitidos
```

### Regras técnicas

- A confirmação não deve depender do redirecionamento do navegador.
- O webhook deverá ser idempotente.
- Webhooks repetidos não poderão duplicar pagamentos ou ingressos.
- Todas as notificações deverão ser registradas.
- A assinatura ou autenticidade da notificação deverá ser validada.
- Falhas deverão entrar em fila de reprocessamento.
- O identificador externo deverá ser armazenado.
- Alterações de status deverão manter histórico.

---

## 5.12 Vendas

### Dados da venda

- número;
- tenant;
- evento;
- sessão;
- comprador;
- participantes;
- itens;
- mesa;
- assentos;
- estacionamento;
- adicionais;
- cupom;
- subtotal;
- desconto;
- taxa;
- valor total;
- valor líquido estimado;
- forma de pagamento;
- parcelas;
- identificador PagBank;
- origem da venda;
- status;
- data de criação;
- data de expiração;
- data de pagamento;
- data de cancelamento.

### Status da venda

```text
CRIADO
AGUARDANDO_PAGAMENTO
EM_ANALISE
PAGO
CONFIRMADO
EXPIRADO
RECUSADO
CANCELADO
ESTORNO_SOLICITADO
ESTORNADO_EXTERNAMENTE
CHARGEBACK
FALHA
```

### Origem da venda

```text
LOJA_ONLINE
VENDA_INTERNA
CORTESIA
IMPORTACAO
```

### Regras

- Status da venda e do pagamento devem ser separados.
- Itens e valores devem ser imutáveis após confirmação, exceto por processos auditados.
- Venda pago deverá confirmar estoque antes de emitir ingressos.
- Vendas expirados deverão liberar reservas.
- Operações manuais deverão registrar usuário, data e motivo.

---

## 5.13 Pagamentos

### Status do pagamento

```text
PENDENTE
AGUARDANDO
EM_ANALISE
APROVADO
RECUSADO
CANCELADO
ESTORNADO_PARCIAL
ESTORNADO_TOTAL
CHARGEBACK
ERRO
```

### Dados

- venda;
- provedor;
- identificador externo;
- método;
- parcelas;
- valor;
- status;
- payload de criação;
- resposta do provedor;
- data de aprovação;
- histórico de eventos.

---

## 5.14 Estorno externo

Na primeira versão, o sistema não efetuará o estorno diretamente no PagBank.

### Fluxo

1. comprador entra em contato com o clube;
2. operador localiza a venda;
3. clube analisa a solicitação;
4. clube realiza o estorno no PagBank;
5. operador registra o estorno no sistema;
6. sistema invalida os ingressos envolvidos;
7. operador escolhe se os lugares serão liberados;
8. relatórios são recalculados;
9. ação é registrada na auditoria.

### Campos

- venda;
- tipo total ou parcial;
- valor;
- motivo;
- data;
- identificador externo;
- comprovante opcional;
- operador;
- observações;
- ingressos afetados;
- liberar estoque;
- status.

### Regras

- O valor estornado não pode superar o valor pago.
- Estorno parcial deverá permitir selecionar itens ou ingressos.
- Ingressos estornados deverão ficar inválidos para check-in.
- A liberação de lugares deverá ser uma decisão explícita.

---

## 5.15 Ingressos digitais

### Dados

- identificador único;
- código alfanumérico;
- QR Code;
- venda;
- participante;
- evento;
- sessão;
- tipo de ingresso;
- setor;
- mesa;
- assento;
- status;
- data de emissão;
- histórico.

### Status

```text
PENDENTE
ATIVO
UTILIZADO
CANCELADO
ESTORNADO
BLOQUEADO
TRANSFERIDO
EXPIRADO
```

### Entrega

- área “Meus ingressos”;
- e-mail;
- PDF;
- impressão;
- compartilhamento por link seguro;
- reenvio pelo administrador.

### Regras

- O QR Code não deverá expor dados sensíveis.
- O token deverá ser difícil de adivinhar.
- Ingresso cancelado ou estornado não poderá ser utilizado.
- Ingresso utilizado deverá indicar data, hora, portaria e operador.

---

## 5.16 Controle de acesso e check-in

### Aplicação de portaria

Preferencialmente web responsiva ou PWA.

### Funcionalidades

- selecionar evento;
- selecionar sessão;
- selecionar portaria;
- ler QR Code pela câmera;
- buscar por nome;
- buscar por CPF;
- buscar por número da venda;
- buscar por código do ingresso;
- realizar check-in manual;
- desfazer check-in com permissão;
- consultar histórico;
- operar com conexão instável em fase posterior.

### Resultados

```text
VALIDO
JA_UTILIZADO
CANCELADO
ESTORNADO
BLOQUEADO
EVENTO_INCORRETO
SESSAO_INCORRETA
PORTARIA_NAO_AUTORIZADA
NAO_ENCONTRADO
```

### Dados do check-in

- ingresso;
- evento;
- sessão;
- portaria;
- operador;
- data e hora;
- dispositivo;
- resultado;
- observação;
- reversão, quando aplicável.

---

## 5.17 Cupons

### Campos

- código;
- descrição;
- percentual ou valor fixo;
- evento;
- tipo de ingresso;
- limite total;
- limite por CPF;
- data de início;
- data de validade;
- valor mínimo;
- exclusivo para associado;
- status.

### Regras

- Cupons não poderão gerar total negativo.
- O uso deverá ser registrado por compra e comprador.
- O sistema deverá impedir uso além do limite.
- Reversão de venda deverá devolver o uso quando configurado.

---

## 5.18 Cortesias e venda interna

### Cortesias

- gerar individualmente;
- gerar em lote;
- associar a mesa ou assento;
- informar beneficiário;
- informar motivo;
- registrar quem autorizou;
- enviar por e-mail;
- cancelar;
- acompanhar utilização.

### Venda interna

- selecionar ou cadastrar comprador;
- selecionar evento e sessão;
- selecionar itens;
- escolher mesa ou assento;
- informar pagamento externo;
- registrar dinheiro, maquininha, Pix externo ou cortesia;
- emitir ingresso;
- identificar origem da venda.

---

## 5.19 Associados e convidados

Funcionalidade preparada para integração futura com o cadastro do clube.

### Possibilidades

- validar matrícula;
- validar CPF;
- consultar API do clube;
- preço especial;
- pré-venda exclusiva;
- limite de convidados;
- evento exclusivo;
- validar dependentes;
- impedir compra por associado irregular, quando aplicável;
- aplicar desconto automaticamente.

---

## 5.20 Comunicações

### Eventos de comunicação

- venda criado;
- Pix gerado;
- pagamento aprovado;
- pagamento recusado;
- venda expirado;
- ingresso emitido;
- reenvio de ingresso;
- evento alterado;
- evento cancelado;
- lembrete do evento;
- estorno registrado.

### Canais

- e-mail no MVP;
- WhatsApp em fase posterior;
- SMS opcional;
- notificações internas.

---

## 6. Loja pública

## 6.1 Home

### Cabeçalho

- logo;
- início;
- eventos;
- categorias;
- meus ingressos;
- ajuda;
- entrar.

### Conteúdo

- banner principal;
- eventos em destaque;
- próximos eventos;
- categorias;
- eventos mais procurados;
- informações institucionais;
- perguntas frequentes;
- contato;
- redes sociais.

### Cards de evento

- imagem;
- nome;
- data;
- local;
- preço inicial;
- situação;
- botão de compra.

### Situações públicas

```text
DISPONIVEL
ULTIMOS_INGRESSOS
ESGOTADO
VENDAS_ENCERRADAS
EM_BREVE
CANCELADO
```

## 6.2 Página do evento

Ordem recomendada:

1. imagem de capa;
2. nome;
3. data e horário;
4. local;
5. botão de compra;
6. descrição;
7. tipos de ingresso;
8. mapa e seleção de lugares;
9. regras;
10. política de cancelamento;
11. dúvidas frequentes;
12. contato.

No celular, o botão de compra deverá permanecer acessível durante a navegação.

---

## 7. Relatórios e indicadores

## 7.1 Dashboard executivo

Indicadores principais:

- vendas brutas;
- receita líquida estimada;
- vendas pagos;
- ingressos vendidos;
- ticket médio;
- ocupação;
- check-ins;
- estornos;
- vendas de estacionamento;
- vendas pendentes;
- vendas expirados.

## 7.2 Gráficos

- vendas por período;
- receita por evento;
- vendas por tipo de ingresso;
- vendas por lote;
- vendas por forma de pagamento;
- ocupação por setor;
- ocupação por mesa;
- vendas pagos versus expirados;
- abandono de checkout;
- associados versus não associados;
- estacionamento vendido versus disponível.

## 7.3 Funil

```text
VISUALIZOU_EVENTO
→ SELECIONOU_INGRESSO
→ RESERVOU_ESTOQUE
→ INICIOU_CHECKOUT
→ ESCOLHEU_PAGAMENTO
→ PAGOU
```

## 7.4 Relatório por evento

- capacidade;
- disponível;
- reservado;
- vendido;
- bloqueado;
- cortesia;
- cancelado;
- estornado;
- receita bruta;
- descontos;
- taxas;
- receita líquida estimada;
- ticket médio;
- ocupação;
- check-ins;
- ausentes.

## 7.5 Relatório financeiro

- venda;
- data;
- comprador;
- evento;
- valor bruto;
- desconto;
- taxa;
- valor líquido;
- forma de pagamento;
- parcelas;
- status;
- identificador externo;
- estorno;
- origem.

## 7.6 Exportações

- CSV;
- Excel;
- PDF;
- participantes;
- mesas;
- estacionamento;
- portaria;
- financeiro;
- check-ins.

---

## 8. Menu administrativo sugerido

```text
Visão geral

Eventos
├── Categorias
├── Eventos
├── Sessões
├── Tipos de ingresso
├── Lotes
├── Mapas e espaços
└── Estacionamento e adicionais

Vendas
├── Vendas
├── Pagamentos
├── Participantes
├── Ingressos
├── Reservas expiradas
├── Cortesias
└── Estornos externos

Operação
├── Controle de acesso
├── Portarias
├── Check-ins
├── Lista de convidados
└── Bloqueios

Marketing
├── Cupons
├── Destaques da loja
└── Comunicações

Relatórios
├── Executivo
├── Vendas
├── Financeiro
├── Ocupação
├── Participantes
├── Check-in
└── Estacionamento

Configurações
├── Loja do clube
├── PagBank
├── Formas de pagamento
├── Prazos de reserva
├── Termos e políticas
├── E-mails
└── Integrações
```

---

## 9. Telas necessárias

## 9.1 Administração

1. dashboard;
2. categorias;
3. cadastro de categoria;
4. eventos;
5. cadastro de evento;
6. sessões;
7. tipos de ingresso;
8. lotes;
9. mapas;
10. editor de mapa;
11. mesas e assentos;
12. estacionamento e adicionais;
13. configuração de pagamentos;
14. vendas;
15. detalhes da venda;
16. participantes;
17. ingressos;
18. estornos externos;
19. controle de acesso;
20. relatórios;
21. configuração da loja;
22. integração PagBank.

## 9.2 Comprador

1. home;
2. eventos;
3. categoria;
4. detalhes do evento;
5. seleção de sessão;
6. seleção de ingresso;
7. mapa;
8. carrinho;
9. identificação;
10. participantes;
11. conferência;
12. pagamento;
13. Pix pendente;
14. pagamento em análise;
15. compra confirmada;
16. compra recusada;
17. reserva expirada;
18. meus vendas;
19. meus ingressos;
20. ingresso digital;
21. ajuda.

## 9.3 Portaria

1. seleção de evento;
2. seleção de sessão;
3. seleção de portaria;
4. leitor de QR Code;
5. busca manual;
6. resultado da validação;
7. histórico;
8. resumo de entradas.

---

## 10. Entidades sugeridas

Os nomes poderão ser adaptados ao padrão do projeto.

```text
event_categories
events
event_sessions
event_locations
venue_maps
venue_map_versions
venue_map_elements
sectors
tables
seats
ticket_types
ticket_batches
event_products
parking_products
coupons
coupon_usages
carts
cart_items
inventory_holds
orders
order_items
payments
payment_events
attendees
tickets
ticket_checkins
entrance_gates
external_refunds
complimentary_tickets
customer_questions
customer_answers
notification_logs
audit_logs
```

### Relacionamentos principais

```text
Event
├── belongsTo Category
├── hasMany Sessions
├── hasMany TicketTypes
├── hasMany Products
├── hasMany Orders
└── belongsTo VenueMapVersion

Session
├── belongsTo Event
├── hasMany InventoryHolds
├── hasMany Orders
└── hasMany Tickets

Order
├── belongsTo Customer
├── belongsTo Event
├── belongsTo Session
├── hasMany OrderItems
├── hasMany Payments
└── hasMany Tickets

Ticket
├── belongsTo OrderItem
├── belongsTo Attendee
├── belongsTo Seat
└── hasMany Checkins
```

---

## 11. Fluxos principais

## 11.1 Venda concluída

```text
Cliente acessa a loja
→ escolhe o evento
→ escolhe a sessão
→ escolhe o ingresso
→ seleciona mesa ou assento
→ adiciona estacionamento
→ sistema reserva o estoque
→ inicia contagem regressiva
→ cliente informa os dados
→ revisa a venda
→ escolhe o pagamento
→ sistema cria a venda
→ PagBank processa o pagamento
→ webhook confirma o pagamento
→ sistema confirma o estoque
→ gera os ingressos
→ envia a confirmação
→ ingresso é validado na portaria
```

## 11.2 Reserva expirada

```text
Prazo encerra
→ carrinho é marcado como expirado
→ venda pendente é expirado
→ reservas são removidas
→ mesas e assentos voltam a ficar disponíveis
→ comprador precisa reiniciar a seleção
```

## 11.3 Estorno externo

```text
Comprador contata o clube
→ clube analisa a solicitação
→ clube realiza o estorno no PagBank
→ operador registra o estorno no sistema
→ ingressos são invalidados
→ lugares podem ser liberados
→ relatórios são atualizados
```

## 11.4 Check-in

```text
Operador seleciona evento e portaria
→ lê o QR Code
→ sistema localiza o ingresso
→ valida evento, sessão e status
→ registra o check-in
→ apresenta entrada liberada ou motivo da recusa
```

---

## 12. Regras críticas de negócio

1. Um assento não pode ser vendido duas vezes.
2. Uma mesa não pode possuir reservas conflitantes.
3. A reserva temporária deve possuir expiração no servidor.
4. O sistema não deve confiar apenas no cronômetro do navegador.
5. O estoque deverá ser alterado dentro de transação.
6. O pagamento somente emitirá ingressos após confirmação confiável.
7. Webhooks deverão ser idempotentes.
8. Webhooks repetidos não poderão emitir ingressos duplicados.
9. Vendas expirados não poderão confirmar estoque sem nova validação.
10. Ingressos cancelados ou estornados deverão ser invalidados.
11. Check-in repetido deverá ser identificado.
12. Operações manuais deverão registrar usuário, data e motivo.
13. Evento com vendas não poderá ser excluído.
14. Valores históricos deverão ser preservados na venda.
15. Configurações futuras não poderão modificar vendas antigos.
16. O mapa utilizado pelo evento deverá possuir versão congelada.
17. Alterar mapas com vendas exigirá procedimento controlado.
18. O estacionamento terá estoque independente.
19. Pagamentos em análise deverão manter ou liberar estoque segundo regra configurável.
20. O sistema deverá distinguir venda online, interna, cortesia e importação.
21. Todos os dados deverão ser isolados por tenant.
22. Identificadores públicos não deverão usar IDs sequenciais previsíveis.
23. QR Codes não deverão conter dados pessoais em texto aberto.
24. Reprocessamento de pagamento não poderá gerar duplicidade.
25. O registro financeiro deverá separar valor bruto, desconto, taxa e líquido.

---

## 13. Requisitos não funcionais

### Segurança

- isolamento completo entre tenants;
- validação de autorização em todas as operações;
- proteção contra compra duplicada;
- proteção contra alteração de valor no frontend;
- validação de webhook;
- logs de auditoria;
- dados sensíveis criptografados quando necessário;
- links e tokens não previsíveis;
- limitação de tentativas em endpoints críticos.

### Desempenho

- mapa responsivo em celular;
- dashboard com filtros paginados;
- consultas de disponibilidade otimizadas;
- filas para e-mails, geração de PDF e webhooks;
- cache para catálogo público;
- bloqueios de estoque com baixa latência.

### Disponibilidade

- recuperação de webhooks;
- reprocessamento de notificações;
- monitoramento de filas;
- registro de falhas de integração;
- conciliação manual quando necessário.

### Usabilidade

- interface mobile-first para o comprador;
- instruções claras no checkout;
- mensagens de erro orientativas;
- contraste e acessibilidade;
- mapa utilizável por toque;
- resumo de compra sempre visível.

---

## 14. Escopo do MVP

### Obrigatório

- configuração visual da loja;
- categorias;
- eventos;
- visibilidade pública, oculta e privada;
- sessões;
- tipos de ingresso e inscrição;
- lotes;
- estoque;
- mapa de mesas e assentos;
- seleção direta no mapa;
- reserva temporária de 15 minutos;
- carrinho;
- estacionamento;
- checkout guiado;
- Pix;
- cartão de crédito;
- cartão de débito, caso tecnicamente habilitado;
- integração PagBank;
- webhook;
- vendas;
- pagamentos;
- ingressos com QR Code;
- área de ingressos;
- envio por e-mail;
- leitor de QR Code;
- check-in manual;
- dashboard básico;
- relatórios básicos;
- exportação;
- registro de estorno externo;
- auditoria.

### Fora do MVP

- nota fiscal;
- aplicativo nativo;
- reconhecimento facial;
- integração com catracas;
- revenda oficial;
- transferência de titularidade;
- QR Code dinâmico;
- split de pagamento;
- programa de fidelidade;
- BI avançado;
- integração automática com cadastro de associados;
- estorno automático pelo sistema;
- operação offline completa.

---

## 15. Fases posteriores

### Fase 2

- cupons;
- cortesias em lote;
- venda interna;
- questionários personalizados;
- integração com associados;
- lembretes automáticos;
- relatórios de conversão;
- PWA de check-in;
- produtos adicionais;
- múltiplas portarias;
- personalização de e-mails;
- WhatsApp.

### Fase 3

- aplicativo nativo;
- QR Code dinâmico;
- transferência de ingresso;
- revenda oficial;
- integração fiscal;
- catracas;
- reconhecimento facial;
- split de pagamento;
- promotores e comissões;
- fidelidade;
- BI avançado;
- preço dinâmico;
- CRM e automação de marketing.

---

## 16. Critérios de aceite do MVP

### Evento

- Administrador consegue criar um evento e publicá-lo.
- Evento pode ser público, oculto ou privado.
- Evento pode possuir uma ou mais sessões.
- Evento pode possuir tipos de ingresso com preços e estoques diferentes.

### Mapa

- Administrador consegue configurar mesas e assentos.
- Comprador consegue selecionar diretamente no mapa.
- Lugar selecionado fica temporariamente indisponível para outros compradores.
- Reserva expirada libera o lugar automaticamente.
- Não é possível confirmar duas vendas para o mesmo lugar.

### Checkout

- Comprador visualiza todas as etapas e o tempo restante.
- Valor apresentado no frontend é validado no backend.
- Venda armazena valores históricos.
- O sistema impede finalizar venda sem estoque disponível.

### Pagamento

- Clube consegue conectar sua conta PagBank.
- Clube consegue configurar Pix e cartão.
- Pagamento aprovado por webhook confirma a venda.
- Webhook repetido não duplica venda, pagamento ou ingresso.
- Pagamento recusado não emite ingresso.

### Ingresso

- Venda aprovado gera um ingresso único por participante ou item configurado.
- Ingresso possui QR Code.
- Comprador consegue acessar o ingresso.
- Administrador consegue reenviar o ingresso.
- Ingresso cancelado ou estornado não passa no check-in.

### Estacionamento

- Comprador consegue adicionar estacionamento no mesma venda.
- Estoque do estacionamento é controlado separadamente.
- O item aparece na venda e nos relatórios.

### Check-in

- Operador consegue ler QR Code.
- Sistema diferencia ingresso válido, utilizado, cancelado e inexistente.
- Check-in registra operador, portaria, data e hora.
- Segunda leitura apresenta aviso de ingresso já utilizado.

### Estorno externo

- Operador consegue registrar estorno total ou parcial.
- Ingressos afetados são invalidados.
- Operador pode decidir se o estoque será liberado.
- Relatórios refletem o valor estornado.

### Relatórios

- Gestor visualiza vendas, receita, ingressos, ocupação e check-ins.
- Gestor consegue filtrar por evento e período.
- Gestor consegue exportar os dados básicos.

---

## 17. Diretrizes de arquitetura

### Backend

Separar responsabilidades em domínios ou módulos:

```text
Events
Catalog
VenueMaps
Inventory
Checkout
Orders
Payments
Tickets
AccessControl
Reports
Notifications
```

### Serviços recomendados

- `EventService`
- `TicketTypeService`
- `InventoryService`
- `HoldService`
- `CheckoutService`
- `OrderService`
- `PagBankService`
- `PaymentWebhookService`
- `TicketIssuanceService`
- `CheckinService`
- `ExternalRefundService`
- `ReportService`

### Processos assíncronos

- processamento de webhook;
- emissão de ingresso;
- geração de PDF;
- envio de e-mail;
- liberação de reservas expiradas;
- reprocessamento de falhas;
- atualização de agregados de relatório.

### Concorrência de estoque

Recomenda-se:

- transação no banco;
- bloqueio pessimista ou estratégia equivalente;
- chave única para evitar venda duplicada;
- Redis opcional para reservas temporárias;
- job periódico de limpeza como contingência;
- idempotency key em operações de pagamento.

---

## 18. Estratégia recomendada de entrega

### Sprint 1 — Catálogo e eventos

- categorias;
- eventos;
- sessões;
- tipos de ingresso;
- lotes;
- loja pública inicial.

### Sprint 2 — Mapas e estoque

- mapas;
- mesas;
- assentos;
- disponibilidade;
- reserva temporária;
- expiração.

### Sprint 3 — Checkout e vendas

- carrinho;
- identificação;
- participantes;
- conferência;
- vendas;
- estacionamento.

### Sprint 4 — PagBank

- conexão da conta;
- Pix;
- cartão;
- webhooks;
- histórico de pagamento;
- tratamento de falhas.

### Sprint 5 — Ingressos e acesso

- emissão;
- QR Code;
- área do comprador;
- e-mails;
- leitor;
- check-in.

### Sprint 6 — Gestão e relatórios

- dashboard;
- relatórios;
- exportações;
- estorno externo;
- auditoria;
- ajustes finais de UX.

---

## 19. Diferenciais prioritários

O produto deverá concentrar sua diferenciação em quatro pontos:

1. **Loja bonita e personalizável** — experiência visual superior para cada clube.
2. **Mapa simples e intuitivo** — comprador escolhe a mesa ou assento diretamente no desenho do espaço.
3. **Checkout orientado** — instruções claras, etapas visíveis e resumo permanente.
4. **Gestão objetiva** — dashboards e relatórios que respondem rapidamente quanto vendeu, quanto recebeu, ocupação, disponibilidade e entradas.

Pagamentos e QR Codes são requisitos essenciais. A vantagem competitiva percebida virá principalmente da facilidade de compra e da clareza operacional para o clube.

---

## 20. Decisões pendentes antes do desenvolvimento

- O clube venderá mesa completa, lugares individuais ou ambos?
- Um assento poderá ter preços diferentes por tipo de comprador?
- Será exigido CPF de todos os participantes?
- Haverá integração com a base de associados no MVP?
- O estacionamento terá QR Code independente?
- O débito será realmente necessário no primeiro lançamento?
- Haverá taxa de serviço? Quem pagará?
- O sistema permitirá pagamento presencial no MVP?
- Um pagamento em análise manterá a reserva por quanto tempo?
- Lugares estornados voltarão automaticamente à venda?
- Haverá múltiplas portarias no primeiro evento?
- O clube precisa importar participantes ou vendas anteriores?

Essas decisões devem ser transformadas em configurações sempre que houver possibilidade de variar entre clubes.
