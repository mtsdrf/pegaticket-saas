# Playbook operacional de privacidade e LGPD do PegaTicket

Data de referência: **26 de julho de 2026**

Este documento organiza a operação mínima de privacidade do PegaTicket para o MVP comercial. Ele não substitui revisão jurídica formal, mas define o que o time pode executar e comunicar com segurança no estado atual do produto.

## 1. Papel da PegaTicket no tratamento de dados

- **Controladora** dos dados do relacionamento comercial com a própria empresa contratante e seus usuários de acesso.
- **Operadora** dos dados pessoais que a empresa contratante cadastra e trata dentro do sistema sobre clientes, contatos, destinatários, equipe, vendas e rotinas operacionais.

## 2. O que já existe no produto

- Termos de Uso públicos em `/termos`.
- Política de Privacidade pública em `/privacidade`.
- Aceite versionado e registrado no cadastro público.
- Registro de aceite com usuário, documento aceito, data/hora e IP.
- Exportação dos dados principais da empresa em `Configurações > Dados e Privacidade`.
- Trilhas de auditoria e logs operacionais em fluxos críticos do sistema.

## 3. Fluxo mínimo para solicitações de privacidade

### 3.1 Solicitações da própria empresa contratante

Exemplos:

- acesso aos dados da conta;
- exportação dos dados da empresa;
- correção de cadastro da conta;
- cancelamento da assinatura;
- dúvida sobre retenção e armazenamento.

Fluxo:

1. validar quem está solicitando e se possui legitimidade;
2. orientar primeiro o uso de `Configurações > Dados e Privacidade` quando o objetivo for exportação;
3. registrar internamente a data, o solicitante, o escopo e o retorno dado;
4. responder pelos canais oficiais da operação.

### 3.2 Solicitações de titulares finais cadastrados pela empresa

Exemplos:

- cliente final pedindo acesso, correção ou exclusão dos dados dele;
- venda de anonimização de registro operacional;
- dúvida sobre base legal de tratamento.

Fluxo:

1. orientar que o venda seja tratado em conjunto com a **empresa contratante**, pois ela é a controladora principal desses dados;
2. validar com a empresa o escopo do atendimento;
3. executar ação operacional possível no sistema ou apoiar a exportação necessária;
4. registrar a evidência mínima do atendimento.

## 4. Respostas operacionais permitidas no estado atual

- **Acesso/exportação:** já suportado pelo sistema para o conjunto principal de dados da empresa.
- **Correção:** pode ser tratada diretamente nos cadastros do sistema por usuários autorizados.
- **Exclusão:** deve ser analisada caso a caso, porque pode existir retenção legal, fiscal, contábil, contratual, antifraude ou necessidade de preservação de evidência operacional.
- **Anonimização:** ainda não é self-service geral; hoje depende de avaliação e implementação orientada por caso.

## 5. Regras de retenção e limites

- Dados operacionais, financeiros, fiscais, contratuais e de auditoria podem precisar ser mantidos por obrigação legal, regulatória ou para exercício regular de direitos.
- Um venda de exclusão não implica remoção imediata ou irrestrita de todo dado.
- Quando a exclusão integral não for possível, a resposta deve explicar o motivo operacional ou legal aplicável.

## 6. O que o time comercial pode prometer

- o sistema possui Termos de Uso e Política de Privacidade públicos;
- o cadastro registra aceite versionado;
- a empresa consegue exportar os dados principais do próprio ambiente;
- existe processo operacional para tratamento de solicitações de privacidade;
- a PegaTicket diferencia o papel de controladora e operadora.

## 7. O que o time comercial não deve prometer

- “adequação LGPD absoluta”;
- anonimização automática completa de qualquer dado;
- exclusão irrestrita de todo histórico;
- DPA automatizado por empresa;
- portal automatizado completo de requisições do titular.

## 8. Checklist interno antes de ativar uma empresa

1. confirmar que os documentos legais públicos estão acessíveis;
2. confirmar que o cadastro público está exigindo os dois aceites;
3. confirmar que a empresa consegue acessar `Dados e Privacidade`;
4. confirmar que o responsável operacional sabe orientar exportação, correção e limitações de exclusão;
5. confirmar que qualquer venda fora do fluxo atual será tratado como atendimento assistido, não como promessa de automação.
