# Maskats — Adendo de auditoria estrutural

Data: 27 de julho de 2026

## 1. Objetivo

Este documento complementa o levantamento funcional anterior com uma segunda passagem orientada a:

- brechas estruturais;
- inconsistências entre conceito e implementação;
- fragilidades de acesso, tenancy e governança;
- acoplamentos implícitos entre serviços;
- pontos que tendem a gerar bugs silenciosos.

Escopo real analisado nesta rodada:

- `api/routes/api.php`
- middlewares de autenticação/autorização/tenant
- serviços de assinatura, checkout público, usuários e onboarding
- `web/src/routes/AppRoutes.tsx`
- `web/src/contexts/AuthContext.tsx`
- fluxo visual do dashboard/onboarding
- relação entre `User`, `TenantUser`, permissões e plano

Este adendo descreve o **estado observado na auditoria** e serviu de base para a rodada seguinte de correções estruturais concluída ainda em **27 de julho de 2026**.

## 1.1 Implementação derivada desta auditoria

Os seguintes pontos deste adendo já foram atacados em código na rodada imediatamente posterior:

- owner-only de assinatura no backend:
  - middleware [api/app/Http/Middleware/EnsureTenantOwner.php](/home/mtsdrf/workspace/maskats-saas/api/app/Http/Middleware/EnsureTenantOwner.php)
  - rotas `/api/v1/subscription*` protegidas por `tenant.owner` em [api/routes/api.php](/home/mtsdrf/workspace/maskats-saas/api/routes/api.php)
- endurecimento das rotas de PIN/sessão do PDV:
  - `pdv/operator-pin` e `pdv/operator-session` agora exigem `perm:pdv,read`
- criação unificada de usuário da empresa:
  - backend aceita `user_uuid` ou `user { name, email, password }` em uma única operação
  - frontend passou a expor esse fluxo em [web/src/pages/Admin/TenantUserFormPage.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/pages/Admin/TenantUserFormPage.tsx)
- resiliência do `AuthContext`:
  - falha transitória de perfil/empresas não limpa mais estado válido por engano
- checklist de implantação:
  - dispensa persistida por `tenant_user`
  - passos modulados por plano/funcionalidade
- contexto de tenant no checkout público:
  - encapsulado em [api/app/Services/Tenant/TenantExecutionContext.php](/home/mtsdrf/workspace/maskats-saas/api/app/Services/Tenant/TenantExecutionContext.php)

Validação da implementação:

- `cd api && php artisan test --filter='(SubscriptionAccessGateTest|SubscriptionEndpointTest|OnboardingChecklistTest|OperatorPinTest|TenantUserCreateTest|TenantUserPermissionsTest)'`
- `cd web && npm run build`

## 2. Resumo executivo

O Maskats está com a fundação geral madura, mas esta segunda auditoria encontrou um conjunto pequeno e importante de problemas estruturais que hoje representam mais risco do que bugs visuais isolados.

Os pontos mais críticos são:

1. governança de assinatura ainda não está protegida no backend como operação exclusiva do proprietário da empresa;
2. existem rotas tenant-scoped fora do middleware `perm`, o que também as tira do gate de plano e do bloqueio por assinatura suspensa;
3. o fluxo de criação de usuário da empresa continua conceitualmente quebrado em duas entidades (`User` global + `TenantUser`), o que facilita usuários órfãos e onboarding administrativo confuso;
4. o frontend degrada a sessão de acesso em falhas transitórias de rede, fazendo permissões “sumirem” sem logout real;
5. o checkout público depende de bindings globais de container (`app('tenant_id')`) para funcionar, criando um acoplamento implícito frágil entre serviços.

## 3. Achados estruturais

### 3.1 Crítico — ações de assinatura não são owner-only no backend

Estado observado:

- as rotas de assinatura exigem apenas `tenant + perm:subscription,*` em [api/routes/api.php](/home/mtsdrf/workspace/maskats-saas/api/routes/api.php:1499)
- o `SubscriptionController` opera direto sobre `app('tenant_id')`, sem verificar se o ator é o proprietário em [api/app/Http/Controllers/Subscription/SubscriptionController.php](/home/mtsdrf/workspace/maskats-saas/api/app/Http/Controllers/Subscription/SubscriptionController.php:66)
- o `SubscriptionService` implementa regras transacionais e de estado, mas não faz validação de papel do ator em [api/app/Services/Subscription/SubscriptionService.php](/home/mtsdrf/workspace/maskats-saas/api/app/Services/Subscription/SubscriptionService.php:39)

Risco:

- qualquer usuário com `subscription:update` pode cancelar, renovar, trocar plano ou trocar cartão da empresa;
- a UI já trata essa área como especial do proprietário, mas o backend ainda não garante isso;
- isso é vulnerabilidade de governança, não só problema de UX.

Correção recomendada:

- criar um gate server-side explícito para “governança de assinatura”;
- permitir leitura ampla apenas se desejado, mas restringir `store`, `cancel`, `withdrawal`, `renew`, `change-plan` e `payment-method` ao owner;
- adicionar testes negativos cobrindo usuário com permissão operacional mas sem papel `owner`.

### 3.2 Crítico — rotas tenant-scoped fora de `perm` escapam do gate de plano e do bloqueio por assinatura

Estado observado:

- o middleware `CheckPermission` concentra:
  - autorização funcional;
  - gate de plano;
  - bloqueio por assinatura suspensa/cancelada
- isso está explícito em [api/app/Http/Middleware/CheckPermission.php](/home/mtsdrf/workspace/maskats-saas/api/app/Http/Middleware/CheckPermission.php:15)
- mas há rotas com apenas `tenant`:
  - checklist: [api/routes/api.php](/home/mtsdrf/workspace/maskats-saas/api/routes/api.php:834)
  - `pdv/operator-pin`: [api/routes/api.php](/home/mtsdrf/workspace/maskats-saas/api/routes/api.php:1300)
  - `pdv/operator-session`: [api/routes/api.php](/home/mtsdrf/workspace/maskats-saas/api/routes/api.php:1303)

Risco:

- essas rotas não passam pelo gate comercial;
- também não passam pelo bloqueio `SUBSCRIPTION_SUSPENDED`;
- no caso do PDV, um tenant sem módulo ou com assinatura bloqueada pode continuar operando a camada de PIN/sessão do operador;
- o comportamento final fica conceitualmente inconsistente: parte do módulo é bloqueada e outra parte continua viva.

Correção recomendada:

- decidir explicitamente quais dessas rotas são exceção de negócio e por quê;
- se não forem exceção formal, alinhá-las ao mesmo gate do restante do módulo;
- no caso do checklist, escolher conscientemente se ele deve continuar acessível sob assinatura suspensa;
- no caso do PIN/operador, adicionar pelo menos gate coerente com `pdv`.

### 3.3 Alto — modelo `User` global + `TenantUser` separado ainda favorece usuários órfãos e fluxo administrativo quebrado

Estado observado:

- `UserService::create()` cria um `User` global e, no modo tenant-scoped, só o coloca no grupo `clients`, sem vinculá-lo automaticamente à empresa em [api/app/Services/User/UserService.php](/home/mtsdrf/workspace/maskats-saas/api/app/Services/User/UserService.php:102)
- o vínculo real com a empresa acontece depois, em outro fluxo, via `TenantUserFormPage` em [web/src/pages/Admin/TenantUserFormPage.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/pages/Admin/TenantUserFormPage.tsx:30)
- o próprio `UserService::paginate()` já mascara isso ao listar só usuários vinculados ao tenant quando não é administrador, em [api/app/Services/User/UserService.php](/home/mtsdrf/workspace/maskats-saas/api/app/Services/User/UserService.php:68)

Risco:

- empresa cria usuário, mas se o vínculo `TenantUser` falhar ou for esquecido, nasce um usuário global órfão;
- a entidade “usuário da empresa” continua distribuída em dois CRUDs e dois conceitos;
- isso tende a gerar suporte, inconsistência operacional e falsa sensação de cadastro concluído.

Correção recomendada:

- tratar “criar usuário da empresa” como caso de uso único:
  - criar `User`
  - criar `TenantUser`
  - atribuir `TenantRole`
  - tudo em uma transação
- deixar o CRUD global `User` restrito à plataforma;
- usar o fluxo tenant-facing apenas para identidade operacional da própria empresa.

### 3.4 Alto — o frontend derruba o perfil de acesso em qualquer falha transitória

Estado observado:

- `loadAccessProfile()` zera `accessProfile` em qualquer erro em [web/src/contexts/AuthContext.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/contexts/AuthContext.tsx:21)
- `loadTenants()` também limpa a lista em qualquer falha em [web/src/contexts/AuthContext.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/contexts/AuthContext.tsx:45)
- isso não distingue:
  - token inválido;
  - timeout;
  - falha momentânea da API;
  - oscilação de rede.

Risco:

- menus, permissões e empresa ativa podem “sumir” sem logout real;
- o usuário entra em estado visual de acesso negado por falha temporária, não por autorização;
- isso aumenta bug fantasma e dificulta diagnóstico de incidentes de rede.

Correção recomendada:

- manter o último `accessProfile` válido em falhas transitórias;
- limpar estado só quando houver 401/refresh realmente inválido;
- separar “erro de autorização” de “erro de disponibilidade” no provider de autenticação;
- adicionar teste de comportamento para sessão preservada em erro temporário de `/auth/access-profile`.

### 3.5 Alto — checkout público depende de binding global manual de tenant

Estado observado:

- o checkout público injeta `app('tenant_id')` manualmente antes de chamar serviços internos em [api/app/Services/Storefront/StorefrontCheckoutService.php](/home/mtsdrf/workspace/maskats-saas/api/app/Services/Storefront/StorefrontCheckoutService.php:58)
- o próprio comentário admite que vários serviços internos dependem desse binding de container;
- o restante do sistema normalmente obtém esse contexto pelo middleware `ResolveTenant`.

Risco:

- o fluxo funciona hoje porque os serviços atuais leem `tenant_id`, mas qualquer evolução que passe a depender também de `tenant`, `tenant_role` ou outro binding quebrará de forma indireta;
- o contrato entre serviços fica implícito e invisível;
- aumenta chance de regressão ao reaproveitar serviços de domínio em fluxos públicos, jobs ou comandos.

Correção recomendada:

- reduzir dependência de estado global do container nos serviços de domínio;
- preferir `tenantId` explícito nos casos públicos e em jobs;
- onde o binding for inevitável, encapsular isso em um contexto de execução único e bem definido, em vez de espalhar `app()->instance(...)`.

### 3.6 Médio — onboarding continua acoplado ao navegador e ainda mascara falhas

Estado observado:

- a dispensa do checklist ainda vive em `localStorage` em [web/src/utils/onboardingChecklistStorage.ts](/home/mtsdrf/workspace/maskats-saas/web/src/utils/onboardingChecklistStorage.ts:1)
- o hook engole qualquer erro e simplesmente oculta o card em [web/src/hooks/useOnboardingChecklist.ts](/home/mtsdrf/workspace/maskats-saas/web/src/hooks/useOnboardingChecklist.ts:29)

Risco:

- o estado não é por empresa nem por usuário;
- trocar de navegador ou de colaborador muda a experiência;
- uma falha da API pode parecer “checklist concluído” quando, na verdade, ele só desapareceu.

Correção recomendada:

- persistir dispensa no backend com escopo explícito;
- manter o fallback silencioso só para indisponibilidade leve, mas registrar observabilidade;
- exibir modo degradado rastreável quando o checklist não puder ser carregado.

### 3.7 Médio — o checklist de implantação continua sem refletir a topologia real de módulos

Estado observado:

- `OnboardingService::checklist()` ainda usa apenas:
  - produto
  - cliente
  - endereço da loja
  - configuração de storefront
  - primeiro pedido
- implementação em [api/app/Services/Onboarding/OnboardingService.php](/home/mtsdrf/workspace/maskats-saas/api/app/Services/Onboarding/OnboardingService.php:21)

Risco:

- o produto hoje já tem módulos independentes como PDV, Balcão, marketplace, contador, fiscal interno, assinatura e reservas;
- o checklist segue representando só um recorte parcial da implantação;
- empresas podem parecer “prontas” sem estarem prontas para o módulo que realmente contrataram.

Correção recomendada:

- tornar o checklist modular por plano e funcionalidades ativas;
- separar “implantação mínima da empresa” de “ativação do módulo contratado”;
- usar a mesma base depois para health score de implantação.

### 3.8 Médio — backend e frontend já escondem muito por permissão, mas ainda existe fragmentação entre “bloquear rota” e “deixar API negar”

Estado observado:

- o menu lateral já filtra itens por `can(...)` em [web/src/layouts/AppLayout.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/layouts/AppLayout.tsx:262)
- as rotas principais usam `PermissionRoute` em [web/src/routes/AppRoutes.tsx](/home/mtsdrf/workspace/maskats-saas/web/src/routes/AppRoutes.tsx:376)
- porém a proteção continua distribuída entre:
  - ocultação de navegação;
  - redirecionamento por rota;
  - erro vindo da API;
  - exceções manuais como owner bypass.

Risco:

- a experiência de autorização fica correta na maior parte do tempo, mas menos previsível em telas novas;
- o time pode continuar introduzindo diferenças entre “não mostro”, “redireciono” e “deixo carregar para a API negar”.

Correção recomendada:

- documentar uma matriz única de comportamento para tela sem acesso:
  - esconder menu;
  - bloquear rota;
  - impedir ação;
  - nunca depender só do erro da API para UX primária.

## 4. Lacunas de teste derivadas desta auditoria

Os testes automatizados do projeto já cobrem muita coisa, mas esta rodada deixou lacunas bem específicas:

- falta teste de owner-only para mutações de assinatura;
- falta teste provando o comportamento desejado das rotas `tenant` sem `perm` sob plano bloqueado e assinatura suspensa;
- falta teste do fluxo tenant-facing de criação de usuário completo em uma operação única, porque esse fluxo ainda não existe;
- falta teste frontend para preservar sessão/permissões em falha transitória de `/auth/access-profile`;
- falta teste de integração que proteja o checkout público contra novos serviços que passem a exigir mais contexto do que `tenant_id`.

## 5. Priorização recomendada

### Bloco 1 — segurança e governança

1. fechar owner-only de assinatura no backend;
2. revisar rotas tenant sem `perm`;
3. alinhar gate de PDV/PIN/sessão com plano e assinatura.

### Bloco 2 — consistência de identidade e tenancy

1. unificar o caso de uso “criar usuário da empresa”;
2. reduzir criação de usuários globais órfãos;
3. revisar acoplamentos por `app('tenant_id')`.

### Bloco 3 — resiliência de UX operacional

1. impedir que o `AuthContext` perca permissões por falha transitória;
2. mover dispensa do onboarding para backend;
3. tornar checklist realmente modular por plano/módulo.

## 6. Conclusão

O Maskats não está com problema de arquitetura “quebrada”; o cenário observado é mais sutil:

- a base é ampla e funcional;
- os módulos conversam razoavelmente bem;
- a maior parte dos bugs de agora tende a nascer não por ausência de recurso, mas por **inconsistência entre conceito, escopo e exceções**.

Por isso, a melhor estratégia neste momento é:

1. corrigir governança e gates;
2. fechar os fluxos onde identidade e empresa ainda estão partidas;
3. endurecer o comportamento do frontend em falhas transitórias;
4. só então seguir empilhando novas funcionalidades operacionais.
