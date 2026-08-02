# Self-Service Tenant Onboarding

## Objetivo

Permitir que o próprio cliente implante um novo tenant do zero, sem suporte operacional, já entrando no sistema com:

- um usuário proprietário inicial
- um tenant ativo
- um papel `Owner`
- permissões tenant-scoped coerentes com o plano escolhido

O fluxo deve manter separação rígida entre:

- acesso global de plataforma
- acesso interno do tenant
- limite comercial imposto pelo plano

## Resultado esperado da v1

Ao finalizar o cadastro público:

1. o usuário proprietário é criado e fica ativo
2. o usuário é vinculado ao grupo global `clients`
3. o tenant é criado com o `plan_id` selecionado
4. o tenant recebe o papel `Owner`
5. o proprietário é vinculado ao tenant com esse papel
6. o `Owner` recebe todas as ações apenas das funcionalidades liberadas no plano
7. a sessão já é aberta com token escopado ao tenant criado

## Modelo de acesso que vale no projeto hoje

### Camada global

Permissões globais são concedidas por `GroupPermission`.

Exemplos de funcionalidades globais:

- `groups`
- `functionalities`
- `plans`
- `tenants`

Um tenant recém-cadastrado não deve acessar isso, porque o usuário entra no grupo `clients`, não em `administrators`.

### Camada do tenant

Permissões internas do tenant são concedidas por `TenantRolePermission`.

Exemplos:

- `users`
- `tenant_roles`
- `tenant_users`
- `clients`
- `products`
- `stock`
- `sales`
- `reports`

As rotas tenant-scoped passam pelo middleware `tenant` e aceitam:

- permissão global via grupo
- ou permissão local via papel do tenant

### Camada comercial

Mesmo que o papel do tenant tenha a permissão funcional, o middleware ainda bloqueia a funcionalidade se o plano do tenant não a liberar.

Isso é o último guard-rail do modelo.

## Regras de provisionamento

### Grupo global do usuário recém-cadastrado

O proprietário criado no self-signup entra no grupo `clients`.

Consequência:

- não enxerga cadastros globais da plataforma
- não administra outros tenants
- não administra planos
- não administra grupos globais

### Papel `Owner` do tenant

O `Owner` é um papel interno do tenant e deve ser criado automaticamente.

Regra da v1:

- recebe todas as ações disponíveis (`read`, `create`, `update`, `delete` e outras ativas)
- somente para as funcionalidades liberadas no plano do tenant

### Funcionalidades mínimas obrigatórias por plano

Para o tenant ser autossuficiente, todo plano comercial que possa ser vendido por self-signup deve incluir no mínimo:

- `users`
- `tenant_roles`
- `tenant_users`

Sem isso o proprietário não consegue montar sua própria equipe nem delegar acesso.

Hoje o seeder inicial já respeita isso para:

- `basic`
- `professional`
- `premium`

## Fluxo funcional

### Etapa 1. Exposição dos planos públicos

O frontend consulta os planos ativos disponíveis para cadastro.

Campos mínimos exibidos:

- nome
- descrição
- ordem comercial

### Etapa 2. Cadastro do proprietário

Campos mínimos:

- nome do proprietário
- e-mail
- senha
- confirmação de senha

### Etapa 3. Cadastro da empresa

Campos mínimos:

- nome da empresa
- slug técnico único do tenant
- plano inicial

### Etapa 4. Provisionamento transacional

Tudo deve acontecer na mesma transação:

1. criar usuário
2. vincular grupo `clients`
3. criar tenant
4. criar papel `Owner`
5. vincular proprietário ao tenant
6. sincronizar permissões do `Owner` pelo plano
7. emitir sessão autenticada já com `tenant_uuid`

### Etapa 5. Primeiro acesso

No primeiro acesso o proprietário já pode:

- cadastrar usuários do próprio tenant
- criar perfis internos
- vincular usuários aos perfis
- operar somente os módulos permitidos pelo plano

## Requisitos não funcionais

### Segurança

- endpoint público com `throttle`
- validação de unicidade de `users.email`
- validação de unicidade de `tenants.slug`
- senha mínima de 8 caracteres
- token emitido já escopado ao tenant criado
- refresh token deve preservar o escopo do tenant quando o token atual for renovado

### Consistência

- criação transacional
- auditoria dos eventos de criação
- sincronização do `Owner` sempre baseada no plano ativo

### Performance

- listagem pública de planos sem joins pesados
- provisionamento do `Owner` em lote, não registro a registro em loops Eloquent quando possível

## Direitos de acesso esperados por perfil

### Administrador de plataforma

Grupo global `administrators`.

Pode:

- administrar usuários globais
- administrar grupos globais
- administrar funcionalidades
- administrar planos
- criar e manter tenants de terceiros
- alternar tenant quando vinculado

### Proprietário de tenant via self-signup

Grupo global `clients` + papel local `Owner`.

Pode:

- operar o próprio tenant
- criar usuários internos do próprio tenant
- criar perfis internos do próprio tenant
- delegar permissões internas
- usar apenas funcionalidades liberadas no plano

Não pode:

- ver outros tenants
- ver usuários globais de outros tenants
- administrar grupos globais
- administrar planos
- administrar catálogo global de funcionalidades

## Critérios de aceite da v1

- existe endpoint público para listar planos de cadastro
- existe endpoint público para criar usuário + tenant
- o login pós-cadastro já entra com tenant selecionado
- o `Owner` não nasce com acesso fora do plano
- um usuário self-service não acessa rotas globais administrativas
- o refresh de sessão não perde o `tenant_uuid`

## Próximos passos recomendados

1. criar `onboarding checklist` pós-cadastro com tarefas guiadas
2. permitir escolha de módulos extras por plano/comercial
3. adicionar billing real e política de trial
4. criar ativação opcional por e-mail
5. expandir o onboarding com checklist guiado pós-cadastro e documentação operacional de privacidade por empresa
