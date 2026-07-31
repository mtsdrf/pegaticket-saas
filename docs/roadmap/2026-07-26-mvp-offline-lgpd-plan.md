# PegaTicket — Plano de fechamento do MVP para apresentação comercial

Data de referência: **26 de julho de 2026**

Este documento define o recorte exato de duas frentes consideradas obrigatórias para apresentar o PegaTicket como MVP para empresas:

1. **Jurídico / LGPD operacional**
2. **Offline real do PDV/Balcão**

O objetivo aqui não é abrir escopo genérico. É fechar um **MVP vendável**, com linguagem honesta, baixo risco de promessa errada e critérios claros de aceite.

---

## 1. Princípio de implantação

O projeto já possui um núcleo operacional grande e funcional. Para apresentação comercial, o que falta não é "mais CRUD", e sim:

- confiança jurídica mínima;
- capacidade de operar temporariamente sem internet em cenários críticos.

Por isso, a priorização correta do MVP de apresentação passa a ser:

1. **fechar primeiro a frente jurídico/LGPD**, porque é menor, mais rápida e reduz risco comercial imediato;
2. **fechar depois o offline real do PDV/Balcão**, com escopo controlado e tecnicamente defensável.

---

## 2. Frente A — Jurídico / LGPD operacional

### 2.1 Objetivo do MVP

Ter o produto com:

- Termos de Uso e Política de Privacidade reais, não mais rascunho;
- aceite versionado e rastreável;
- linguagem clara sobre o papel da PegaTicket no tratamento de dados;
- fluxo mínimo de atendimento LGPD;
- política operacional interna do que pode ser prometido e do que ainda não pode.

### 2.2 Estado atual real

Hoje o projeto já tem:

- rotas públicas `/termos` e `/privacidade`;
- documentos versionados em `legal_documents`;
- registro de aceite em `legal_acceptances`;
- aceite obrigatório no cadastro público;
- UI pública para leitura dos documentos;
- integração do aceite com signup.

Hoje ainda falta:

- substituir o conteúdo marcado como **RASCUNHO** por conteúdo validado;
- consolidar a operação LGPD além do texto legal;
- deixar a comunicação comercial e operacional alinhada.

### 2.3 Escopo que entra no MVP

1. **Substituição dos documentos rascunho por versões revisadas**
   - Termos de Uso
   - Política de Privacidade

2. **Aceite juridicamente apresentável**
   - manter versionamento;
   - manter data/hora;
   - manter vínculo com usuário;
   - manter IP já registrado no backend.

3. **Posicionamento LGPD explícito no produto e na documentação comercial**
   - PegaTicket como **controladora** dos dados da contratante;
   - PegaTicket como **operadora** dos dados que a contratante cadastra sobre seus clientes;
   - indicar canal de contato para solicitações.

4. **Fluxo mínimo operacional LGPD**
   - exportação dos dados da empresa já existente como ponto de partida;
   - procedimento documentado para solicitação de correção/exclusão/anonimização quando cabível;
   - política mínima de retenção e resposta.

5. **Checklist comercial/jurídico interno**
   - o que já pode ser prometido;
   - o que ainda é parcial;
   - o que depende de homologação externa.

### 2.4 Escopo que fica fora deste MVP

- automação completa de portal de atendimento LGPD;
- workflow sofisticado de solicitação com SLA automatizado;
- anonimização self-service para qualquer dado do sistema;
- DPA completo automatizado por tenant;
- assinatura eletrônica avançada de contrato;
- motor jurídico parametrizável.

### 2.5 Entregas concretas

1. revisão do `LegalDocumentsSeeder` para remover texto de rascunho;
2. revisão de textos públicos do cadastro/login para refletir a política final;
3. documento operacional em `docs/` com:
   - base legal operacional;
   - canal de atendimento;
   - procedimento de resposta;
   - limites de exclusão por retenção legal;
4. checklist comercial resumido para apresentação.

### 2.6 Riscos

- **Risco principal:** publicar texto sem revisão jurídica e vender como “100% adequado”.
- **Mitigação:** tratar esta frente como “jurídico operacional mínimo validado”, não como blindagem jurídica absoluta.

### 2.7 Critérios de aceite

Esta frente só pode ser considerada concluída quando:

- `/termos` e `/privacidade` não exibirem mais conteúdo de rascunho;
- o cadastro público continuar exigindo aceite versionado;
- existir documento operacional claro sobre tratamento de solicitações LGPD;
- o discurso comercial do MVP estiver alinhado com o estado real do produto.

---

## 3. Frente B — Offline real do PDV/Balcão

### 3.1 Objetivo do MVP

Permitir demonstrar, com honestidade, que o sistema:

- continua operando **temporariamente** sem internet em cenários críticos;
- registra as operações localmente;
- sincroniza ao reconectar;
- deixa claro para o operador o que foi sincronizado e o que ainda está pendente.

### 3.2 Decisão de escopo do MVP

O MVP **não** será “o sistema inteiro offline”.

O MVP será:

- **PDV offline controlado**
- **Balcão offline controlado**

com foco em:

- abertura da operação já sincronizada;
- uso local do catálogo/configuração já baixados;
- registro local de venda/comanda;
- fila local append-only;
- sincronização automática ou manual ao reconectar;
- bloqueio explícito de fluxos não seguros offline.

### 3.3 Escopo que entra no MVP

#### PDV

1. abrir sessão online e baixar snapshot local:
   - produtos;
   - preços;
   - configurações mínimas;
   - formas de pagamento permitidas offline.

2. registrar venda offline:
   - adicionar item;
   - ajustar quantidade;
   - remover item antes de concluir;
   - fechar venda offline.

3. pagamentos permitidos offline:
   - **dinheiro**
   - opcionalmente “pagamento externo/manual já recebido”

4. pagamentos que **não entram** offline:
   - Pix online;
   - cartão online;
   - qualquer fluxo que dependa de PSP em tempo real.

5. fila local de operações:
   - venda criada;
   - itens incluídos;
   - fechamento;
   - pagamentos manuais permitidos.

6. sincronização:
   - ao reconectar;
   - por ação manual do operador;
   - com indicador visual de pendência.

#### Balcão / restaurante

1. baixar snapshot local:
   - mesas/comandas abertas relevantes para o dispositivo;
   - produtos;
   - roteamento mínimo.

2. lançar item offline em comanda já aberta ou nova comanda local;
3. cancelar item ainda localmente antes da sincronização;
4. sincronizar depois com o servidor;
5. status visual por comanda:
   - offline local;
   - pendente de sincronização;
   - sincronizada;
   - erro de sincronização.

### 3.4 Escopo que fica fora deste MVP

- KDS/cozinha totalmente offline;
- múltiplos dispositivos sincronizando a mesma mesa com resolução complexa de conflito;
- Pix offline;
- cartão offline;
- NFC-e offline/contingência fiscal;
- operação completa de todo o painel administrativo sem internet;
- reconciliação financeira avançada offline;
- ficha técnica/receita com baixa sofisticada offline.

### 3.5 Estratégia técnica recomendada

1. **IndexedDB** como armazenamento local.
2. **Dexie** como camada de acesso local.
3. **fila append-only de comandos**, nunca sincronização por “estado livre”.
4. **reconexão com fallback universal**
   - evento `online`;
   - polling leve enquanto houver pendência;
   - se Background Sync funcionar, usar como complemento, não dependência.
5. **idempotência no backend** por comando.
6. **bloqueio de ações perigosas offline** em vez de simulação enganosa.

### 3.6 Linguagem comercial permitida

Pode prometer:

- “o PDV e o Balcão conseguem continuar operando temporariamente sem internet”;
- “as operações ficam salvas localmente e sincronizam quando a conexão volta”;
- “o operador enxerga claramente o que já subiu e o que ainda está pendente”.

Não pode prometer:

- “o sistema inteiro funciona offline”;
- “Pix e cartão funcionam normalmente sem internet”;
- “cozinha/bar recebem tudo em tempo real mesmo com queda total de conexão”;
- “não existe risco operacional em múltiplos dispositivos totalmente offline”.

### 3.7 Riscos

- **Risco técnico maior do projeto inteiro:** sincronização offline do Balcão.
- **Risco de promessa errada:** vender como “offline total”.
- **Risco operacional:** operador achar que pagamento eletrônico foi concluído sem internet.

Mitigações:

- limitar o escopo do MVP;
- ter estados visuais explícitos;
- não permitir fluxos ambíguos;
- começar pelo PDV e só então expandir o mesmo padrão para Balcão.

### 3.8 Critérios de aceite

Esta frente só pode ser considerada concluída quando:

1. o operador conseguir abrir o módulo com snapshot previamente sincronizado;
2. conseguir registrar venda/comanda sem internet;
3. o sistema mostrar claramente que a operação está pendente de envio;
4. ao reconectar, a sincronização acontecer sem duplicidade;
5. o backend tratar os comandos de forma idempotente;
6. pagamentos eletrônicos indisponíveis offline ficarem claramente bloqueados;
7. houver roteiro de teste de queda/retorno de conexão validado.

---

## 4. Ordem recomendada de execução

### Etapa 1 — Jurídico / LGPD operacional

Prioridade: **máxima**

Entregas:

- conteúdo legal final;
- aceite confirmado;
- documento operacional LGPD;
- checklist comercial.

### Etapa 2 — Offline MVP do PDV

Prioridade: **alta**

Entregas:

- snapshot local;
- venda offline controlada;
- fila local;
- sincronização;
- estados visuais.

### Etapa 3 — Offline MVP do Balcão

Prioridade: **alta**

Entregas:

- lançamento offline controlado de comanda;
- fila local por comanda;
- sincronização;
- feedback visual.

### Etapa 4 — UAT de apresentação

Prioridade: **obrigatória**

Executar demonstração roteirizada com:

- queda simulada de conexão;
- operação local;
- retorno da conexão;
- sincronização;
- checagem do registro no backoffice.

---

## 5. Plano de apresentação do MVP

Quando essas duas frentes estiverem prontas, o posicionamento comercial do MVP passa a ser:

- sistema operacional multiempresa;
- controle de acesso por plano e perfil;
- loja online;
- pedidos, estoque, clientes e relatórios;
- assinatura/plano estruturados;
- base fiscal já preparada;
- documentos legais e aceite rastreável;
- PDV e Balcão com operação temporária sem internet e sincronização posterior.

Este é um MVP forte e defensável.

---

## 6. Próxima recomendação após essas duas frentes

Depois de concluir este plano, a ordem natural volta a ser:

1. homologação financeira real;
2. fiscal oficial;
3. rollout operacional assistido;
4. iFood homologado em ambiente real.
