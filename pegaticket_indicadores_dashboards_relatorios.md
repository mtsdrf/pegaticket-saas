# PegaTicket — Especificação Completa de Indicadores, Dashboards e Relatórios

## 1. Objetivo

Este documento define a camada analítica do PegaTicket para que organizadores, produtores, gestores financeiros, equipes de marketing, operadores de acesso, bilheterias, afiliados e administradores tomem decisões rápidas e fundamentadas.

A solução deve separar claramente dois contextos:

1. **Home executivo:** leitura rápida, visual e acionável do que está acontecendo agora.
2. **Central de relatórios:** análises específicas, comparações, cruzamentos, detalhamento operacional e exportação para Excel.

A camada analítica não deve ser apenas uma coleção de gráficos. Cada indicador precisa responder pelo menos uma destas perguntas:

- O resultado está dentro do esperado?
- O que mudou?
- Por que mudou?
- Onde está o problema ou a oportunidade?
- Qual ação deve ser tomada agora?
- Qual evento, canal, lote, ingresso ou público explica o resultado?

---

# PARTE I — PRINCÍPIOS DA CAMADA ANALÍTICA

## 2. Princípios obrigatórios

### 2.1 Dados acionáveis

Todo cartão, gráfico ou tabela deve permitir uma ação relacionada, como:

- abrir vendas relacionadas;
- ajustar preço ou lote;
- revisar uma campanha;
- abrir um evento;
- consultar pedidos com falha;
- verificar portaria com fila;
- liberar capacidade;
- exportar dados;
- criar alerta;
- comparar com outro período;
- abrir detalhamento.

### 2.2 Contexto em todos os números

Nenhum número principal deve aparecer isolado. Sempre que aplicável, mostrar:

- valor atual;
- comparação com período anterior;
- comparação com meta;
- comparação com evento semelhante;
- tendência;
- participação no total;
- variação absoluta;
- variação percentual;
- situação esperada para a etapa atual do evento.

Exemplo:

> Receita líquida: R$ 284.500 — 12,4% acima da semana anterior e 6,8% abaixo da meta proporcional.

### 2.3 Do resumo ao detalhe

Toda visualização deve oferecer navegação progressiva:

```text
Organização → evento → sessão → setor → lote → tipo de ingresso → pedido → transação
```

O usuário deve conseguir selecionar uma parte de um gráfico e abrir os registros que formaram aquele valor.

### 2.4 Comparações justas

Comparações entre eventos devem considerar:

- dias desde a abertura das vendas;
- dias restantes até o evento;
- capacidade total;
- faixa de preço;
- tipo de evento;
- cidade ou região;
- dia da semana;
- duração do evento;
- número de sessões;
- investimento em marketing;
- estágio comercial equivalente.

Não se deve comparar apenas datas de calendário quando os eventos estão em estágios diferentes.

### 2.5 Tempo real onde importa

Atualização quase em tempo real para:

- vendas;
- pagamentos;
- estoque;
- ocupação;
- check-in;
- filas;
- falhas de pagamento;
- fraude;
- operação de bilheteria;
- consumo durante o evento.

Atualização periódica para:

- coortes;
- retenção;
- segmentação;
- previsões;
- atribuição de marketing;
- comparativos históricos.

### 2.6 Métricas com definição única

O sistema deve possuir catálogo de métricas contendo:

- nome oficial;
- descrição;
- fórmula;
- fonte dos dados;
- periodicidade de atualização;
- filtros compatíveis;
- tratamento de cancelamentos;
- tratamento de cortesias;
- tratamento de reembolsos;
- tratamento de taxas;
- proprietário responsável pela métrica;
- histórico de alterações na fórmula.

---

## 3. Filtros globais

Os filtros devem ser compartilhados entre Home e relatórios, respeitando permissões do usuário.

### 3.1 Filtros básicos

- organização;
- grupo econômico;
- produtora;
- evento;
- grupo de eventos;
- temporada;
- sessão;
- período de venda;
- período de realização;
- status do evento;
- tipo de evento;
- cidade;
- estado;
- país;
- local;
- modalidade presencial, online ou híbrida;
- canal de venda;
- tipo de ingresso;
- setor;
- lote;
- categoria de preço;
- origem da venda;
- meio de pagamento;
- gateway;
- afiliado ou promotor;
- campanha;
- cupom;
- dispositivo;
- perfil de cliente.

### 3.2 Períodos rápidos

- hoje;
- ontem;
- últimos 7 dias;
- últimos 14 dias;
- últimos 30 dias;
- mês atual;
- mês anterior;
- trimestre atual;
- ano atual;
- desde a abertura das vendas;
- até a data do evento;
- período personalizado.

### 3.3 Modos de comparação

- período imediatamente anterior;
- mesmo período do ano anterior;
- evento selecionado anteriormente;
- evento semelhante;
- meta definida;
- orçamento;
- previsão vigente;
- média da organização;
- mediana da categoria;
- estágio equivalente de venda.

### 3.4 Filtros salvos

O usuário deve poder:

- salvar uma combinação de filtros;
- nomear uma visão;
- tornar uma visão privada ou compartilhada;
- fixar uma visão como padrão;
- enviar o link mantendo os filtros;
- agendar o envio daquela visão;
- controlar quem pode visualizar ou editar.

---

# PARTE II — HOME EXECUTIVO

## 4. Objetivo do Home

O Home deve responder em poucos segundos:

1. Quanto vendemos?
2. Estamos no ritmo certo?
3. Quanto ainda podemos vender?
4. Existe algum problema urgente?
5. Qual evento exige atenção?
6. Qual canal ou campanha está funcionando?
7. Como está a entrada do público quando há evento acontecendo?

O Home não deve conter tabelas extensas nem indicadores excessivamente técnicos. Deve priorizar até 12 cartões principais, gráficos compactos e alertas acionáveis.

---

## 5. Personalização do Home

O conteúdo deve variar conforme perfil e momento operacional.

### 5.1 Perfil executivo ou administrador

Priorizar:

- receita;
- vendas;
- ocupação;
- previsão;
- margem;
- repasses;
- eventos em risco;
- desempenho comparativo.

### 5.2 Perfil financeiro

Priorizar:

- receita bruta e líquida;
- pagamentos;
- taxas;
- saldo a receber;
- reembolsos;
- chargebacks;
- conciliação;
- repasses.

### 5.3 Perfil marketing

Priorizar:

- tráfego;
- conversão;
- origem das vendas;
- campanhas;
- ROAS;
- cupons;
- recuperação de checkout;
- novos compradores;

### 5.4 Perfil operação e acesso

Priorizar:

- ingressos vendidos;
- público esperado;
- check-ins;
- velocidade de entrada;
- filas;
- portarias;
- falhas de leitura;
- capacidade ocupada.

### 5.5 Perfil produtor de evento

Priorizar:

- vendas do evento sob sua responsabilidade;
- curva de vendas;
- lotes;
- estoque;
- afiliados;
- previsão de fechamento;
- alertas de operação.

---

## 6. Estrutura recomendada do Home

### 6.1 Bloco A — Indicadores principais

#### KPI 1 — Receita bruta confirmada

**Definição:** valor total dos pedidos pagos antes de descontos financeiros, reembolsos, comissões, impostos e repasses.

Mostrar:

- valor atual;
- variação versus período anterior;
- percentual da meta;
- participação de ingressos e adicionais;
- mini tendência dos últimos dias.

Ações:

- abrir relatório de receita;
- comparar eventos;
- visualizar composição.

#### KPI 2 — Receita líquida projetada

**Definição:** receita estimada após descontos, taxas, comissões, impostos, reembolsos esperados e demais deduções configuradas.

Mostrar:

- valor realizado;
- valor projetado até o encerramento;
- diferença para meta;
- margem estimada quando custos estiverem cadastrados.

#### KPI 3 — Ingressos vendidos

Mostrar:

- quantidade paga;
- quantidade reservada;
- quantidade cortesia separadamente;
- variação no período;
- percentual da capacidade comercial.

#### KPI 4 — Taxa de ocupação comercial

Fórmula recomendada:

```text
Ingressos válidos emitidos ÷ capacidade comercial disponível
```

Mostrar:

- percentual geral;
- capacidade ainda disponível;
- setores próximos de esgotar;
- diferença entre capacidade física, operacional e comercial.

#### KPI 5 — Ticket médio por pedido

Fórmula:

```text
Receita bruta confirmada ÷ pedidos pagos
```

Apresentar também:

- ticket médio por participante;
- variação versus período anterior;
- impacto de adicionais;
- comparação com eventos semelhantes.

#### KPI 6 — Conversão de compra

Funil principal:

```text
Visualização do evento → início da seleção → checkout iniciado → pagamento iniciado → pedido pago
```

Mostrar:

- conversão total;
- maior ponto de abandono;
- comparação por dispositivo;
- variação recente.

#### KPI 7 — Vendas nas últimas 24 horas

Mostrar:

- ingressos vendidos;
- receita;
- melhor hora;
- variação versus as 24 horas anteriores;
- evento com maior aceleração.

#### KPI 8 — Previsão de venda final

Mostrar:

- ingressos previstos;
- receita prevista;
- faixa de confiança;
- percentual provável de ocupação;
- cenário conservador, provável e otimista.

#### KPI 9 — Saldo a receber

Mostrar:

- saldo total;
- próximo repasse;
- valores retidos;
- valores em análise;
- valores já liberados.

#### KPI 10 — Reembolsos e chargebacks

Mostrar:

- valor no período;
- percentual sobre receita;
- variação;
- eventos com maior incidência;
- alerta quando superar limite configurado.

#### KPI 11 — Público presente agora

Exibir somente quando houver evento em andamento.

Mostrar:

- check-ins realizados;
- percentual do público esperado;
- entradas nos últimos 15 minutos;
- pessoas ainda não presentes;
- reentradas;
- bloqueios ou leituras inválidas.

#### KPI 12 — Saúde operacional

Indicador composto com:

- estabilidade dos pagamentos;
- taxa de erro do checkout;
- atraso de webhooks;
- disponibilidade do check-in;
- sincronização offline;
- divergências financeiras;
- filas acima do limite.

O cartão deve informar os motivos que diminuíram a nota.

---

## 7. Gráficos principais do Home

### 7.1 Linha — Evolução de vendas e receita

Exibir duas métricas selecionáveis:

- ingressos vendidos;
- receita bruta;
- receita líquida;
- pedidos pagos;
- ticket médio.

Opções de agrupamento:

- hora;
- dia;
- semana;
- mês.

Comparações:

- período anterior;
- meta acumulada;
- previsão;
- evento comparável;
- estágio equivalente.

Interação:

- passar o cursor para ver valores;
- selecionar intervalo;
- clicar em pico ou queda;
- abrir vendas daquele ponto;
- anotar campanhas, viradas de lote e alterações de preço na linha temporal.

### 7.2 Funil — Jornada de conversão

Etapas recomendadas:

1. visualizações únicas da página;
2. clique em comprar;
3. seleção de ingresso;
4. início do checkout;
5. identificação concluída;
6. pagamento iniciado;
7. pagamento aprovado;
8. ingresso emitido.

Mostrar em cada etapa:

- volume;
- conversão para a próxima etapa;
- abandono;
- tempo médio;
- comparação com período anterior.

### 7.3 Barras horizontais — Desempenho dos eventos

Listar até 10 eventos por:

- receita;
- ingressos vendidos;
- ocupação;
- velocidade de venda;
- conversão;
- margem;
- crescimento recente.

Cada barra deve mostrar:

- realizado;
- meta;
- previsão;
- dias restantes.

### 7.4 Rosca — Composição das vendas

Alternar dimensão entre:

- canal de venda;
- tipo de ingresso;
- lote;
- meio de pagamento;
- origem de tráfego;
- dispositivo;
- venda online versus presencial;
- ingresso versus adicionais.

O gráfico de rosca deve ser usado somente quando houver poucas categorias. Quando houver muitas categorias, usar barras ordenadas.

### 7.5 Heatmap — Dias e horários de maior venda

Eixos:

- dia da semana;
- hora do dia.

Métricas alternáveis:

- pedidos;
- ingressos;
- receita;
- conversão;
- falhas de pagamento.

Utilidade:

- definir horários de campanhas;
- identificar picos;
- planejar atendimento;
- compreender comportamento de compra.

### 7.6 Gauge ou bullet chart — Meta de vendas

Preferir bullet chart quando houver espaço, pois permite mostrar:

- realizado;
- meta proporcional;
- meta final;
- previsão;
- faixa de risco.

### 7.7 Área empilhada — Receita por origem

Mostrar evolução temporal da receita por:

- orgânico;
- mídia paga;
- afiliados;
- parceiros;
- e-mail;
- redes sociais;
- venda presencial;
- marketplace;
- link direto.

### 7.8 Ranking visual — Eventos que precisam de atenção

Classificar por uma nota de risco composta de:

- venda abaixo do ritmo;
- baixa conversão;
- alto abandono;
- estoque parado;
- falha de pagamento;
- reembolso elevado;
- concentração excessiva em um canal;
- proximidade da data do evento;
- baixa eficiência de campanha.

Mostrar motivo principal e ação recomendada.

---

## 8. Bloco de alertas e oportunidades do Home

### 8.1 Alertas críticos

- gateway com aumento de recusas;
- checkout com erro acima do normal;
- evento próximo com ocupação baixa;
- setor ou lote esgotando rapidamente;
- divergência de conciliação;
- chargeback acima do limite;
- portaria com fila excessiva;
- scanner offline por tempo elevado;
- estoque negativo ou sobreposição de reserva;
- fraude ou bots em volume anormal;
- repasse bloqueado;
- documento fiscal pendente;
- campanha gastando sem conversão.

### 8.2 Oportunidades sugeridas

- liberar próximo lote;
- aumentar capacidade comercial;
- ativar lista de espera;
- recuperar checkouts abandonados;
- reativar compradores de evento semelhante;
- criar campanha para segmento com alta propensão;
- mover investimento para canal mais eficiente;
- oferecer upgrade;
- criar bundle com adicional;
- abrir venda presencial;
- estender campanha com bom retorno;
- ajustar horário de comunicação.

### 8.3 Feed de acontecimentos

Exemplos:

- evento atingiu 50%, 75%, 90% ou 100% da capacidade;
- lote esgotou;
- recorde de vendas por hora;
- campanha ultrapassou meta;
- primeiro check-in realizado;
- pico de entrada identificado;
- repasse concluído;
- evento publicado;
- previsão mudou significativamente.

---

# PARTE III — CENTRAL DE RELATÓRIOS

## 9. Organização da Central de Relatórios

A Central deve ser organizada por áreas:

1. Visão executiva e portfólio;
2. Vendas e receita;
3. Funil e conversão;
4. Inventário, lotes e assentos;
5. Financeiro e repasses;
6. Pagamentos;
7. Marketing e atribuição;
8. Público e CRM;
9. Acesso e operação;
10. Afiliados e promotores;
11. Cupons, convites e cortesias;
12. Reembolsos, cancelamentos e chargebacks;
13. Antifraude e risco;
14. Bilheteria e ponto de venda;
15. Adicionais, estacionamento e consumo;
16. Atendimento e experiência;
17. Revenda e transferência;
18. Fiscal e conciliação;
19. Previsões e inteligência;
20. Construtor de relatórios personalizados.

Cada relatório pode conter uma tela de resumo e telas secundárias de detalhamento.

---

# RELATÓRIO 1 — VISÃO EXECUTIVA DO PORTFÓLIO

## 10. Tela 1 — Resumo organizacional

### Indicadores

- receita bruta;
- receita líquida;
- pedidos pagos;
- ingressos vendidos;
- eventos ativos;
- eventos realizados;
- capacidade total;
- ocupação média ponderada;
- ticket médio;
- conversão média;
- margem estimada;
- saldo a receber;
- reembolso;
- chargeback;
- crescimento versus período anterior.

### Gráficos

- linha de receita e ingressos no tempo;
- barras de receita por evento;
- bolhas de receita versus ocupação, usando capacidade como tamanho da bolha;
- matriz de eventos por risco e oportunidade;
- mapa geográfico de receita e público;
- waterfall de receita bruta até receita líquida;
- distribuição de eventos por estágio.

## 11. Tela 2 — Comparação entre eventos

### Tabela comparativa

Colunas configuráveis:

- evento;
- data;
- capacidade;
- dias de venda;
- dias restantes;
- ingressos vendidos;
- ocupação;
- receita;
- receita por capacidade;
- ticket médio;
- conversão;
- reembolso;
- margem;
- previsão final;
- diferença para meta.

### Visualizações

- barras lado a lado;
- radar com poucas métricas normalizadas;
- dispersão receita versus conversão;
- dispersão ocupação versus dias restantes;
- curva de vendas normalizada por dias antes do evento;
- ranking por índice de desempenho.

### Cruzamentos

- tipo de evento × ticket médio;
- cidade × ocupação;
- local × taxa de conversão;
- dia da semana × receita;
- capacidade × velocidade de venda;
- investimento em marketing × receita incremental.

---

# RELATÓRIO 2 — VENDAS E RECEITA

## 12. Tela 1 — Resumo de vendas

### Indicadores

- receita bruta;
- receita líquida;
- valor de descontos;
- valor de taxas;
- pedidos criados;
- pedidos pagos;
- pedidos cancelados;
- ingressos vendidos;
- ingressos líquidos após cancelamentos;
- ticket médio por pedido;
- ticket médio por ingresso;
- itens por pedido;
- tempo médio entre abertura e compra;
- velocidade de venda;
- receita por dia disponível de venda.

### Gráficos

- linha de vendas por hora, dia, semana ou mês;
- linha acumulada versus meta;
- área empilhada por tipo de ingresso;
- barras por evento, lote, setor ou canal;
- waterfall da composição da receita;
- heatmap de vendas por dia e hora;
- boxplot de valor dos pedidos;
- histograma de quantidade de ingressos por pedido.

## 13. Tela 2 — Detalhamento por dimensão

Permitir alternar linhas e colunas entre:

- evento;
- sessão;
- setor;
- lote;
- ingresso;
- canal;
- afiliado;
- campanha;
- cupom;
- cidade do comprador;
- dispositivo;
- meio de pagamento;
- gateway;
- origem.

Métricas:

- pedidos;
- ingressos;
- receita;
- desconto;
- taxa;
- reembolso;
- receita líquida;
- ticket médio;
- participação;
- crescimento.

## 14. Tela 3 — Curva de vendas

### Visualizações

- vendas acumuladas por dias antes do evento;
- comparação com eventos anteriores;
- marcações de virada de lote;
- marcações de campanhas;
- marcações de mudança de preço;
- marcações de anúncio de atração;
- previsão até o evento.

### Indicadores derivados

- velocidade média diária;
- aceleração de vendas;
- desaceleração;
- melhor período;
- pior período;
- efeito de cada mudança de lote;
- elasticidade observada após alteração de preço.

---

# RELATÓRIO 3 — FUNIL E CONVERSÃO

## 15. Tela 1 — Funil geral

### Etapas

- impressão ou visualização do evento;
- visita única;
- clique em comprar;
- seleção de ingresso;
- criação de reserva;
- início do checkout;
- identificação;
- pagamento iniciado;
- pagamento aprovado;
- ingresso emitido.

### Métricas

- usuários em cada etapa;
- conversão entre etapas;
- conversão total;
- abandono por etapa;
- tempo em cada etapa;
- valor potencial perdido;
- valor recuperado;
- número de tentativas por pedido.

### Gráficos

- funil;
- Sankey para caminhos alternativos;
- linha de conversão no tempo;
- barras de abandono por motivo;
- heatmap de conversão por dispositivo e navegador.

## 16. Tela 2 — Abandono de checkout

### Indicadores

- checkouts iniciados;
- checkouts abandonados;
- valor abandonado;
- taxa de recuperação;
- receita recuperada;
- tempo até recuperação;
- canal de recuperação mais eficiente.

### Quebras

- etapa;
- dispositivo;
- sistema operacional;
- navegador;
- meio de pagamento;
- tipo de ingresso;
- faixa de valor;
- cliente novo ou recorrente;
- origem da campanha;
- erro apresentado.

### Gráficos

- barras de abandono por etapa;
- Pareto de motivos;
- linha de abandono ao longo do tempo;
- coorte de recuperação por horas desde o abandono;
- matriz dispositivo × forma de pagamento.

## 17. Tela 3 — Performance técnica da compra

- tempo de carregamento da página;
- tempo de resposta de disponibilidade;
- tempo para criar reserva;
- tempo para iniciar pagamento;
- tempo de aprovação;
- erros por endpoint;
- conversão por faixa de latência;
- impacto de lentidão na conversão;
- taxa de expiração de reserva;
- colisões de estoque;
- filas e tempo de espera em alta demanda.

---

# RELATÓRIO 4 — INVENTÁRIO, LOTES, SETORES E ASSENTOS

## 18. Tela 1 — Resumo de inventário

### Indicadores

- capacidade física;
- capacidade operacional;
- capacidade comercial;
- estoque liberado;
- estoque bloqueado;
- estoque reservado;
- estoque vendido;
- estoque disponível;
- ocupação;
- taxa de sell-through;
- assentos em hold;
- reservas expiradas;
- setores esgotados;
- lotes ativos.

### Gráficos

- barras empilhadas de capacidade por status;
- treemap por setor e categoria;
- mapa de assentos com ocupação;
- linha de consumo de estoque;
- heatmap de assentos mais procurados;
- rosca de inventário por status.

## 19. Tela 2 — Lotes e preços

### Indicadores

- vendas por lote;
- receita por lote;
- tempo de duração do lote;
- velocidade de venda;
- percentual vendido antes da virada;
- receita incremental entre lotes;
- preço médio realizado;
- desconto médio;
- estoque remanescente.

### Visualizações

- linha de preço versus volume;
- barras de vendas por lote;
- waterfall de aumento de receita por mudança de preço;
- curva de elasticidade observada;
- tabela de viradas programadas e realizadas.

## 20. Tela 3 — Assentos e mapa

### Indicadores

- ocupação por setor, fileira e bloco;
- assentos mais vendidos;
- assentos com maior abandono;
- assentos bloqueados;
- assentos acessíveis vendidos e disponíveis;
- vendas de assentos contíguos;
- grupos fragmentados;
- receita por zona do mapa.

### Visualizações

- mapa de calor sobre a planta;
- barras por setor;
- distribuição de preço por localização;
- tempo médio até venda por assento;
- zonas com baixa procura.

---

# RELATÓRIO 5 — FINANCEIRO E REPASSES

## 21. Tela 1 — Demonstrativo financeiro

### Indicadores

- receita bruta;
- descontos comerciais;
- taxas de serviço;
- taxas de pagamento;
- comissões;
- impostos;
- reembolsos;
- chargebacks;
- retenções;
- receita líquida;
- valor repassado;
- saldo a repassar;
- margem estimada;
- custo por ingresso vendido.

### Gráficos

- waterfall da receita;
- linha de recebimentos e repasses;
- barras por evento;
- composição de custos;
- aging de recebíveis;
- fluxo de caixa projetado.

## 22. Tela 2 — Agenda de recebimentos

### Indicadores

- próximos repasses;
- valores por data;
- valores pendentes;
- valores bloqueados;
- valores antecipados;
- custo de antecipação;
- prazo médio de recebimento.

### Visualizações

- calendário financeiro;
- linha de fluxo de caixa;
- barras por status;
- tabela de parcelas e liquidações.

## 23. Tela 3 — Rentabilidade por evento

Quando custos estiverem cadastrados:

- receita líquida;
- custos fixos;
- custos variáveis;
- custo de marketing;
- comissão;
- custo de estrutura;
- margem de contribuição;
- margem operacional;
- ponto de equilíbrio;
- ingressos necessários para break-even;
- retorno sobre investimento.

Gráficos:

- waterfall de lucro;
- linha de break-even;
- dispersão receita versus margem;
- comparação de rentabilidade entre eventos;
- cenários de ocupação e preço.

---

# RELATÓRIO 6 — PAGAMENTOS

## 24. Tela 1 — Desempenho dos pagamentos

### Indicadores

- pagamentos iniciados;
- pagamentos aprovados;
- taxa de aprovação;
- taxa de recusa;
- pagamentos pendentes;
- pagamentos expirados;
- tempo médio de aprovação;
- tentativas por pedido;
- valor aprovado;
- valor recusado;
- custo de processamento;
- disponibilidade por provedor.

### Gráficos

- linha de aprovação no tempo;
- barras por meio de pagamento;
- barras por gateway;
- Sankey de tentativa, retentativa e aprovação;
- heatmap por banco emissor e motivo de recusa;
- Pareto de recusas.

## 25. Tela 2 — Pix

- Pix gerados;
- Pix pagos;
- conversão de Pix;
- tempo médio até pagamento;
- expiração;
- pagamentos após lembrete;
- valor médio;
- horários de maior pagamento;
- divergências de webhook;
- devoluções.

Visualizações:

- curva de pagamento após geração;
- coorte por minutos até pagamento;
- heatmap hora × dia;
- funil geração → visualização → pagamento.

## 26. Tela 3 — Cartões

- autorização;
- captura;
- aprovação;
- recusa;
- 3DS solicitado;
- 3DS concluído;
- parcelamento médio;
- aprovação por número de parcelas;
- aprovação por bandeira;
- aprovação por emissor;
- custo por adquirente;
- retentativas bem-sucedidas.

## 27. Tela 4 — Roteamento inteligente

- volume roteado por provedor;
- aprovação por provedor;
- custo por provedor;
- tempo de resposta;
- indisponibilidade;
- ganho incremental do roteamento;
- pedidos recuperados por fallback;
- economia gerada.

---

# RELATÓRIO 7 — MARKETING E ATRIBUIÇÃO

## 28. Tela 1 — Aquisição

### Indicadores

- visitantes;
- novos visitantes;
- sessões;
- custo de mídia;
- pedidos atribuídos;
- receita atribuída;
- CAC;
- ROAS;
- conversão por canal;
- receita por visitante;
- custo por checkout iniciado;
- custo por ingresso vendido.

### Gráficos

- barras de receita por canal;
- linha de investimento e receita;
- scatter de CAC versus ROAS;
- funil por origem;
- área empilhada por canal;
- árvore ou Sankey de atribuição.

## 29. Tela 2 — Campanhas

Colunas:

- campanha;
- canal;
- período;
- investimento;
- impressões;
- cliques;
- CTR;
- visitas;
- checkouts;
- pedidos;
- ingressos;
- receita;
- conversão;
- CAC;
- ROAS;
- reembolso associado;
- receita líquida.

Visualizações:

- ranking de campanhas;
- linha temporal com início e fim das campanhas;
- quadrante alto retorno × alto volume;
- Pareto da receita;
- comparação entre modelos de atribuição.

## 30. Tela 3 — Origem e atribuição

Modelos suportados:

- último clique;
- primeiro clique;
- linear;
- baseado em posição;
- decaimento temporal;
- atribuição configurável;
- atribuição baseada em dados quando houver volume suficiente.

Indicadores:

- conversões assistidas;
- receita assistida;
- número médio de interações;
- tempo médio até compra;
- canais de descoberta;
- canais de fechamento.

## 31. Tela 4 — Links e UTMs

- acessos por link;
- visitantes únicos;
- compras;
- receita;
- conversão;
- ticket médio;
- dispositivo;
- localização;
- campanha, source, medium, content e term;
- links compartilhados;
- vendas indiretas após compartilhamento.

---

# RELATÓRIO 8 — PÚBLICO, CLIENTES E CRM

## 32. Tela 1 — Perfil do público

Indicadores agregados e compatíveis com consentimento:

- compradores únicos;
- participantes únicos;
- novos compradores;
- compradores recorrentes;
- frequência média;
- gasto médio;
- valor total do cliente;
- cidade e região;
- faixa etária quando coletada legalmente;
- preferências declaradas;
- interesses inferidos com governança;
- canal preferido;
- consentimento de comunicação.

Visualizações:

- mapa geográfico;
- barras por faixa;
- distribuição de gasto;
- treemap de interesses;
- coortes de primeira compra;
- matriz de recorrência e valor.

## 33. Tela 2 — Segmentação RFM

Dimensões:

- recência;
- frequência;
- valor monetário.

Segmentos sugeridos:

- campeões;
- clientes leais;
- potenciais leais;
- novos clientes;
- promissores;
- precisam de atenção;
- em risco;
- inativos.

Mostrar:

- tamanho do segmento;
- receita;
- ticket médio;
- frequência;
- eventos preferidos;
- propensão de recompra;
- ação recomendada.

## 34. Tela 3 — Coortes e retenção

Coortes por:

- mês da primeira compra;
- primeiro evento;
- primeira categoria;
- primeiro canal;
- primeira campanha.

Métricas:

- recompra em 30, 60, 90, 180 e 365 dias;
- receita acumulada;
- frequência;
- churn comportamental;
- tempo até segunda compra.

Visualização principal:

- heatmap de retenção por coorte.

## 35. Tela 4 — Afinidade e cruzamento de eventos

- compradores em comum entre eventos;
- afinidade entre categorias;
- sequência mais comum de compras;
- probabilidade de compra de evento B após evento A;
- região de sobreposição do público;
- ticket médio cruzado;
- intervalo entre eventos.

Visualizações:

- diagrama de Venn apenas para poucos eventos;
- matriz de afinidade;
- rede de eventos relacionados;
- Sankey de jornada entre categorias.

## 36. Tela 5 — Lifetime Value

- receita histórica por comprador;
- margem histórica;
- número de eventos;
- frequência;
- LTV realizado;
- LTV previsto;
- custo de aquisição;
- relação LTV/CAC;
- prazo de retorno do CAC.

---

# RELATÓRIO 9 — ACESSO, CHECK-IN E OPERAÇÃO

## 37. Tela 1 — Operação ao vivo

### Indicadores em tempo real

- público esperado;
- check-ins realizados;
- percentual presente;
- check-ins por minuto;
- pico de entrada;
- pessoas ainda não presentes;
- ingressos inválidos;
- ingressos já utilizados;
- tentativas duplicadas;
- reentradas;
- scanners ativos;
- scanners offline;
- portarias abertas;
- tempo estimado de fila.

### Gráficos

- linha de entradas por minuto;
- previsão de público acumulado;
- barras por portaria;
- heatmap de fluxo por horário e acesso;
- mapa operacional por portaria;
- ranking de dispositivos;
- distribuição de motivos de bloqueio.

## 38. Tela 2 — Desempenho por portaria

- check-ins;
- velocidade média;
- maior velocidade;
- falhas;
- rejeições;
- tempo ocioso;
- scanners por portaria;
- produtividade por operador;
- diferença entre capacidade esperada e processada.

## 39. Tela 3 — Curva de chegada

- horário real de chegada;
- antecedência em relação ao início;
- percentual por faixa horária;
- comparação com eventos anteriores;
- chegada por tipo de ingresso;
- chegada por setor;
- chegada de VIPs;
- no-show final.

## 40. Tela 4 — No-show

- ingressos válidos não utilizados;
- taxa de no-show;
- receita associada;
- no-show por ingresso, lote, canal, região e comprador;
- comparação entre cortesias e pagos;
- no-show de grupos;
- impacto na capacidade.

---

# RELATÓRIO 10 — AFILIADOS, PROMOTORES E COMISSÁRIOS

## 41. Tela 1 — Ranking

Indicadores:

- acessos;
- pedidos;
- ingressos;
- receita;
- conversão;
- ticket médio;
- comissão gerada;
- comissão paga;
- cancelamentos;
- reembolsos;
- receita líquida atribuída;
- percentual da meta.

Gráficos:

- barras de vendas por afiliado;
- ranking com meta;
- linha de desempenho;
- scatter volume versus conversão;
- Pareto de receita.

## 42. Tela 2 — Atribuição e qualidade

- primeiro contato versus último contato;
- vendas assistidas;
- tempo até conversão;
- pedidos suspeitos;
- uso próprio de cupom;
- concentração de compradores;
- taxa de cancelamento;
- taxa de reembolso;
- qualidade líquida da receita.

## 43. Tela 3 — Comissões

- comissão prevista;
- comissão aprovada;
- comissão retida;
- comissão cancelada;
- comissão paga;
- data prevista de pagamento;
- ajustes manuais;
- memória de cálculo.

---

# RELATÓRIO 11 — CUPONS, PROMOÇÕES, CONVITES E CORTESIAS

## 44. Tela 1 — Cupons

- códigos emitidos;
- códigos usados;
- taxa de uso;
- pedidos;
- ingressos;
- receita bruta;
- desconto concedido;
- receita líquida;
- ticket médio com e sem cupom;
- conversão assistida;
- custo por venda incremental;
- abuso ou compartilhamento suspeito.

Gráficos:

- ranking de cupons;
- linha de uso;
- barras de receita e desconto;
- scatter desconto versus conversão;
- Pareto de códigos.

## 45. Tela 2 — Cortesias e convidados

- cortesias disponibilizadas;
- emitidas;
- aceitas;
- utilizadas;
- não utilizadas;
- taxa de presença;
- valor comercial equivalente;
- responsável pela emissão;
- parceiro beneficiado;
- setor e tipo.

Cruzamentos:

- cortesia × presença;
- responsável × uso;
- parceiro × público gerado;
- cortesia × consumo adicional;
- cortesia × aquisição futura.

## 46. Tela 3 — Promoções e bundles

- participação das promoções;
- receita incremental;
- itens por pedido;
- adesão ao bundle;
- margem do bundle;
- canibalização estimada;
- comparação com público sem promoção;
- taxa de upgrade.

---

# RELATÓRIO 12 — REEMBOLSOS, CANCELAMENTOS E CHARGEBACKS

## 47. Tela 1 — Resumo

- solicitações de reembolso;
- reembolsos aprovados;
- recusados;
- pendentes;
- valor reembolsado;
- taxa de reembolso;
- tempo médio de resolução;
- cancelamentos de pedidos;
- chargebacks;
- taxa de chargeback;
- valor recuperado em contestação.

## 48. Tela 2 — Motivos

- motivo informado;
- evento cancelado;
- evento adiado;
- desistência;
- duplicidade;
- erro de compra;
- problema no pagamento;
- não reconhecimento;
- fraude;
- atendimento;
- outros.

Visualizações:

- Pareto de motivos;
- linha temporal;
- barras por evento;
- heatmap motivo × canal;
- coorte por dias entre compra e solicitação.

## 49. Tela 3 — Impacto financeiro

- receita perdida;
- taxas não recuperadas;
- custo operacional;
- comissão revertida;
- saldo retido;
- impacto no repasse;
- impacto na margem;
- previsão de chargebacks futuros.

---

# RELATÓRIO 13 — ANTIFRAUDE E RISCO

## 50. Tela 1 — Visão de risco

- transações analisadas;
- aprovadas automaticamente;
- recusadas;
- revisadas manualmente;
- fraude confirmada;
- falso positivo;
- valor protegido;
- receita perdida por recusa;
- taxa de fraude;
- taxa de falso positivo;
- tempo de análise.

## 51. Tela 2 — Padrões de risco

- múltiplos cartões por comprador;
- múltiplos compradores por dispositivo;
- volume anormal por IP;
- tentativas em alta velocidade;
- divergência geográfica;
- e-mails temporários;
- uso anormal de cupons;
- concentração de compras;
- revenda suspeita;
- bots e automação.

Visualizações:

- rede de relacionamento entre entidades;
- heatmap geográfico;
- linha de tentativas;
- distribuição de score de risco;
- Pareto de regras acionadas.

## 52. Tela 3 — Revisão manual

- fila atual;
- tempo de espera;
- valor em análise;
- prioridade;
- evento;
- score;
- regras acionadas;
- histórico do comprador;
- decisão;
- analista responsável.

---

# RELATÓRIO 14 — BILHETERIA E PONTO DE VENDA

## 53. Tela 1 — Vendas presenciais

- receita presencial;
- ingressos vendidos;
- pedidos;
- ticket médio;
- vendas por caixa;
- vendas por operador;
- vendas por terminal;
- vendas por local;
- forma de pagamento;
- cancelamentos;
- sangrias;
- divergência de caixa.

### Gráficos

- barras por operador;
- linha por hora;
- heatmap terminal × hora;
- composição por pagamento;
- ranking de locais.

## 54. Tela 2 — Fechamento de caixa

- saldo inicial;
- vendas;
- recebimentos;
- cancelamentos;
- estornos;
- sangrias;
- suprimentos;
- saldo esperado;
- saldo informado;
- divergência;
- responsável;
- horário de abertura e fechamento.

---

# RELATÓRIO 15 — ADICIONAIS, ESTACIONAMENTO, PRODUTOS E CONSUMO

## 55. Tela 1 — Adicionais vendidos no checkout

- receita de adicionais;
- participação na receita total;
- taxa de adesão;
- itens por pedido;
- receita incremental por pedido;
- produto mais vendido;
- combinação mais comum;
- margem por adicional;
- estoque disponível.

Visualizações:

- barras por produto;
- matriz ingresso × adicional;
- cesta de produtos;
- Pareto de receita;
- linha de adesão.

## 56. Tela 2 — Estacionamento

- vagas disponíveis;
- vouchers vendidos;
- ocupação prevista;
- utilização real;
- no-show;
- receita;
- receita por vaga;
- horário de chegada;
- acessos por portão;
- placas ou credenciais validadas quando aplicável.

## 57. Tela 3 — Consumo no evento

Quando houver integração cashless ou PDV:

- receita total de consumo;
- gasto médio por participante;
- transações por participante;
- vendas por ponto;
- vendas por produto;
- vendas por horário;
- tempo médio de atendimento;
- ruptura de estoque;
- participantes sem consumo;
- correlação ingresso × consumo;
- receita de patrocinadores ou mídia;
- estornos.

Visualizações:

- mapa de calor por ponto de venda;
- linha por minuto ou hora;
- barras por categoria;
- cesta de produtos;
- curva de gasto acumulado;
- comparação entre perfis de ingresso.

---

# RELATÓRIO 16 — ATENDIMENTO E EXPERIÊNCIA

## 58. Tela 1 — Atendimento

- chamados abertos;
- resolvidos;
- pendentes;
- tempo de primeira resposta;
- tempo de resolução;
- taxa de reabertura;
- satisfação;
- contato por canal;
- assunto;
- evento relacionado;
- pedidos impactados;
- valor em risco.

## 59. Tela 2 — Voz do cliente

- NPS;
- CSAT;
- avaliação do evento;
- avaliação da compra;
- avaliação da entrada;
- avaliação do atendimento;
- temas mais citados;
- sentimento agregado;
- intenção de recompra;
- intenção de recomendação.

Visualizações:

- linha de satisfação;
- distribuição de notas;
- Pareto de temas;
- matriz satisfação × etapa;
- nuvem de temas somente como complemento, nunca como única análise.

---

# RELATÓRIO 17 — TRANSFERÊNCIA E REVENDA OFICIAL

## 60. Tela 1 — Transferências

- ingressos transferidos;
- taxa de transferência;
- transferências aceitas;
- pendentes;
- canceladas;
- tempo até aceite;
- quantidade de titulares únicos;
- eventos e tipos mais transferidos.

## 61. Tela 2 — Revenda

- ingressos anunciados;
- ingressos revendidos;
- taxa de conversão da revenda;
- tempo médio para revender;
- valor transacionado;
- preço médio;
- taxa gerada;
- receita compartilhada com produtor;
- ingressos retirados da revenda;
- revendas próximas ao evento;
- impacto na presença;
- redução estimada do comércio informal.

Visualizações:

- linha de anúncios e vendas;
- funil de revenda;
- distribuição de tempo até revenda;
- barras por evento e ingresso;
- mapa de fluxo titular anterior → novo titular em nível agregado.

---

# RELATÓRIO 18 — FISCAL E CONCILIAÇÃO

## 62. Tela 1 — Conciliação

- pedidos no sistema;
- transações no gateway;
- liquidações;
- divergências;
- transações sem pedido;
- pedidos sem transação;
- valores divergentes;
- taxas divergentes;
- chargebacks não refletidos;
- repasses divergentes;
- conciliações pendentes.

Visualizações:

- waterfall de conciliação;
- barras por tipo de divergência;
- aging de pendências;
- linha de divergência acumulada.

## 63. Tela 2 — Documentos fiscais

- documentos a emitir;
- emitidos;
- autorizados;
- rejeitados;
- cancelados;
- valor fiscal;
- impostos;
- erros por motivo;
- tempo de emissão;
- contingência.

---

# RELATÓRIO 19 — PREVISÕES E INTELIGÊNCIA

## 64. Tela 1 — Previsão de vendas

- ingressos previstos;
- receita prevista;
- ocupação prevista;
- data provável de esgotamento;
- faixa de confiança;
- diferença para meta;
- mudança da previsão ao longo do tempo.

Entradas do modelo:

- histórico do evento;
- eventos semelhantes;
- velocidade recente;
- dias restantes;
- preço;
- lote;
- capacidade;
- campanhas;
- sazonalidade;
- dia da semana;
- localidade;
- categoria;
- feriados;
- mudanças relevantes registradas.

## 65. Tela 2 — Cenários

Cenários configuráveis:

- manter situação atual;
- aumentar investimento em marketing;
- reduzir ou aumentar preço;
- abrir novo lote;
- liberar capacidade;
- lançar promoção;
- ativar afiliados;
- oferecer upgrade;
- mudar prazo de parcelamento.

Resultados estimados:

- vendas;
- receita;
- margem;
- ocupação;
- CAC;
- risco.

O sistema deve deixar claro que cenários são estimativas, não garantias.

## 66. Tela 3 — Anomalias

Detectar automaticamente:

- queda inesperada de conversão;
- aumento de recusas;
- pico suspeito de tráfego;
- vendas fora do padrão;
- reembolso acima do esperado;
- divergência financeira;
- comportamento anormal de afiliado;
- falha de check-in;
- mudança abrupta de previsão;
- preço ou lote configurado incorretamente.

Apresentar:

- métrica afetada;
- período;
- desvio;
- possíveis causas;
- dimensões relacionadas;
- impacto estimado;
- ação recomendada.

## 67. Tela 4 — Recomendações

Exemplos:

- “O evento está 18% abaixo do ritmo esperado a 12 dias da realização.”
- “Compradores recorrentes possuem conversão 3,2 vezes maior; considere campanha específica.”
- “O gateway B apresenta aprovação maior para cartões em seis parcelas.”
- “O lote atual deve esgotar em aproximadamente dois dias.”
- “A portaria norte processa 34% menos pessoas por minuto que a média.”

Cada recomendação deve informar:

- evidência utilizada;
- impacto estimado;
- nível de confiança;
- ação sugerida;
- possibilidade de dispensar ou marcar como resolvida.

---

# PARTE IV — CRUZAMENTOS DE DADOS E ANÁLISES AVANÇADAS

## 68. Cruzamentos prioritários

### 68.1 Vendas × Marketing

- receita por campanha;
- conversão por campanha;
- ticket médio por canal;
- margem após custo de mídia;
- compradores novos versus recorrentes por origem;
- tempo até compra por campanha;
- reembolso por campanha;
- LTV por canal de aquisição.

### 68.2 Vendas × Público

- ticket médio por segmento;
- frequência por categoria de evento;
- região versus tipo de ingresso;
- idade agregada versus horário do evento, quando legalmente coletada;
- clientes recorrentes versus ocupação;
- afinidade entre eventos;
- compradores versus participantes efetivos.

### 68.3 Vendas × Inventário

- velocidade por setor;
- preço versus procura;
- assento versus abandono;
- estoque parado;
- lotes versus conversão;
- virada de lote versus aceleração;
- capacidade liberada versus receita incremental.

### 68.4 Vendas × Pagamento

- conversão por meio;
- ticket médio por forma de pagamento;
- parcelas versus aprovação;
- gateway versus aprovação e custo;
- dispositivo versus recusa;
- valor do pedido versus fraude;
- retentativa versus recuperação.

### 68.5 Vendas × Acesso

- tipo de ingresso versus horário de chegada;
- canal versus no-show;
- lote versus presença;
- cortesia versus presença;
- afiliado versus no-show;
- comprador recorrente versus chegada;
- revenda versus presença.

### 68.6 Acesso × Consumo

- horário de entrada versus gasto;
- tipo de ingresso versus consumo;
- setor versus consumo;
- permanência estimada versus gasto;
- perfil de público versus categoria de produto;
- cortesia versus consumo;
- participante recorrente versus gasto.

### 68.7 Atendimento × Receita

- chamados versus cancelamento;
- tempo de resposta versus reembolso;
- motivo do contato versus abandono;
- satisfação versus recompra;
- evento versus volume de chamados;
- problema técnico versus receita perdida.

### 68.8 Fraude × Revenda × Transferência

- múltiplas titularidades;
- velocidade de revenda;
- compras concentradas;
- dispositivos relacionados;
- chargeback após transferência;
- revenda próxima ao horário do evento;
- padrões de compradores reincidentes.

---

## 69. Visualizações analíticas recomendadas

### 69.1 Linha

Usar para:

- evolução temporal;
- tendências;
- previsões;
- metas acumuladas;
- comparação entre períodos.

### 69.2 Barras verticais

Usar para:

- comparação de poucas categorias;
- evolução por períodos discretos;
- realizado versus meta.

### 69.3 Barras horizontais

Usar para:

- rankings;
- nomes longos;
- muitos eventos ou campanhas.

### 69.4 Barras empilhadas

Usar para:

- composição de um total;
- evolução da composição;
- capacidade por status.

### 69.5 Pizza ou rosca

Usar somente quando:

- houver poucas categorias;
- a soma representar 100%;
- diferenças forem claras.

Não usar para comparar muitos eventos, campanhas ou categorias semelhantes.

### 69.6 Área

Usar para:

- volume acumulado;
- composição ao longo do tempo;
- demanda simultânea.

### 69.7 Heatmap

Usar para:

- dia × hora;
- portaria × horário;
- setor × procura;
- dispositivo × conversão;
- coortes;
- matriz de afinidade.

### 69.8 Dispersão e bolhas

Usar para:

- correlação;
- identificação de outliers;
- receita × conversão;
- ocupação × prazo;
- CAC × ROAS;
- volume × margem.

### 69.9 Funil

Usar para:

- etapas ordenadas de conversão;
- abandono;
- revenda;
- recuperação.

### 69.10 Sankey

Usar para:

- caminhos do usuário;
- migração entre canais;
- tentativas de pagamento;
- jornada entre eventos.

### 69.11 Waterfall

Usar para:

- receita bruta até líquida;
- margem;
- conciliação;
- impacto de descontos e taxas.

### 69.12 Treemap

Usar para:

- composição de receita;
- inventário por setor;
- portfólio de eventos;
- produtos e categorias.

### 69.13 Bullet chart

Usar para:

- realizado versus meta;
- previsão versus capacidade;
- desempenho por indicador.

### 69.14 Boxplot

Usar para:

- distribuição de valor de pedidos;
- tempo de entrada;
- tempo de aprovação;
- comparação de dispersão entre eventos.

### 69.15 Histograma

Usar para:

- quantidade de ingressos por pedido;
- distribuição de gasto;
- tempo até compra;
- antecedência de chegada.

### 69.16 Mapa geográfico

Usar para:

- compradores;
- participantes;
- receita por região;
- alcance de campanha;
- deslocamento estimado.

### 69.17 Rede de relacionamento

Usar com acesso controlado para:

- fraude;
- afinidade de eventos;
- entidades relacionadas;
- caminhos de recompra.

---

# PARTE V — CONSTRUTOR DE RELATÓRIOS PERSONALIZADOS

## 70. Funcionalidades obrigatórias

O usuário autorizado deve poder:

- escolher uma fonte de dados;
- selecionar dimensões;
- selecionar métricas;
- criar métricas calculadas;
- adicionar filtros;
- definir agrupamento;
- ordenar;
- limitar resultados;
- escolher visualização;
- combinar mais de uma visualização;
- criar tabela dinâmica;
- salvar relatório;
- duplicar relatório;
- compartilhar;
- exportar;
- agendar envio;
- fixar no Home;
- definir alertas;
- adicionar descrição e observações.

## 71. Fontes de dados disponíveis

- eventos;
- sessões;
- inventário;
- lotes;
- assentos;
- pedidos;
- itens do pedido;
- pagamentos;
- repasses;
- reembolsos;
- chargebacks;
- ingressos;
- check-ins;
- clientes;
- participantes;
- campanhas;
- links;
- afiliados;
- cupons;
- cortesias;
- adicionais;
- bilheteria;
- atendimento;
- revenda;
- transferências;
- fraude;
- conciliação;
- fiscal.

## 72. Métricas calculadas

Permitir fórmulas seguras, como:

```text
Receita líquida = receita bruta - descontos - reembolsos - taxas - comissões
Conversão = pedidos pagos / visitantes únicos
Ocupação = ingressos válidos / capacidade comercial
ROAS = receita atribuída / investimento
Receita por participante = receita total / participantes presentes
```

O sistema deve validar:

- divisão por zero;
- compatibilidade entre dimensões;
- duplicidade causada por junções;
- tipos de dados;
- permissão de acesso;
- impacto de performance.

## 73. Tabelas dinâmicas

Recursos:

- linhas e colunas configuráveis;
- subtotais;
- totais gerais;
- expansão hierárquica;
- percentuais do total;
- comparação com período anterior;
- formatação condicional;
- agrupamento de datas;
- drill-down;
- exportação preservando estrutura.

---

# PARTE VI — EXPORTAÇÃO PARA EXCEL E DISTRIBUIÇÃO

## 74. Requisitos de exportação

Toda tela de relatório deve permitir exportar:

- dados resumidos;
- dados detalhados;
- registros que formam um gráfico;
- tabela atual;
- todas as páginas;
- somente colunas visíveis;
- colunas selecionadas;
- arquivo CSV para grandes volumes;
- arquivo XLSX formatado;
- PDF executivo quando aplicável.

## 75. Estrutura recomendada do XLSX

### Aba 1 — Resumo

- nome do relatório;
- organização;
- eventos selecionados;
- período;
- filtros aplicados;
- data e hora de geração;
- usuário responsável;
- indicadores principais.

### Aba 2 — Dados consolidados

- tabela principal do relatório;
- cabeçalhos congelados;
- filtros automáticos;
- tipos numéricos corretos;
- datas como datas;
- moedas como valores numéricos;
- percentuais como percentuais.

### Aba 3 — Dados detalhados

- granularidade de pedido, item, ingresso ou transação conforme relatório;
- identificadores rastreáveis;
- sem mascaramento indevido para usuários autorizados;
- mascaramento obrigatório para perfis sem permissão.

### Aba 4 — Dicionário de dados

- nome da coluna;
- significado;
- fórmula;
- unidade;
- origem;
- observações.

### Aba 5 — Metadados

- filtros;
- ordenação;
- timezone;
- moeda;
- versão do relatório;
- horário de atualização dos dados.

## 76. Exportações grandes

Para volumes elevados:

- processar de forma assíncrona;
- informar status;
- dividir arquivos quando necessário;
- compactar em ZIP;
- disponibilizar por tempo limitado;
- registrar auditoria;
- notificar quando concluído;
- impedir fórmulas maliciosas em CSV;
- aplicar limites por perfil;
- permitir exportação incremental.

## 77. Agendamento

Permitir envio:

- diário;
- semanal;
- mensal;
- após o evento;
- após fechamento financeiro;
- em horário configurado;
- quando uma meta ou condição for atingida.

Formatos:

- link seguro;
- XLSX;
- CSV;
- PDF;
- resumo no corpo do e-mail.

Destinatários devem respeitar acesso e consentimento.

---

# PARTE VII — ALERTAS, METAS E DECISÕES

## 78. Metas

Permitir metas por:

- organização;
- evento;
- sessão;
- receita;
- ingressos;
- ocupação;
- conversão;
- ticket médio;
- margem;
- afiliado;
- campanha;
- canal;
- período.

Tipos:

- meta fixa;
- meta por período;
- meta acumulada;
- meta proporcional aos dias restantes;
- meta baseada em evento anterior;
- orçamento versus realizado.

## 79. Alertas configuráveis

Exemplos:

- ocupação abaixo de 50% a dez dias do evento;
- conversão caiu mais de 20%;
- lote atingiu 90%;
- Pix expirado acima do limite;
- aprovação de cartão abaixo da meta;
- reembolso acima de 3%;
- campanha com ROAS abaixo de 1;
- portaria processando menos de determinado volume;
- saldo divergente;
- previsão de esgotamento antecipado;
- estoque abaixo de limite;
- comissão acima do orçamento.

Canais:

- notificação interna;
- e-mail;
- push;
- webhook;
- integração corporativa autorizada.

## 80. Anotações e contexto

Usuários autorizados devem poder adicionar marcações à linha temporal:

- início de campanha;
- anúncio de atração;
- mudança de preço;
- virada de lote;
- publicação em rede social;
- indisponibilidade;
- mudança de local ou data;
- abertura de setor;
- ação com influenciador;
- evento externo relevante.

Isso permite explicar picos e quedas sem depender de memória informal.

---

# PARTE VIII — REQUISITOS NÃO FUNCIONAIS

## 81. Atualização dos dados

- vendas e pagamentos: atualização em segundos;
- inventário: atualização em segundos;
- check-in: atualização próxima do tempo real;
- marketing: conforme disponibilidade das integrações;
- financeiro: conforme eventos de liquidação e conciliação;
- coortes e previsões: processamento periódico identificado na tela.

Cada visualização deve exibir quando foi atualizada.

## 82. Performance

- Home deve carregar inicialmente em até 3 segundos em condições normais;
- filtros comuns devem responder em até 2 segundos;
- relatórios complexos podem usar processamento progressivo;
- tabelas extensas devem usar paginação ou virtualização;
- consultas devem ser pré-agregadas quando necessário;
- gráficos não devem carregar milhões de pontos diretamente;
- exportações pesadas devem ocorrer em processamento separado.

## 83. Consistência

- os valores do Home e dos relatórios devem usar as mesmas definições;
- mudanças retroativas devem ser rastreadas;
- reembolsos e cancelamentos devem refletir conforme data de competência e data de ocorrência;
- fuso horário deve ser explícito;
- moeda deve ser explícita;
- dados em processamento devem ser identificados.

## 84. Segurança e permissões

Controlar separadamente acesso a:

- receita;
- margem;
- custos;
- dados pessoais;
- dados de pagamento mascarados;
- antifraude;
- comissões;
- repasses;
- exportação;
- relatórios personalizados;
- compartilhamento externo.

Toda exportação deve ser auditada.

## 85. Privacidade e LGPD

- usar dados agregados sempre que identificação individual não for necessária;
- aplicar finalidade e base legal;
- respeitar consentimentos;
- permitir anonimização;
- limitar retenção;
- mascarar dados conforme função;
- registrar acesso a dados pessoais;
- impedir inferências sensíveis indevidas;
- possibilitar atendimento a direitos do titular.

## 86. Acessibilidade

- gráficos devem possuir tabela ou descrição equivalente;
- não depender apenas de cor;
- permitir navegação por teclado;
- fornecer nomes acessíveis;
- permitir ampliação;
- preservar leitura em diferentes resoluções;
- tooltips não podem ser a única forma de consultar valores.

## 87. Auditoria

Registrar:

- quem criou ou alterou relatório;
- quem exportou;
- filtros utilizados;
- quem compartilhou;
- alteração de fórmula;
- mudança de meta;
- configuração de alerta;
- acesso a informação restrita.

---

# PARTE IX — PRIORIZAÇÃO DE IMPLEMENTAÇÃO

## 88. Fase 1 — Essencial para operação comercial

Implementar primeiro:

- Home executivo;
- receita e ingressos vendidos;
- ocupação;
- ticket médio;
- vendas nas últimas 24 horas;
- linha de vendas;
- composição por ingresso, canal e pagamento;
- relatório de vendas;
- relatório de pedidos;
- relatório de pagamentos;
- relatório financeiro básico;
- relatório de check-in;
- filtros globais;
- exportação XLSX e CSV;
- permissões;
- metas básicas;
- alertas de estoque e pagamento.

## 89. Fase 2 — Competitividade forte

- funil completo;
- origem e campanhas;
- afiliados;
- cupons;
- inventário avançado;
- assentos;
- repasses;
- reembolsos;
- comparação entre eventos;
- relatórios agendados;
- visões salvas;
- dashboard por perfil;
- operação ao vivo;
- conciliação;
- custos e rentabilidade.

## 90. Fase 3 — Diferenciação

- CRM unificado;
- RFM;
- coortes;
- LTV;
- afinidade entre eventos;
- previsão de vendas;
- anomalias;
- recomendações inteligentes;
- cenários;
- roteamento de pagamentos analisável;
- consumo cashless;
- revenda e transferência;
- construtor de relatórios;
- métricas calculadas;
- benchmark normalizado.

## 91. Fase 4 — Inteligência avançada

- atribuição baseada em dados;
- previsão de no-show;
- propensão de compra;
- propensão de reembolso;
- otimização de preço e lote;
- otimização de campanhas;
- detecção de fraude em rede;
- previsão de filas;
- recomendações operacionais em tempo real;
- consultas em linguagem natural com respostas auditáveis.

---

# PARTE X — RESUMO DAS DUAS EXPERIÊNCIAS

## 92. Home

Deve ser:

- rápido;
- visual;
- personalizado;
- limitado aos indicadores mais relevantes;
- comparativo;
- atualizado;
- orientado a alertas e ações;
- adequado para consulta diária e móvel.

Conteúdo ideal:

- 8 a 12 KPIs;
- linha de vendas;
- funil de conversão;
- ranking de eventos;
- composição das vendas;
- meta e previsão;
- alertas;
- operação ao vivo quando aplicável.

## 93. Relatórios

Devem ser:

- profundos;
- filtráveis;
- comparáveis;
- detalháveis;
- exportáveis;
- auditáveis;
- customizáveis;
- adequados a análises financeiras, comerciais, operacionais e estratégicas.

A Central de Relatórios deve permitir que o usuário comece com uma pergunta de negócio, visualize o resultado, identifique a causa, acesse os registros e exporte os dados sem precisar solicitar extrações manuais à equipe técnica.

---

# 94. Critérios finais de aceite

A camada analítica será considerada completa quando:

1. os indicadores possuírem fórmulas documentadas;
2. Home e relatórios apresentarem valores consistentes;
3. usuários puderem navegar do total ao registro de origem;
4. filtros forem preservados entre visualização e exportação;
5. comparações considerarem estágio equivalente dos eventos;
6. alertas indicarem causa e ação possível;
7. relatórios respeitarem permissões e LGPD;
8. exportações possuírem dicionário e metadados;
9. dados em tempo real forem claramente diferenciados de dados processados;
10. o sistema suportar visões salvas, compartilhamento e agendamento;
11. o Home responder rapidamente o que exige atenção;
12. relatórios permitirem análises financeiras, comerciais, operacionais e de público sem dependência de consultas manuais ao banco.
