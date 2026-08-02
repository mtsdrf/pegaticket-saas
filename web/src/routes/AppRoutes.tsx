import { Box, CircularProgress } from '@mui/material'
import { lazy, Suspense } from 'react'
import { Navigate, Outlet, Route, Routes } from 'react-router-dom'
import { AppLayout } from '../layouts/AppLayout'
import { StorefrontLayout } from '../layouts/StorefrontLayout'
import { ACCESS } from '../access/requirements'
import { SETTINGS_BLOCKS } from '../pages/Settings/blocks/registry'
import { PortalAuthProvider } from '../contexts/PortalAuthContext'
import { ThemeFab } from '../components/ThemeFab'
import { LoginPage } from '../pages/Login/LoginPage'
import { SignupPage } from '../pages/Login/SignupPage'
import { PermissionRoute } from './PermissionRoute'
import { PortalProtectedRoute } from './PortalProtectedRoute'
import { ProtectedRoute } from './ProtectedRoute'

const AcceptInvitePage = lazy(() =>
  import('../pages/Invite/AcceptInvitePage').then((m) => ({ default: m.AcceptInvitePage })),
)
const ForgotPasswordPage = lazy(() =>
  import('../pages/Auth/ForgotPasswordPage').then((m) => ({ default: m.ForgotPasswordPage })),
)
const ResetPasswordPage = lazy(() =>
  import('../pages/Auth/ResetPasswordPage').then((m) => ({ default: m.ResetPasswordPage })),
)
const ConfirmEmailPage = lazy(() =>
  import('../pages/Account/ConfirmEmailPage').then((m) => ({ default: m.ConfirmEmailPage })),
)
const MyAccountPage = lazy(() =>
  import('../pages/Account/MyAccountPage').then((m) => ({ default: m.MyAccountPage })),
)
const SaleTrackingPage = lazy(() =>
  import('../pages/SaleTracking/SaleTrackingPage').then((m) => ({ default: m.SaleTrackingPage })),
)
const PortalLoginPage = lazy(() =>
  import('../pages/Portal/PortalLoginPage').then((m) => ({ default: m.PortalLoginPage })),
)
const PortalSalesPage = lazy(() =>
  import('../pages/Portal/PortalSalesPage').then((m) => ({ default: m.PortalSalesPage })),
)
const PortalFavoritesPage = lazy(() =>
  import('../pages/Portal/PortalFavoritesPage').then((m) => ({ default: m.PortalFavoritesPage })),
)
const PortalVouchersPage = lazy(() =>
  import('../pages/Portal/PortalVouchersPage').then((m) => ({ default: m.PortalVouchersPage })),
)
const PortalProfilePage = lazy(() =>
  import('../pages/Portal/PortalProfilePage').then((m) => ({ default: m.PortalProfilePage })),
)
const StorefrontCatalogPage = lazy(() =>
  import('../pages/Storefront/StorefrontCatalogPage').then((m) => ({ default: m.StorefrontCatalogPage })),
)
const StorefrontEventDetailPage = lazy(() =>
  import('../pages/Storefront/StorefrontEventDetailPage').then((m) => ({ default: m.StorefrontEventDetailPage })),
)
const StorefrontProfilePage = lazy(() =>
  import('../pages/Storefront/StorefrontProfilePage').then((m) => ({ default: m.StorefrontProfilePage })),
)
const StorefrontCartPage = lazy(() =>
  import('../pages/Storefront/StorefrontCartPage').then((m) => ({ default: m.StorefrontCartPage })),
)
const StorefrontCheckoutPage = lazy(() =>
  import('../pages/Storefront/StorefrontCheckoutPage').then((m) => ({ default: m.StorefrontCheckoutPage })),
)
const DashboardPage = lazy(() => import('../pages/Dashboard/DashboardPage').then((m) => ({ default: m.DashboardPage })))
const EventListPage = lazy(() => import('../pages/Event/EventListPage').then((m) => ({ default: m.EventListPage })))
const EventFormPage = lazy(() => import('../pages/Event/EventFormPage').then((m) => ({ default: m.EventFormPage })))
const EventSessionListPage = lazy(() =>
  import('../pages/EventSession/EventSessionListPage').then((m) => ({ default: m.EventSessionListPage })),
)
const EventSessionFormPage = lazy(() =>
  import('../pages/EventSession/EventSessionFormPage').then((m) => ({ default: m.EventSessionFormPage })),
)
const TicketTypeListPage = lazy(() =>
  import('../pages/TicketType/TicketTypeListPage').then((m) => ({ default: m.TicketTypeListPage })),
)
const TicketTypeFormPage = lazy(() =>
  import('../pages/TicketType/TicketTypeFormPage').then((m) => ({ default: m.TicketTypeFormPage })),
)
const TicketBatchListPage = lazy(() =>
  import('../pages/TicketBatch/TicketBatchListPage').then((m) => ({ default: m.TicketBatchListPage })),
)
const TicketBatchFormPage = lazy(() =>
  import('../pages/TicketBatch/TicketBatchFormPage').then((m) => ({ default: m.TicketBatchFormPage })),
)
const CheckinPage = lazy(() =>
  import('../pages/Checkin/CheckinPage').then((m) => ({ default: m.CheckinPage })),
)
const TicketListPage = lazy(() =>
  import('../pages/Ticket/TicketListPage').then((m) => ({ default: m.TicketListPage })),
)
const EventProductListPage = lazy(() =>
  import('../pages/EventProduct/EventProductListPage').then((m) => ({ default: m.EventProductListPage })),
)
const EventProductFormPage = lazy(() =>
  import('../pages/EventProduct/EventProductFormPage').then((m) => ({ default: m.EventProductFormPage })),
)
const VenueListPage = lazy(() => import('../pages/Venue/VenueListPage').then((m) => ({ default: m.VenueListPage })))
const VenueFormPage = lazy(() => import('../pages/Venue/VenueFormPage').then((m) => ({ default: m.VenueFormPage })))
const VenueSeatsPage = lazy(() => import('../pages/Venue/VenueSeatsPage').then((m) => ({ default: m.VenueSeatsPage })))
const SeatFormPage = lazy(() => import('../pages/Venue/SeatFormPage').then((m) => ({ default: m.SeatFormPage })))
const SaleListPage = lazy(() => import('../pages/Sale/SaleListPage').then((m) => ({ default: m.SaleListPage })))
const StorefrontSaleManagementPage = lazy(() =>
  import('../pages/Sale/StorefrontSaleManagementPage').then((m) => ({ default: m.StorefrontSaleManagementPage })),
)
const SaleFormPage = lazy(() => import('../pages/Sale/SaleFormPage').then((m) => ({ default: m.SaleFormPage })))
const AnalyticsPage = lazy(() =>
  import('../pages/Analytics/AnalyticsPage').then((m) => ({ default: m.AnalyticsPage })),
)
const SaleReportListPage = lazy(() =>
  import('../pages/SaleReport/SaleReportListPage').then((m) => ({ default: m.SaleReportListPage })),
)
const ChannelReportPage = lazy(() =>
  import('../pages/SaleReport/ChannelReportPage').then((m) => ({ default: m.ChannelReportPage })),
)
const ReconciliationPage = lazy(() =>
  import('../pages/Finance/ReconciliationPage').then((m) => ({ default: m.ReconciliationPage })),
)
const UserListPage = lazy(() => import('../pages/Admin/UserListPage').then((m) => ({ default: m.UserListPage })))
const UserFormPage = lazy(() => import('../pages/Admin/UserFormPage').then((m) => ({ default: m.UserFormPage })))
const GroupListPage = lazy(() => import('../pages/Admin/GroupListPage').then((m) => ({ default: m.GroupListPage })))
const GroupFormPage = lazy(() => import('../pages/Admin/GroupFormPage').then((m) => ({ default: m.GroupFormPage })))
const FunctionalityListPage = lazy(() =>
  import('../pages/Admin/FunctionalityListPage').then((m) => ({ default: m.FunctionalityListPage })),
)
const FunctionalityFormPage = lazy(() =>
  import('../pages/Admin/FunctionalityFormPage').then((m) => ({ default: m.FunctionalityFormPage })),
)
const TenantListPage = lazy(() => import('../pages/Admin/TenantListPage').then((m) => ({ default: m.TenantListPage })))
const TenantFormPage = lazy(() => import('../pages/Admin/TenantFormPage').then((m) => ({ default: m.TenantFormPage })))
const PlanListPage = lazy(() => import('../pages/Admin/PlanListPage').then((m) => ({ default: m.PlanListPage })))
const PlanFormPage = lazy(() => import('../pages/Admin/PlanFormPage').then((m) => ({ default: m.PlanFormPage })))
const TenantRoleListPage = lazy(() =>
  import('../pages/Admin/TenantRoleListPage').then((m) => ({ default: m.TenantRoleListPage })),
)
const TenantRoleFormPage = lazy(() =>
  import('../pages/Admin/TenantRoleFormPage').then((m) => ({ default: m.TenantRoleFormPage })),
)
const TenantUserListPage = lazy(() =>
  import('../pages/Admin/TenantUserListPage').then((m) => ({ default: m.TenantUserListPage })),
)
const TenantUserFormPage = lazy(() =>
  import('../pages/Admin/TenantUserFormPage').then((m) => ({ default: m.TenantUserFormPage })),
)
const TenantUserInviteFormPage = lazy(() =>
  import('../pages/Admin/TenantUserInviteFormPage').then((m) => ({ default: m.TenantUserInviteFormPage })),
)
const EventCategoryListPage = lazy(() =>
  import('../pages/EventCategory/EventCategoryListPage').then((m) => ({ default: m.EventCategoryListPage })),
)
const EventCategoryFormPage = lazy(() =>
  import('../pages/EventCategory/EventCategoryFormPage').then((m) => ({ default: m.EventCategoryFormPage })),
)
const AuditLogListPage = lazy(() =>
  import('../pages/Admin/AuditLogListPage').then((m) => ({ default: m.AuditLogListPage })),
)
const PaymentIssuesListPage = lazy(() =>
  import('../pages/Admin/PaymentIssuesListPage').then((m) => ({ default: m.PaymentIssuesListPage })),
)
const SettingsHubLayout = lazy(() =>
  import('../pages/Settings/SettingsHubLayout').then((m) => ({ default: m.SettingsHubLayout })),
)
const SettingsIndexPage = lazy(() =>
  import('../pages/Settings/SettingsIndexPage').then((m) => ({ default: m.SettingsIndexPage })),
)
const SettingsBlockPage = lazy(() =>
  import('../pages/Settings/SettingsBlockPage').then((m) => ({ default: m.SettingsBlockPage })),
)
const SubscriptionPage = lazy(() =>
  import('../pages/Settings/SubscriptionPage').then((m) => ({ default: m.SubscriptionPage })),
)
const LegalDocumentPage = lazy(() =>
  import('../pages/Legal/LegalDocumentPage').then((m) => ({ default: m.LegalDocumentPage })),
)
const SupportTicketsPage = lazy(() =>
  import('../pages/Support/SupportTicketsPage').then((m) => ({ default: m.SupportTicketsPage })),
)
function RouteFallback() {
  return (
    <Box sx={{ display: 'flex', justifyContent: 'center', py: 10 }}>
      <CircularProgress size={28} />
    </Box>
  )
}

/**
 * `PortalAuthProvider` isolado dessa subárvore de rotas — nunca junto do
 * `AuthProvider` de staff em `App.tsx`. `/rastreio/:uuid` entra aqui junto
 * porque a página de rastreio consulta `usePortalAuth()` pra decidir o
 * destino do CTA "Ver todas as minhas compras" (ver `SaleTrackingPage.tsx`).
 */
function PortalLayout() {
  return (
    <PortalAuthProvider>
      <Outlet />
      <ThemeFab />
    </PortalAuthProvider>
  )
}

export function AppRoutes() {
  return (
    <Suspense fallback={<RouteFallback />}>
      <Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route path="/cadastro" element={<SignupPage />} />
        {/* Documentos legais — 100% públicos (lidos antes do cadastro, sem sessão). */}
        <Route path="/termos" element={<LegalDocumentPage type="terms" />} />
        <Route path="/privacidade" element={<LegalDocumentPage type="privacy" />} />
        <Route path="/convite/:token" element={<AcceptInvitePage />} />
        <Route path="/confirmar-email/:token" element={<ConfirmEmailPage />} />
        <Route path="/esqueci-senha" element={<ForgotPasswordPage />} />
        <Route path="/redefinir-senha/:token" element={<ResetPasswordPage />} />

        <Route element={<PortalLayout />}>
          <Route path="/rastreio/:uuid" element={<SaleTrackingPage />} />
          <Route path="/portal/entrar" element={<PortalLoginPage />} />

          <Route element={<PortalProtectedRoute />}>
            <Route path="/portal/compras" element={<PortalSalesPage />} />
            <Route path="/portal/favoritos" element={<PortalFavoritesPage />} />
            <Route path="/portal/vouchers" element={<PortalVouchersPage />} />
            <Route path="/portal/perfil" element={<PortalProfilePage />} />
          </Route>
        </Route>

        {/* Bilheteria pública do tenant — 100% pública, sem
            ProtectedRoute/PermissionRoute. `StorefrontLayout` monta seu
            próprio `PortalAuthProvider` (mesma identidade OTP do Portal,
            reaproveitada) + `StorefrontCartProvider` (carrinho por slug). */}
        <Route path="/eventos/:slug" element={<StorefrontLayout />}>
          <Route index element={<StorefrontCatalogPage />} />
          <Route path=":eventSlug" element={<StorefrontEventDetailPage />} />
          <Route path="perfil" element={<StorefrontProfilePage />} />
          <Route path="carrinho" element={<StorefrontCartPage />} />
          <Route path="checkout" element={<StorefrontCheckoutPage />} />
        </Route>
        <Route path="/loja/:slug" element={<StorefrontLayout />}>
          <Route index element={<StorefrontCatalogPage />} />
          <Route path="eventos/:eventSlug" element={<StorefrontEventDetailPage />} />
          <Route path="perfil" element={<StorefrontProfilePage />} />
          <Route path="carrinho" element={<StorefrontCartPage />} />
          <Route path="checkout" element={<StorefrontCheckoutPage />} />
        </Route>
        <Route path="/bilheteria/:slug" element={<StorefrontLayout />}>
          <Route index element={<StorefrontCatalogPage />} />
          <Route path="eventos/:eventSlug" element={<StorefrontEventDetailPage />} />
          <Route path="perfil" element={<StorefrontProfilePage />} />
          <Route path="carrinho" element={<StorefrontCartPage />} />
          <Route path="checkout" element={<StorefrontCheckoutPage />} />
        </Route>

        <Route element={<ProtectedRoute />}>
          <Route element={<AppLayout />}>
            {/* Sem PermissionRoute de propósito: "/" é o destino de fallback de
                qualquer PermissionRoute negada em outra rota (ver PermissionRoute.tsx) —
                se ela própria exigisse permissão, um usuário sem `dashboard:read`
                cairia num loop de redirecionamento. A página decide internamente
                o que mostrar (ações rápidas sempre; números só com `dashboard:read`). */}
            <Route path="/" element={<DashboardPage />} />
            {/* Auto-serviço: sem PermissionRoute de propósito — todo usuário logado edita o próprio perfil. */}
            <Route path="/minha-conta" element={<MyAccountPage />} />

            <Route path="/eventos" element={<PermissionRoute requirement={ACCESS.eventsRead}><EventListPage /></PermissionRoute>} />
            <Route path="/eventos/novo" element={<PermissionRoute requirement={ACCESS.eventsCreate}><EventFormPage /></PermissionRoute>} />
            <Route path="/eventos/:uuid/editar" element={<PermissionRoute requirement={ACCESS.eventsUpdate}><EventFormPage /></PermissionRoute>} />
            <Route path="/eventos/:eventUuid/sessoes" element={<PermissionRoute requirement={ACCESS.eventSessionsRead}><EventSessionListPage /></PermissionRoute>} />
            <Route path="/eventos/:eventUuid/sessoes/nova" element={<PermissionRoute requirement={ACCESS.eventSessionsCreate}><EventSessionFormPage /></PermissionRoute>} />
            <Route path="/eventos/:eventUuid/sessoes/:sessionUuid/editar" element={<PermissionRoute requirement={ACCESS.eventSessionsUpdate}><EventSessionFormPage /></PermissionRoute>} />
            <Route path="/eventos/categorias" element={<PermissionRoute requirement={ACCESS.eventCategoriesRead}><EventCategoryListPage /></PermissionRoute>} />
            <Route path="/eventos/categorias/nova" element={<PermissionRoute requirement={ACCESS.eventCategoriesCreate}><EventCategoryFormPage /></PermissionRoute>} />
            <Route path="/eventos/categorias/:uuid/editar" element={<PermissionRoute requirement={ACCESS.eventCategoriesUpdate}><EventCategoryFormPage /></PermissionRoute>} />

            <Route path="/tipos-de-ingresso" element={<PermissionRoute requirement={ACCESS.ticketTypesRead}><TicketTypeListPage /></PermissionRoute>} />
            <Route path="/tipos-de-ingresso/novo" element={<PermissionRoute requirement={ACCESS.ticketTypesCreate}><TicketTypeFormPage /></PermissionRoute>} />
            <Route path="/tipos-de-ingresso/:uuid/editar" element={<PermissionRoute requirement={ACCESS.ticketTypesUpdate}><TicketTypeFormPage /></PermissionRoute>} />
            <Route path="/tipos-de-ingresso/:ticketTypeUuid/lotes" element={<PermissionRoute requirement={ACCESS.ticketBatchesRead}><TicketBatchListPage /></PermissionRoute>} />
            <Route path="/tipos-de-ingresso/:ticketTypeUuid/lotes/novo" element={<PermissionRoute requirement={ACCESS.ticketBatchesCreate}><TicketBatchFormPage /></PermissionRoute>} />
            <Route path="/tipos-de-ingresso/:ticketTypeUuid/lotes/:batchUuid/editar" element={<PermissionRoute requirement={ACCESS.ticketBatchesUpdate}><TicketBatchFormPage /></PermissionRoute>} />

            <Route path="/portaria" element={<PermissionRoute requirement={ACCESS.ticketsCheckin}><CheckinPage /></PermissionRoute>} />
            <Route path="/ingressos" element={<PermissionRoute requirement={ACCESS.ticketsRead}><TicketListPage /></PermissionRoute>} />

            <Route path="/adicionais" element={<PermissionRoute requirement={ACCESS.eventProductsRead}><EventProductListPage /></PermissionRoute>} />
            <Route path="/adicionais/novo" element={<PermissionRoute requirement={ACCESS.eventProductsCreate}><EventProductFormPage /></PermissionRoute>} />
            <Route path="/adicionais/:uuid/editar" element={<PermissionRoute requirement={ACCESS.eventProductsUpdate}><EventProductFormPage /></PermissionRoute>} />
            <Route path="/locais" element={<PermissionRoute requirement={ACCESS.venuesRead}><VenueListPage /></PermissionRoute>} />
            <Route path="/locais/novo" element={<PermissionRoute requirement={ACCESS.venuesCreate}><VenueFormPage /></PermissionRoute>} />
            <Route path="/locais/:uuid/editar" element={<PermissionRoute requirement={ACCESS.venuesUpdate}><VenueFormPage /></PermissionRoute>} />
            <Route path="/locais/:venueUuid/assentos" element={<PermissionRoute requirement={ACCESS.seatsRead}><VenueSeatsPage /></PermissionRoute>} />
            <Route path="/locais/:venueUuid/assentos/novo" element={<PermissionRoute requirement={ACCESS.seatsCreate}><SeatFormPage /></PermissionRoute>} />
            <Route path="/locais/:venueUuid/assentos/:seatUuid/editar" element={<PermissionRoute requirement={ACCESS.seatsUpdate}><SeatFormPage /></PermissionRoute>} />

            <Route path="/vendas" element={<Navigate to="/vendas-manuais" replace />} />
            <Route path="/vendas-manuais" element={<PermissionRoute requirement={ACCESS.salesRead}><SaleListPage /></PermissionRoute>} />
            <Route path="/vendas/nova" element={<Navigate to="/vendas-manuais/nova" replace />} />
            <Route path="/vendas-manuais/nova" element={<PermissionRoute requirement={ACCESS.salesCreate}><SaleFormPage /></PermissionRoute>} />
            <Route path="/vendas-online" element={<PermissionRoute requirement={ACCESS.storefrontSalesRead}><StorefrontSaleManagementPage /></PermissionRoute>} />
            <Route path="/vendas-loja" element={<PermissionRoute requirement={ACCESS.storefrontSalesRead}><StorefrontSaleManagementPage /></PermissionRoute>} />
            <Route path="/analises" element={<PermissionRoute requirement={ACCESS.reportsRead}><AnalyticsPage /></PermissionRoute>} />
            <Route path="/relatorios/canais" element={<PermissionRoute requirement={ACCESS.reportsRead}><ChannelReportPage /></PermissionRoute>} />
            <Route path="/relatorios/vendas" element={<PermissionRoute requirement={ACCESS.reportsRead}><SaleReportListPage /></PermissionRoute>} />
            <Route path="/financeiro/conciliacao" element={<PermissionRoute requirement={ACCESS.financeRead}><ReconciliationPage /></PermissionRoute>} />

            <Route path="/admin/usuarios" element={<PermissionRoute requirement={ACCESS.adminUsersRead}><UserListPage /></PermissionRoute>} />
            <Route path="/admin/usuarios/novo" element={<PermissionRoute requirement={ACCESS.adminUsersCreate}><UserFormPage /></PermissionRoute>} />
            <Route path="/admin/usuarios/:uuid/editar" element={<PermissionRoute requirement={ACCESS.adminUsersUpdate}><UserFormPage /></PermissionRoute>} />
            <Route path="/admin/grupos" element={<PermissionRoute requirement={ACCESS.adminGroupsRead}><GroupListPage /></PermissionRoute>} />
            <Route path="/admin/grupos/novo" element={<PermissionRoute requirement={ACCESS.adminGroupsCreate}><GroupFormPage /></PermissionRoute>} />
            <Route path="/admin/grupos/:uuid/editar" element={<PermissionRoute requirement={ACCESS.adminGroupsUpdate}><GroupFormPage /></PermissionRoute>} />
            <Route path="/admin/funcionalidades" element={<PermissionRoute requirement={ACCESS.adminFunctionalitiesRead}><FunctionalityListPage /></PermissionRoute>} />
            <Route path="/admin/funcionalidades/nova" element={<PermissionRoute requirement={ACCESS.adminFunctionalitiesCreate}><FunctionalityFormPage /></PermissionRoute>} />
            <Route path="/admin/funcionalidades/:uuid/editar" element={<PermissionRoute requirement={ACCESS.adminFunctionalitiesUpdate}><FunctionalityFormPage /></PermissionRoute>} />
            <Route path="/admin/planos" element={<PermissionRoute requirement={ACCESS.adminPlansRead}><PlanListPage /></PermissionRoute>} />
            <Route path="/admin/planos/novo" element={<PermissionRoute requirement={ACCESS.adminPlansCreate}><PlanFormPage /></PermissionRoute>} />
            <Route path="/admin/planos/:uuid/editar" element={<PermissionRoute requirement={ACCESS.adminPlansUpdate}><PlanFormPage /></PermissionRoute>} />
            <Route path="/admin/tenants" element={<PermissionRoute requirement={ACCESS.adminTenantsRead}><TenantListPage /></PermissionRoute>} />
            <Route path="/admin/tenants/novo" element={<PermissionRoute requirement={ACCESS.adminTenantsCreate}><TenantFormPage /></PermissionRoute>} />
            <Route path="/admin/tenants/:uuid/editar" element={<PermissionRoute requirement={ACCESS.adminTenantsUpdate}><TenantFormPage /></PermissionRoute>} />
            <Route path="/admin/tenant-roles" element={<PermissionRoute requirement={ACCESS.tenantRolesRead}><TenantRoleListPage /></PermissionRoute>} />
            <Route path="/admin/tenant-roles/novo" element={<PermissionRoute requirement={ACCESS.tenantRolesCreate}><TenantRoleFormPage /></PermissionRoute>} />
            <Route path="/admin/tenant-roles/:uuid/editar" element={<PermissionRoute requirement={ACCESS.tenantRolesUpdate}><TenantRoleFormPage /></PermissionRoute>} />
            <Route path="/admin/tenant-users" element={<PermissionRoute requirement={ACCESS.tenantUsersRead}><TenantUserListPage /></PermissionRoute>} />
            <Route path="/admin/tenant-users/novo" element={<PermissionRoute requirement={ACCESS.tenantUsersCreate}><TenantUserFormPage /></PermissionRoute>} />
            <Route path="/admin/tenant-users/convidar" element={<PermissionRoute requirement={ACCESS.tenantUsersCreate}><TenantUserInviteFormPage /></PermissionRoute>} />
            <Route path="/admin/tenant-users/:uuid/editar" element={<PermissionRoute requirement={ACCESS.tenantUsersUpdate}><TenantUserFormPage /></PermissionRoute>} />
            <Route path="/admin/auditoria" element={<PermissionRoute requirement={ACCESS.adminAuditLogsRead}><AuditLogListPage /></PermissionRoute>} />
            <Route path="/admin/pagamentos-pendencias" element={<PermissionRoute requirement={ACCESS.adminPaymentIssuesRead}><PaymentIssuesListPage /></PermissionRoute>} />
            {/* Hub de Configurações (2026-07-24) — índice + drill-down por bloco,
                cada bloco gated pela própria permissão (não pela página
                inteira). `SettingsHubLayout` decide mobile (só Outlet) vs
                desktop (rail + painel) internamente. */}
            <Route path="/configuracoes" element={<SettingsHubLayout />}>
              <Route index element={<SettingsIndexPage />} />
              {SETTINGS_BLOCKS.map((block) => (
                <Route
                  key={block.key}
                  path={block.path}
                  element={
                    <PermissionRoute requirement={block.permission}>
                      <SettingsBlockPage title={block.label} subtitle={block.description}>
                        <block.Component />
                      </SettingsBlockPage>
                    </PermissionRoute>
                  }
                />
              ))}
              <Route
                path="assinatura"
                element={
                  <PermissionRoute requirement={ACCESS.subscriptionRead} requireOwner ownerBypassesAccess>
                    <SubscriptionPage />
                  </PermissionRoute>
                }
              />
            </Route>
            {/* `/empresa` foi unificada com `/configuracoes/assinatura` (mesmo domínio: plano/cobrança) — redirect preserva links/favoritos antigos. */}
            <Route path="/empresa" element={<Navigate to="/configuracoes/assinatura" replace />} />
            <Route path="/suporte" element={<PermissionRoute requirement={ACCESS.helpRequestsRead}><SupportTicketsPage /></PermissionRoute>} />
          </Route>
        </Route>

        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </Suspense>
  )
}
