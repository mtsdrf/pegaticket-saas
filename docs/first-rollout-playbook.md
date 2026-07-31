# First Rollout Playbook

Data de referência: 2026-07-10

## Objetivo

Disponibilizar o Maskats pela primeira vez com:

- um **administrador máximo da plataforma**
- um **tenant novo** provisionado corretamente
- usuários do cliente com acesso apenas ao que pertence ao próprio tenant
- sem acesso do cliente aos módulos administrativos globais da plataforma

## Modelo de acesso atual

O backend trabalha em modo híbrido:

- rotas globais usam `GroupPermission`
- rotas tenant-scoped usam `tenant` + `GroupPermission` **ou** `TenantRolePermission`

Na prática isso significa:

- a equipe interna usa o grupo global `administrators`
- usuários do cliente usam o grupo global `clients`
- o acesso operacional do dia a dia do cliente deve ser controlado por `TenantRolePermission`

Além disso:

- `users` foi contido ao tenant ativo para usuários que **não** pertencem ao grupo `administrators`
- `tenant_users` foi endurecido para listar e mutar apenas registros do tenant ativo

## Seed inicial recomendado

### Grupo 1: `administrators`

Uso:

- seu usuário
- outros administradores internos da plataforma

Permissões:

- todas as funcionalidades
- todas as ações

Pode:

- administrar usuários globais
- administrar grupos
- administrar funcionalidades
- administrar tenants
- administrar qualquer tenant do sistema

### Grupo 2: `clients`

Uso:

- todos os usuários que pertencem a clientes/tenants

Permissões globais mínimas:

- `users`: `read`, `create`, `update`, `delete`
- `tenant_roles`: `read`, `create`, `update`, `delete`
- `tenant_users`: `read`, `create`, `update`, `delete`

Objetivo:

- permitir que o cliente administre seus próprios usuários
- permitir que o cliente administre os perfis do próprio tenant
- permitir que o cliente vincule usuário ao perfil do tenant

Restrições:

- não deve receber `groups`
- não deve receber `functionalities`
- não deve receber `tenants`
- não deve receber catálogos globais como `estados`, `cidades`, `bairros`

## Matriz de acesso recomendada

### Plataforma global

Somente `administrators`:

| Funcionalidade | Ações |
| --- | --- |
| `users` | `read`, `create`, `update`, `delete` |
| `groups` | `read`, `create`, `update`, `delete` |
| `functionalities` | `read`, `create`, `update`, `delete` |
| `tenants` | `read`, `create`, `update`, `delete` |
| `estados` | `read`, `create`, `update`, `delete` |
| `cidades` | `read`, `create`, `update`, `delete` |
| `bairros` | `read`, `create`, `update`, `delete` |

### Auto-administração do tenant

Grupo global `clients`:

| Funcionalidade | Ações |
| --- | --- |
| `users` | `read`, `create`, `update`, `delete` |
| `tenant_roles` | `read`, `create`, `update`, `delete` |
| `tenant_users` | `read`, `create`, `update`, `delete` |

### Operação do tenant

Acesso controlado por `TenantRolePermission`.

#### Perfil `Owner`

- acesso total aos módulos do tenant

#### Perfil `Manager`

| Funcionalidade | Ações |
| --- | --- |
| `client_categories` | `read`, `create`, `update`, `delete` |
| `product_categories` | `read`, `create`, `update`, `delete` |
| `product_types` | `read`, `create`, `update`, `delete` |
| `enderecos` | `read`, `create`, `update`, `delete` |
| `dias_ideais` | `read`, `create`, `update`, `delete` |
| `periodos_ideais` | `read`, `create`, `update`, `delete` |
| `clients` | `read`, `create`, `update`, `delete` |
| `products` | `read`, `create`, `update`, `delete` |
| `stock_locations` | `read`, `create`, `update`, `delete` |
| `stock` | `read`, `entry`, `exit`, `adjustment`, `transfer`, `block`, `reserve`, `view_costs` |
| `orders` | `read`, `create`, `deliver`, `pay`, `cancel` |
| `reports` | `read`, `export_pdf` |

#### Perfil `Sales`

| Funcionalidade | Ações |
| --- | --- |
| `clients` | `read`, `create`, `update` |
| `enderecos` | `read`, `create`, `update` |
| `products` | `read` |
| `stock` | `read` |
| `orders` | `read`, `create` |
| `reports` | `read` |

#### Perfil `Warehouse`

| Funcionalidade | Ações |
| --- | --- |
| `products` | `read` |
| `stock_locations` | `read` |
| `stock` | `read`, `entry`, `exit`, `adjustment`, `transfer`, `block`, `reserve` |
| `orders` | `read`, `deliver` |

#### Perfil `Finance`

| Funcionalidade | Ações |
| --- | --- |
| `clients` | `read` |
| `orders` | `read`, `pay`, `cancel` |
| `reports` | `read`, `export_pdf` |

#### Perfil `Viewer`

| Funcionalidade | Ações |
| --- | --- |
| `clients` | `read` |
| `products` | `read` |
| `stock` | `read` |
| `orders` | `read` |
| `reports` | `read` |

## Fluxo de implantação

### Etapa 0. Validar baseline

1. rodar seeders base
2. confirmar existência dos grupos:
   - `administrators`
   - `clients`
3. confirmar existência das funcionalidades:
   - `tenants`
   - `tenant_roles`
   - `tenant_users`
4. confirmar existência das actions:
   - `read`, `create`, `update`, `delete`
   - `entry`, `exit`, `adjustment`, `transfer`, `block`, `reserve`
   - `view_costs`, `deliver`, `pay`, `cancel`, `export_pdf`

### Etapa 1. Provisionar o administrador máximo

1. criar ou validar seu usuário interno principal
2. vincular esse usuário ao grupo `administrators`
3. usar esse usuário para a implantação inicial

### Etapa 2. Criar o tenant do cliente

1. entrar com o administrador máximo
2. ir em `Administração > Tenants`
3. criar o tenant

O backend já provisiona automaticamente:

- role `Owner`
- vínculo `TenantUser` do usuário criador
- todas as `TenantRolePermission` do `Owner`

### Etapa 3. Trocar para o tenant

1. selecionar o tenant novo na sidebar
2. validar que o tenant está ativo
3. validar que o usuário atual possui vínculo ativo com o tenant

### Etapa 4. Preparar os perfis do tenant

Dentro do tenant:

1. revisar o `Owner`
2. criar os perfis:
   - `Manager`
   - `Sales`
   - `Warehouse`
   - `Finance`
   - `Viewer`
3. ajustar as permissões de cada perfil em `tenant-role-permissions`

### Etapa 5. Criar usuários do cliente

Para cada usuário do cliente:

1. criar o usuário em `Administração > Usuários`
2. o backend deve manter esse fluxo contido ao tenant ativo para usuários não-admin
3. vincular o usuário ao grupo global `clients`
4. vincular o usuário ao tenant em `Administração > Usuários do tenant`
5. associar o `TenantRole` correto

### Etapa 6. Cadastrar dados-base

Ordem recomendada:

1. `Categorias de cliente`
2. `Dias ideais`
3. `Períodos ideais`
4. `Categorias de produto`
5. `Tipos de produto`
6. `Produtos`
7. `Locais de estoque`
8. `Clientes`

### Etapa 7. Teste operacional mínimo

Validar com um usuário do cliente:

1. login
2. troca de tenant
3. acesso a `Usuários`, `Perfis do tenant` e `Usuários do tenant` apenas do próprio tenant
4. criação de cliente
5. criação de produto
6. entrada de estoque
7. criação de pedido
8. entrega/pagamento conforme perfil
9. relatório + exportação PDF

### Etapa 8. Teste de restrição

Validar com o usuário do cliente que ele **não** consegue:

1. abrir `Administração > Grupos`
2. abrir `Administração > Funcionalidades`
3. abrir `Administração > Tenants`
4. visualizar ou alterar usuários fora do tenant ativo
5. visualizar ou alterar vínculos `tenant_users` de outros tenants
6. alterar catálogos globais (`Estados`, `Cidades`, `Bairros`) se essas telas forem expostas no futuro

## Regra operacional recomendada

- equipe interna fica em `administrators`
- usuários do cliente ficam em `clients`
- o acesso fino do negócio deve ser controlado em `TenantRolePermission`
- evitar criar grupos globais adicionais para clientes, a menos que haja motivo muito claro

## Riscos remanescentes

- a navegação do frontend ainda não é 100% permission-aware; parte das telas pode aparecer e bloquear por permissão ao entrar
- o modelo de `users` continua sendo global na base de dados, mesmo com escopo de acesso controlado no backend
- vale fazer testes reais com 2 tenants diferentes e 2 usuários clientes distintos antes de produção final
