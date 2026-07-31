import { Box, CircularProgress } from '@mui/material'
import { lazy, Suspense } from 'react'
import { Navigate, Outlet, Route, Routes } from 'react-router-dom'
import { AppLayout } from '../layouts/AppLayout'
import { StorefrontLayout } from '../layouts/StorefrontLayout'
import { ACCESS } from '../access/requirements'
import { SETTINGS_BLOCKS } from '../pages/Settings/blocks/registry'
import { PortalAuthProvider } from '../contexts/PortalAuthContext'
import { AccountingAuthProvider } from '../contexts/AccountingAuthContext'
import { ThemeFab } from '../components/ThemeFab'
import { LoginPage } from '../pages/Login/LoginPage'
import { SignupPage } from '../pages/Login/SignupPage'
import { PermissionRoute } from './PermissionRoute'
import { PortalProtectedRoute } from './PortalProtectedRoute'
import { AccountingProtectedRoute } from './AccountingProtectedRoute'
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
const OrderTrackingPage = lazy(() =>
  import('../pages/Tracking/OrderTrackingPage').then((m) => ({ default: m.OrderTrackingPage })),
)
const PortalLoginPage = lazy(() =>
  import('../pages/Portal/PortalLoginPage').then((m) => ({ default: m.PortalLoginPage })),
)
const PortalOrdersPage = lazy(() =>
  import('../pages/Portal/PortalOrdersPage').then((m) => ({ default: m.PortalOrdersPage })),
)
const PortalFavoritesPage = lazy(() =>
  import('../pages/Portal/PortalFavoritesPage').then((m) => ({ default: m.PortalFavoritesPage })),
)
const PortalCashbackPage = lazy(() =>
  import('../pages/Portal/PortalCashbackPage').then((m) => ({ default: m.PortalCashbackPage })),
)
const PortalAddressesPage = lazy(() =>
  import('../pages/Portal/PortalAddressesPage').then((m) => ({ default: m.PortalAddressesPage })),
)
const PortalVouchersPage = lazy(() =>
  import('../pages/Portal/PortalVouchersPage').then((m) => ({ default: m.PortalVouchersPage })),
)
const PortalProfilePage = lazy(() =>
  import('../pages/Portal/PortalProfilePage').then((m) => ({ default: m.PortalProfilePage })),
)
const PrepOrderPage = lazy(() =>
  import('../pages/Storefront/PrepOrderPage').then((m) => ({ default: m.PrepOrderPage })),
)
const StorefrontCatalogPage = lazy(() =>
  import('../pages/Storefront/StorefrontCatalogPage').then((m) => ({ default: m.StorefrontCatalogPage })),
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
const StorefrontReservationPage = lazy(() =>
  import('../pages/Storefront/StorefrontReservationPage').then((m) => ({ default: m.StorefrontReservationPage })),
)

const AccountingRegisterPage = lazy(() =>
  import('../pages/Accounting/AccountingRegisterPage').then((m) => ({ default: m.AccountingRegisterPage })),
)
const AccountingConfirmTotpPage = lazy(() =>
  import('../pages/Accounting/AccountingConfirmTotpPage').then((m) => ({ default: m.AccountingConfirmTotpPage })),
)
const AccountingLoginPage = lazy(() =>
  import('../pages/Accounting/AccountingLoginPage').then((m) => ({ default: m.AccountingLoginPage })),
)
const AccountingShell = lazy(() =>
  import('../pages/Accounting/AccountingShell').then((m) => ({ default: m.AccountingShell })),
)
const AccountingCompaniesPage = lazy(() =>
  import('../pages/Accounting/AccountingCompaniesPage').then((m) => ({ default: m.AccountingCompaniesPage })),
)
const AccountingRequestAccessPage = lazy(() =>
  import('../pages/Accounting/AccountingRequestAccessPage').then((m) => ({ default: m.AccountingRequestAccessPage })),
)
const AccountingCompanyLayout = lazy(() =>
  import('../pages/Accounting/AccountingCompanyLayout').then((m) => ({ default: m.AccountingCompanyLayout })),
)
const AccountingCompanyReportsPage = lazy(() =>
  import('../pages/Accounting/AccountingCompanyReportsPage').then((m) => ({ default: m.AccountingCompanyReportsPage })),
)
const AccountingCompanyMessagesPage = lazy(() =>
  import('../pages/Accounting/AccountingCompanyMessagesPage').then((m) => ({ default: m.AccountingCompanyMessagesPage })),
)
const AccountingCompanyProductsPage = lazy(() =>
  import('../pages/Accounting/AccountingCompanyProductsPage').then((m) => ({ default: m.AccountingCompanyProductsPage })),
)
const AccountingCompanyClientsPage = lazy(() =>
  import('../pages/Accounting/AccountingCompanyClientsPage').then((m) => ({ default: m.AccountingCompanyClientsPage })),
)
const AccountingCompanyTaxRulesPage = lazy(() =>
  import('../pages/Accounting/AccountingCompanyTaxRulesPage').then((m) => ({ default: m.AccountingCompanyTaxRulesPage })),
)
const AccountingAccessPage = lazy(() =>
  import('../pages/Settings/AccountingAccessPage').then((m) => ({ default: m.AccountingAccessPage })),
)

const DashboardPage = lazy(() => import('../pages/Dashboard/DashboardPage').then((m) => ({ default: m.DashboardPage })))
const ClientListPage = lazy(() => import('../pages/Client/ClientListPage').then((m) => ({ default: m.ClientListPage })))
const ClientFormPage = lazy(() => import('../pages/Client/ClientFormPage').then((m) => ({ default: m.ClientFormPage })))
const ClientOrdersPage = lazy(() =>
  import('../pages/Client/ClientOrdersPage').then((m) => ({ default: m.ClientOrdersPage })),
)
const ClientCategoryListPage = lazy(() =>
  import('../pages/ClientCategory/ClientCategoryListPage').then((m) => ({ default: m.ClientCategoryListPage })),
)
const ClientCategoryFormPage = lazy(() =>
  import('../pages/ClientCategory/ClientCategoryFormPage').then((m) => ({ default: m.ClientCategoryFormPage })),
)
const DiaIdealListPage = lazy(() =>
  import('../pages/ClientIdeal/DiaIdealListPage').then((m) => ({ default: m.DiaIdealListPage })),
)
const DiaIdealFormPage = lazy(() =>
  import('../pages/ClientIdeal/DiaIdealFormPage').then((m) => ({ default: m.DiaIdealFormPage })),
)
const PeriodoIdealListPage = lazy(() =>
  import('../pages/ClientIdeal/PeriodoIdealListPage').then((m) => ({ default: m.PeriodoIdealListPage })),
)
const PeriodoIdealFormPage = lazy(() =>
  import('../pages/ClientIdeal/PeriodoIdealFormPage').then((m) => ({ default: m.PeriodoIdealFormPage })),
)
const EstadoListPage = lazy(() => import('../pages/Location/EstadoListPage').then((m) => ({ default: m.EstadoListPage })))
const EstadoFormPage = lazy(() => import('../pages/Location/EstadoFormPage').then((m) => ({ default: m.EstadoFormPage })))
const CidadeListPage = lazy(() => import('../pages/Location/CidadeListPage').then((m) => ({ default: m.CidadeListPage })))
const CidadeFormPage = lazy(() => import('../pages/Location/CidadeFormPage').then((m) => ({ default: m.CidadeFormPage })))
const BairroListPage = lazy(() => import('../pages/Location/BairroListPage').then((m) => ({ default: m.BairroListPage })))
const BairroFormPage = lazy(() => import('../pages/Location/BairroFormPage').then((m) => ({ default: m.BairroFormPage })))
const EnderecoListPage = lazy(() =>
  import('../pages/Location/EnderecoListPage').then((m) => ({ default: m.EnderecoListPage })),
)
const EnderecoFormPage = lazy(() =>
  import('../pages/Location/EnderecoFormPage').then((m) => ({ default: m.EnderecoFormPage })),
)
const ProductListPage = lazy(() => import('../pages/Product/ProductListPage').then((m) => ({ default: m.ProductListPage })))
const ProductFormPage = lazy(() => import('../pages/Product/ProductFormPage').then((m) => ({ default: m.ProductFormPage })))
const StockLocationListPage = lazy(() =>
  import('../pages/StockLocation/StockLocationListPage').then((m) => ({ default: m.StockLocationListPage })),
)
const StockLocationFormPage = lazy(() =>
  import('../pages/StockLocation/StockLocationFormPage').then((m) => ({ default: m.StockLocationFormPage })),
)
const StockBalanceListPage = lazy(() =>
  import('../pages/Stock/StockBalanceListPage').then((m) => ({ default: m.StockBalanceListPage })),
)
const StockMovementListPage = lazy(() =>
  import('../pages/Stock/StockMovementListPage').then((m) => ({ default: m.StockMovementListPage })),
)
const StockMovementFormPage = lazy(() =>
  import('../pages/Stock/StockMovementFormPage').then((m) => ({ default: m.StockMovementFormPage })),
)
const OrderListPage = lazy(() => import('../pages/Order/OrderListPage').then((m) => ({ default: m.OrderListPage })))
const StorefrontOrderManagementPage = lazy(() =>
  import('../pages/Order/StorefrontOrderManagementPage').then((m) => ({ default: m.StorefrontOrderManagementPage })),
)
const MarketplaceOrdersPage = lazy(() =>
  import('../pages/Marketplace/MarketplaceOrdersPage').then((m) => ({ default: m.MarketplaceOrdersPage })),
)
const OrderFormPage = lazy(() => import('../pages/Order/OrderFormPage').then((m) => ({ default: m.OrderFormPage })))
const PdvCashSessionGatePage = lazy(() =>
  import('../pages/Pdv/PdvCashSessionGatePage').then((m) => ({ default: m.PdvCashSessionGatePage })),
)
const PdvSalePage = lazy(() => import('../pages/Pdv/PdvSalePage').then((m) => ({ default: m.PdvSalePage })))
const PdvCloseSessionPage = lazy(() =>
  import('../pages/Pdv/PdvCloseSessionPage').then((m) => ({ default: m.PdvCloseSessionPage })),
)
const PdvReceiptPrintView = lazy(() =>
  import('../pages/Pdv/PdvReceiptPrintView').then((m) => ({ default: m.PdvReceiptPrintView })),
)
const BalcaoTablesPage = lazy(() =>
  import('../pages/Balcao/BalcaoTablesPage').then((m) => ({ default: m.BalcaoTablesPage })),
)
const BalcaoComandaPage = lazy(() =>
  import('../pages/Balcao/BalcaoComandaPage').then((m) => ({ default: m.BalcaoComandaPage })),
)
const BalcaoKdsPage = lazy(() => import('../pages/Balcao/BalcaoKdsPage').then((m) => ({ default: m.BalcaoKdsPage })))
const BalcaoReceiptView = lazy(() =>
  import('../pages/Balcao/BalcaoReceiptView').then((m) => ({ default: m.BalcaoReceiptView })),
)
const RoutePlannerPage = lazy(() =>
  import('../pages/Route/RoutePlannerPage').then((m) => ({ default: m.RoutePlannerPage })),
)
const AnalyticsPage = lazy(() =>
  import('../pages/Analytics/AnalyticsPage').then((m) => ({ default: m.AnalyticsPage })),
)
const OrderReportListPage = lazy(() =>
  import('../pages/Report/OrderReportListPage').then((m) => ({ default: m.OrderReportListPage })),
)
const ChannelReportPage = lazy(() =>
  import('../pages/Report/ChannelReportPage').then((m) => ({ default: m.ChannelReportPage })),
)
const ClientReportListPage = lazy(() =>
  import('../pages/Report/ClientReportListPage').then((m) => ({ default: m.ClientReportListPage })),
)
const ReceivableReportListPage = lazy(() =>
  import('../pages/Report/ReceivableReportListPage').then((m) => ({ default: m.ReceivableReportListPage })),
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
const ProductCategoryListPage = lazy(() =>
  import('../pages/ProductCategory/ProductCategoryListPage').then((m) => ({ default: m.ProductCategoryListPage })),
)
const ProductCategoryFormPage = lazy(() =>
  import('../pages/ProductCategory/ProductCategoryFormPage').then((m) => ({ default: m.ProductCategoryFormPage })),
)
const ProductTypeListPage = lazy(() =>
  import('../pages/ProductType/ProductTypeListPage').then((m) => ({ default: m.ProductTypeListPage })),
)
const ProductTypeFormPage = lazy(() =>
  import('../pages/ProductType/ProductTypeFormPage').then((m) => ({ default: m.ProductTypeFormPage })),
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
const StoreBusinessSettingsPage = lazy(() =>
  import('../pages/Settings/StoreBusinessSettingsPage').then((m) => ({ default: m.StoreBusinessSettingsPage })),
)
const SubscriptionPage = lazy(() =>
  import('../pages/Settings/SubscriptionPage').then((m) => ({ default: m.SubscriptionPage })),
)
const IntegrationsPage = lazy(() =>
  import('../pages/Settings/IntegrationsPage').then((m) => ({ default: m.IntegrationsPage })),
)
const TaxRuleListPage = lazy(() =>
  import('../pages/Fiscal/TaxRuleListPage').then((m) => ({ default: m.TaxRuleListPage })),
)
const TaxRuleFormPage = lazy(() =>
  import('../pages/Fiscal/TaxRuleFormPage').then((m) => ({ default: m.TaxRuleFormPage })),
)
const FiscalOperationProfileListPage = lazy(() =>
  import('../pages/Fiscal/FiscalOperationProfileListPage').then((m) => ({ default: m.FiscalOperationProfileListPage })),
)
const FiscalOperationProfileFormPage = lazy(() =>
  import('../pages/Fiscal/FiscalOperationProfileFormPage').then((m) => ({ default: m.FiscalOperationProfileFormPage })),
)
const LegalDocumentPage = lazy(() =>
  import('../pages/Legal/LegalDocumentPage').then((m) => ({ default: m.LegalDocumentPage })),
)
const SocialMediaPage = lazy(() =>
  import('../pages/SocialMedia/SocialMediaPage').then((m) => ({ default: m.SocialMediaPage })),
)
const SupportTicketsPage = lazy(() =>
  import('../pages/Support/SupportTicketsPage').then((m) => ({ default: m.SupportTicketsPage })),
)
const TrainingCenterPage = lazy(() =>
  import('../pages/Training/TrainingCenterPage').then((m) => ({ default: m.TrainingCenterPage })),
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
 * destino do CTA "Ver todos os meus pedidos" (ver `OrderTrackingPage.tsx`).
 */
function PortalLayout() {
  return (
    <PortalAuthProvider>
      <Outlet />
      <ThemeFab />
    </PortalAuthProvider>
  )
}

/**
 * `AccountingAuthProvider` isolado da subárvore `/contador/*` — 3ª identidade
 * (`AccountingOffice`), nunca junto do `AuthProvider` (staff) nem do
 * `PortalAuthProvider` (cliente final). Mesmo padrão de isolamento do
 * `PortalLayout`.
 */
function AccountingLayout() {
  return (
    <AccountingAuthProvider>
      <Outlet />
      <ThemeFab />
    </AccountingAuthProvider>
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
        {/* Tela pública de preparo do pedido (roadmap Loja) — 100% pública,
            fora de qualquer guard/provider, protegida só pelo token temporário
            na query string (mesmo espírito de /rastreio/:uuid). */}
        <Route path="/preparo/:orderUuid" element={<PrepOrderPage />} />
        <Route path="/convite/:token" element={<AcceptInvitePage />} />
        <Route path="/confirmar-email/:token" element={<ConfirmEmailPage />} />
        <Route path="/esqueci-senha" element={<ForgotPasswordPage />} />
        <Route path="/redefinir-senha/:token" element={<ResetPasswordPage />} />

        <Route element={<PortalLayout />}>
          <Route path="/rastreio/:uuid" element={<OrderTrackingPage />} />
          <Route path="/portal/entrar" element={<PortalLoginPage />} />

          <Route element={<PortalProtectedRoute />}>
            <Route path="/portal/pedidos" element={<PortalOrdersPage />} />
            <Route path="/portal/favoritos" element={<PortalFavoritesPage />} />
            <Route path="/portal/enderecos" element={<PortalAddressesPage />} />
            <Route path="/portal/vouchers" element={<PortalVouchersPage />} />
            <Route path="/portal/cashback" element={<PortalCashbackPage />} />
            <Route path="/portal/perfil" element={<PortalProfilePage />} />
          </Route>
        </Route>

        {/* Módulo do contador (roadmap 2C) — subárvore isolada com provider
            próprio (`AccountingAuthProvider`), mesma estrutura do Portal. */}
        <Route element={<AccountingLayout />}>
          <Route path="/contador/cadastro" element={<AccountingRegisterPage />} />
          <Route path="/contador/cadastro/confirmar-totp" element={<AccountingConfirmTotpPage />} />
          <Route path="/contador/entrar" element={<AccountingLoginPage />} />

          <Route element={<AccountingProtectedRoute />}>
            <Route element={<AccountingShell />}>
              <Route path="/contador/empresas" element={<AccountingCompaniesPage />} />
              <Route path="/contador/solicitar-acesso" element={<AccountingRequestAccessPage />} />
              <Route path="/contador/empresas/:tenantUuid" element={<AccountingCompanyLayout />}>
                <Route index element={<AccountingCompanyReportsPage />} />
                <Route path="produtos-fiscais" element={<AccountingCompanyProductsPage />} />
                <Route path="clientes-fiscais" element={<AccountingCompanyClientsPage />} />
                <Route path="regras-tributarias" element={<AccountingCompanyTaxRulesPage />} />
                <Route path="pendencias" element={<AccountingCompanyMessagesPage />} />
              </Route>
            </Route>
          </Route>
        </Route>

        {/* Loja pública do tenant (Delivery Fase 1) — 100% pública, sem
            ProtectedRoute/PermissionRoute. `StorefrontLayout` monta seu
            próprio `PortalAuthProvider` (mesma identidade OTP do Portal,
            reaproveitada) + `StorefrontCartProvider` (carrinho por slug). */}
        <Route path="/loja/:slug" element={<StorefrontLayout />}>
          <Route index element={<StorefrontCatalogPage />} />
          <Route path="perfil" element={<StorefrontProfilePage />} />
          <Route path="reservas" element={<StorefrontReservationPage />} />
          <Route path="carrinho" element={<StorefrontCartPage />} />
          <Route path="checkout" element={<StorefrontCheckoutPage />} />
        </Route>

        <Route path="/reservas/:slug" element={<StorefrontReservationPage />} />

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
            <Route path="/clientes" element={<PermissionRoute requirement={ACCESS.clientsRead}><ClientListPage /></PermissionRoute>} />
            <Route path="/clientes/novo" element={<PermissionRoute requirement={ACCESS.clientsCreate}><ClientFormPage /></PermissionRoute>} />
            <Route path="/clientes/:uuid/editar" element={<PermissionRoute requirement={ACCESS.clientsUpdate}><ClientFormPage /></PermissionRoute>} />
            <Route path="/clientes/:uuid/pedidos" element={<PermissionRoute requirement={ACCESS.clientsRead}><ClientOrdersPage /></PermissionRoute>} />
            <Route path="/clientes/categorias" element={<PermissionRoute requirement={ACCESS.clientCategoriesRead}><ClientCategoryListPage /></PermissionRoute>} />
            <Route path="/clientes/categorias/nova" element={<PermissionRoute requirement={ACCESS.clientCategoriesCreate}><ClientCategoryFormPage /></PermissionRoute>} />
            <Route path="/clientes/categorias/:uuid/editar" element={<PermissionRoute requirement={ACCESS.clientCategoriesUpdate}><ClientCategoryFormPage /></PermissionRoute>} />
            <Route path="/clientes/dias-ideais" element={<PermissionRoute requirement={ACCESS.diasIdeaisRead}><DiaIdealListPage /></PermissionRoute>} />
            <Route path="/clientes/dias-ideais/novo" element={<PermissionRoute requirement={ACCESS.diasIdeaisCreate}><DiaIdealFormPage /></PermissionRoute>} />
            <Route path="/clientes/dias-ideais/:uuid/editar" element={<PermissionRoute requirement={ACCESS.diasIdeaisUpdate}><DiaIdealFormPage /></PermissionRoute>} />
            <Route path="/clientes/periodos-ideais" element={<PermissionRoute requirement={ACCESS.periodosIdeaisRead}><PeriodoIdealListPage /></PermissionRoute>} />
            <Route path="/clientes/periodos-ideais/novo" element={<PermissionRoute requirement={ACCESS.periodosIdeaisCreate}><PeriodoIdealFormPage /></PermissionRoute>} />
            <Route path="/clientes/periodos-ideais/:uuid/editar" element={<PermissionRoute requirement={ACCESS.periodosIdeaisUpdate}><PeriodoIdealFormPage /></PermissionRoute>} />

            <Route path="/estados" element={<PermissionRoute requirement={ACCESS.estadosRead}><EstadoListPage /></PermissionRoute>} />
            <Route path="/estados/novo" element={<PermissionRoute requirement={ACCESS.estadosCreate}><EstadoFormPage /></PermissionRoute>} />
            <Route path="/estados/:uuid/editar" element={<PermissionRoute requirement={ACCESS.estadosUpdate}><EstadoFormPage /></PermissionRoute>} />
            <Route path="/cidades" element={<PermissionRoute requirement={ACCESS.cidadesRead}><CidadeListPage /></PermissionRoute>} />
            <Route path="/cidades/novo" element={<PermissionRoute requirement={ACCESS.cidadesCreate}><CidadeFormPage /></PermissionRoute>} />
            <Route path="/cidades/:uuid/editar" element={<PermissionRoute requirement={ACCESS.cidadesUpdate}><CidadeFormPage /></PermissionRoute>} />
            <Route path="/bairros" element={<PermissionRoute requirement={ACCESS.bairrosRead}><BairroListPage /></PermissionRoute>} />
            <Route path="/bairros/novo" element={<PermissionRoute requirement={ACCESS.bairrosCreate}><BairroFormPage /></PermissionRoute>} />
            <Route path="/bairros/:uuid/editar" element={<PermissionRoute requirement={ACCESS.bairrosUpdate}><BairroFormPage /></PermissionRoute>} />
            <Route path="/enderecos" element={<PermissionRoute requirement={ACCESS.enderecosRead}><EnderecoListPage /></PermissionRoute>} />
            <Route path="/enderecos/novo" element={<PermissionRoute requirement={ACCESS.enderecosCreate}><EnderecoFormPage /></PermissionRoute>} />
            <Route path="/enderecos/:uuid/editar" element={<PermissionRoute requirement={ACCESS.enderecosUpdate}><EnderecoFormPage /></PermissionRoute>} />

            <Route path="/produtos" element={<PermissionRoute requirement={ACCESS.productsRead}><ProductListPage /></PermissionRoute>} />
            <Route path="/produtos/novo" element={<PermissionRoute requirement={ACCESS.productsCreate}><ProductFormPage /></PermissionRoute>} />
            <Route path="/produtos/:uuid/editar" element={<PermissionRoute requirement={ACCESS.productsUpdate}><ProductFormPage /></PermissionRoute>} />
            <Route path="/produtos/categorias" element={<PermissionRoute requirement={ACCESS.productCategoriesRead}><ProductCategoryListPage /></PermissionRoute>} />
            <Route path="/produtos/categorias/nova" element={<PermissionRoute requirement={ACCESS.productCategoriesCreate}><ProductCategoryFormPage /></PermissionRoute>} />
            <Route path="/produtos/categorias/:uuid/editar" element={<PermissionRoute requirement={ACCESS.productCategoriesUpdate}><ProductCategoryFormPage /></PermissionRoute>} />
            <Route path="/produtos/tipos" element={<PermissionRoute requirement={ACCESS.productTypesRead}><ProductTypeListPage /></PermissionRoute>} />
            <Route path="/produtos/tipos/novo" element={<PermissionRoute requirement={ACCESS.productTypesCreate}><ProductTypeFormPage /></PermissionRoute>} />
            <Route path="/produtos/tipos/:uuid/editar" element={<PermissionRoute requirement={ACCESS.productTypesUpdate}><ProductTypeFormPage /></PermissionRoute>} />

            <Route path="/estoque/locais" element={<PermissionRoute requirement={ACCESS.stockLocationsRead}><StockLocationListPage /></PermissionRoute>} />
            <Route path="/estoque/locais/novo" element={<PermissionRoute requirement={ACCESS.stockLocationsCreate}><StockLocationFormPage /></PermissionRoute>} />
            <Route path="/estoque/locais/:uuid/editar" element={<PermissionRoute requirement={ACCESS.stockLocationsUpdate}><StockLocationFormPage /></PermissionRoute>} />
            <Route path="/estoque/saldos" element={<PermissionRoute requirement={ACCESS.stockRead}><StockBalanceListPage /></PermissionRoute>} />
            <Route path="/estoque/movimentos" element={<PermissionRoute requirement={ACCESS.stockRead}><StockMovementListPage /></PermissionRoute>} />
            <Route path="/estoque/movimentos/nova" element={<PermissionRoute requirement={ACCESS.stockCreate}><StockMovementFormPage /></PermissionRoute>} />

            <Route path="/pedidos" element={<PermissionRoute requirement={ACCESS.ordersRead}><OrderListPage /></PermissionRoute>} />
            <Route path="/pedidos-manuais" element={<PermissionRoute requirement={ACCESS.ordersRead}><OrderListPage /></PermissionRoute>} />
            <Route path="/pedidos/novo" element={<PermissionRoute requirement={ACCESS.ordersCreate}><OrderFormPage /></PermissionRoute>} />
            <Route path="/pedidos-loja" element={<PermissionRoute requirement={ACCESS.storefrontOrdersRead}><StorefrontOrderManagementPage /></PermissionRoute>} />
            <Route path="/pedidos-ifood" element={<PermissionRoute requirement={ACCESS.apiAccessRead}><MarketplaceOrdersPage /></PermissionRoute>} />
            <Route path="/treinamentos" element={<TrainingCenterPage />} />
            {/* PDV — o gate de caixa (PermissionRoute `pdv:read`) envolve toda a
                sub-árvore: sem caixa aberto mostra a abertura, com caixa aberto
                libera venda/fechamento/recibo via <Outlet /> + PdvSessionContext. */}
            <Route path="/pdv" element={<PermissionRoute requirement={ACCESS.pdvRead}><PdvCashSessionGatePage /></PermissionRoute>}>
              <Route index element={<PdvSalePage />} />
              <Route path="fechar" element={<PdvCloseSessionPage />} />
              <Route path="recibo" element={<PdvReceiptPrintView />} />
            </Route>
            {/* Balcão (Fases 1+2) — mesas/comanda/KDS/recibo, todos gated por `balcao:read`.
                Cada ação granular (open/add_item/prep/close) gate por botão dentro da tela. */}
            <Route path="/balcao/mesas" element={<PermissionRoute requirement={ACCESS.balcaoRead}><BalcaoTablesPage /></PermissionRoute>} />
            <Route path="/balcao/comandas/:uuid" element={<PermissionRoute requirement={ACCESS.balcaoRead}><BalcaoComandaPage /></PermissionRoute>} />
            <Route path="/balcao/kds" element={<PermissionRoute requirement={ACCESS.balcaoRead}><BalcaoKdsPage /></PermissionRoute>} />
            <Route path="/balcao/recibo" element={<PermissionRoute requirement={ACCESS.balcaoRead}><BalcaoReceiptView /></PermissionRoute>} />
            <Route path="/rotas" element={<PermissionRoute requirement={ACCESS.routesRead}><RoutePlannerPage /></PermissionRoute>} />

            <Route path="/analises" element={<PermissionRoute requirement={ACCESS.reportsRead}><AnalyticsPage /></PermissionRoute>} />
            <Route path="/relatorios/canais" element={<PermissionRoute requirement={ACCESS.reportsRead}><ChannelReportPage /></PermissionRoute>} />
            <Route path="/relatorios/pedidos" element={<PermissionRoute requirement={ACCESS.reportsRead}><OrderReportListPage /></PermissionRoute>} />
            <Route path="/relatorios/clientes" element={<PermissionRoute requirement={ACCESS.reportsRead}><ClientReportListPage /></PermissionRoute>} />
            <Route path="/relatorios/recebiveis" element={<PermissionRoute requirement={ACCESS.reportsRead}><ReceivableReportListPage /></PermissionRoute>} />
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
                path="loja-online"
                element={
                  <PermissionRoute requirement={ACCESS.storefrontUpdate}>
                    <StoreBusinessSettingsPage />
                  </PermissionRoute>
                }
              />
              <Route
                path="assinatura"
                element={
                  <PermissionRoute requirement={ACCESS.subscriptionRead} requireOwner ownerBypassesAccess>
                    <SubscriptionPage />
                  </PermissionRoute>
                }
              />
              <Route
                path="integracoes"
                element={
                  <PermissionRoute requirement={ACCESS.apiAccessRead}>
                    <IntegrationsPage />
                  </PermissionRoute>
                }
              />
              <Route
                path="contadores"
                element={
                  <PermissionRoute requirement={ACCESS.accountingAccessRead}>
                    <AccountingAccessPage />
                  </PermissionRoute>
                }
              />
              <Route
                path="regras-tributarias"
                element={
                  <PermissionRoute requirement={ACCESS.taxRulesRead}>
                    <TaxRuleListPage />
                  </PermissionRoute>
                }
              />
              <Route
                path="regras-tributarias/nova"
                element={
                  <PermissionRoute requirement={ACCESS.taxRulesCreate}>
                    <TaxRuleFormPage />
                  </PermissionRoute>
                }
              />
              <Route
                path="regras-tributarias/:uuid/editar"
                element={
                  <PermissionRoute requirement={ACCESS.taxRulesUpdate}>
                    <TaxRuleFormPage />
                  </PermissionRoute>
                }
              />
              <Route
                path="perfis-fiscais"
                element={
                  <PermissionRoute requirement={ACCESS.taxRulesRead}>
                    <FiscalOperationProfileListPage />
                  </PermissionRoute>
                }
              />
              <Route
                path="perfis-fiscais/novo"
                element={
                  <PermissionRoute requirement={ACCESS.taxRulesCreate}>
                    <FiscalOperationProfileFormPage />
                  </PermissionRoute>
                }
              />
              <Route
                path="perfis-fiscais/:uuid/editar"
                element={
                  <PermissionRoute requirement={ACCESS.taxRulesUpdate}>
                    <FiscalOperationProfileFormPage />
                  </PermissionRoute>
                }
              />
            </Route>
            {/* `/empresa` foi unificada com `/configuracoes/assinatura` (mesmo domínio: plano/cobrança) — redirect preserva links/favoritos antigos. */}
            <Route path="/empresa" element={<Navigate to="/configuracoes/assinatura" replace />} />
            <Route path="/redes-sociais" element={<PermissionRoute requirement={ACCESS.socialMediaRead}><SocialMediaPage /></PermissionRoute>} />
            <Route path="/suporte" element={<PermissionRoute requirement={ACCESS.supportTicketsRead}><SupportTicketsPage /></PermissionRoute>} />
          </Route>
        </Route>

        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </Suspense>
  )
}
