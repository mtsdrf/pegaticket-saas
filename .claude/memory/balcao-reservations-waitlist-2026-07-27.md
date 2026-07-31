---
name: balcao-reservations-waitlist-2026-07-27
description: Implementação de reservas de mesa e fila de espera no Balcão + reserva online pública.
metadata:
  type: feature
  date: 2026-07-27
---

# Balcão: reservas e fila de espera

## Escopo entregue
- Reserva interna de mesa com escolha automática ou manual da mesa compatível.
- Validação de conflito de horário por mesa e capacidade mínima por quantidade de pessoas.
- Seating da reserva abrindo comanda automaticamente.
- Marcação de `no_show` e cancelamento com motivo.
- Fila de espera interna com `waiting`, `called`, `seated` e `cancelled`.
- Seating da fila abrindo comanda automaticamente e vinculando a mesa escolhida.
- Reserva online pública via storefront em `/loja/:slug/reservas`.
- Reserva pública desacoplada da loja online em `/reservas/:slug`, permitindo operação de reservas mesmo sem módulo de delivery.

## Backend
- Migrations:
  - [api/database/migrations/2026_07_27_090000_create_table_reservations_table.php](/home/mtsdrf/workspace/pegaticket-saas/api/database/migrations/2026_07_27_090000_create_table_reservations_table.php)
  - [api/database/migrations/2026_07_27_090100_create_table_waitlists_table.php](/home/mtsdrf/workspace/pegaticket-saas/api/database/migrations/2026_07_27_090100_create_table_waitlists_table.php)
- Models:
  - [api/app/Models/Balcao/TableReservation.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Models/Balcao/TableReservation.php)
  - [api/app/Models/Balcao/TableWaitlist.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Models/Balcao/TableWaitlist.php)
- Services:
  - [api/app/Services/Balcao/TableAvailabilityService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Balcao/TableAvailabilityService.php)
  - [api/app/Services/Balcao/TableReservationService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Balcao/TableReservationService.php)
  - [api/app/Services/Balcao/TableWaitlistService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Balcao/TableWaitlistService.php)
- Controllers/Requests/Resources novos no namespace `Balcao` e `Storefront`.
- Ajuste em [api/app/Services/Balcao/ComandaService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Balcao/ComandaService.php): comanda pode ocupar mesa `reserved`.

## Rotas novas
- Staff/tenant:
  - `/api/v1/balcao/reservas`
  - `/api/v1/balcao/reservas/disponibilidade`
  - `/api/v1/balcao/fila-espera`
- Pública:
  - `/api/v1/reservas/{slug}`
  - `/api/v1/loja/{slug}/reservas`

## Frontend
- Tipos e service contracts adicionados em:
  - [web/src/types/balcao.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/types/balcao.ts)
  - [web/src/services/balcaoService.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/services/balcaoService.ts)
  - [web/src/types/storefront.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/types/storefront.ts)
  - [web/src/services/storefrontService.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/services/storefrontService.ts)
- Tela operacional consolidada:
  - [web/src/pages/Balcao/BalcaoTablesPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Balcao/BalcaoTablesPage.tsx)
- Nova rota pública:
  - [web/src/pages/Storefront/StorefrontReservationPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Storefront/StorefrontReservationPage.tsx)
  - [web/src/pages/Settings/blocks/CompanyBlock.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Settings/blocks/CompanyBlock.tsx) passou a expor cards para compartilhar os links públicos de `Loja online` e `Reservas`
- CTAs públicas adicionadas em:
  - [web/src/pages/Storefront/StorefrontCatalogPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Storefront/StorefrontCatalogPage.tsx)
  - [web/src/pages/Storefront/StorefrontProfilePage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Storefront/StorefrontProfilePage.tsx)

## Permissões
- Novos gates reutilizam a functionality já existente `balcao`:
  - `balcao:read` lista reservas/fila/disponibilidade
  - `balcao:create` cria reservas e entradas da fila
  - `balcao:update` cancela, chama e marca `no_show`
  - `balcao:open` acomoda e abre comanda
- O frontend passou a expor também `ACCESS.balcaoCreate` e `ACCESS.balcaoUpdate`.

## Validação
- `php artisan test tests/Feature/Balcao/BalcaoTest.php`
- `cd web && npm run build`

## Cuidados futuros
- Ainda não existe política separada para “janela mínima de antecedência”, “janela máxima” e “duração padrão por empresa”; hoje a duração padrão é fixa em 120 min no frontend/backend.
- Ainda não existe SMS/WhatsApp automático de confirmação da reserva.
- Ainda não existe tela administrativa dedicada de histórico multi-dia; a visão atual é operacional e focada no dia corrente.
- O fluxo público de reservas agora retorna também `storefront_enabled` para a UI decidir se deve oferecer CTA de retorno para a loja online, sem obrigar empresas sem `storefront` a terem uma vitrine ativa.
