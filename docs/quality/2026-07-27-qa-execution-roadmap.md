# Roadmap de Execução de QA para Certificação

Data: 2026-07-27

## Fase 1. Fechar base de automação

- corrigir a regressão atual do storefront (`CashbackTest` e `StorefrontCheckoutTest`) removendo a dependência residual de `app('tenant_id')` — concluído em 2026-07-27
- adicionar Playwright ao `web/` — concluído em 2026-07-28
- criar smoke E2E de login, dashboard, troca de empresa e logout — concluído em 2026-07-28
- criar E2E de owner x usuário comum para menus e bloqueios — concluído no primeiro recorte em 2026-07-28, cobrindo ocultação de links sem permissão e governança de assinatura
- criar smoke E2E de navegação mobile e redirecionamento de rota inexistente — concluído em 2026-07-28
- criar E2E de pedido manual — concluído em 2026-07-28
- criar E2E de pedido da loja — concluído em 2026-07-28
- criar E2E mínimo de assinatura — concluído em 2026-07-28 no fluxo inicial de contratação
- criar E2E de checkout comercial

## Fase 2. Blindar interface crítica

- adicionar Vitest + React Testing Library
- cobrir componentes de autorização, grids, banners de erro, formulários de autenticação e checkout
- eliminar warnings atuais de lint
- transformar a suíte Playwright inicial em gate obrigatório de CI antes do deploy — concluído em 2026-07-28

## Fase 3. Certificar operação

- homologar PDV, balcão, reservas, fila, drag and drop e offline com cenários reais
- validar tempos de resposta em massa grande
- validar fluxo mobile-first nas telas operacionais

## Fase 4. Certificar integrações

- assinatura e webhooks
- pagamentos reais de homologação
- marketplace
- fiscal configurável, quando ativado

## Fase 5. Fechar produção

- smoke pós-deploy
- smoke pós-deploy para URL publicada via `PLAYWRIGHT_BASE_URL` — concluído em 2026-07-28
- checklist manual de release
- rollback documentado
- monitoramento ativo nas primeiras 48h

## Fechamento desta rodada

- backend validado em 2026-07-28 com `1129 passed` e `4491 assertions`
- frontend validado em 2026-07-28 com `npm run lint` sem warnings, `npm run build` verde e `npm run test:e2e` verde
- suíte E2E atual fechada novamente em 2026-07-28 com `13 passed`, `1 skipped (credenciais reais opcionais)`, `31.8s`
- deploy agora barrado automaticamente se lint, build ou E2E do `web/` falharem
