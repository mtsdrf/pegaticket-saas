import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type {
  MarketplaceCatalogPreview,
  MarketplaceCancellationReason,
  MarketplaceCatalogSync,
  MarketplaceEvent,
  MarketplaceHealthCheck,
  MarketplaceIntegration,
  MarketplaceIntegrationPayload,
  MarketplaceMerchantStatusSnapshot,
  MarketplaceOpeningHoursSyncResult,
  MarketplaceOperationsSummary,
  MarketplaceOrder,
  MarketplaceOrderFilters,
  MarketplaceOrderPageResult,
  MarketplaceOrderActionPayload,
  MarketplacePollResult,
} from '../types/marketplace'

export function listMarketplaceIntegrations(): Promise<MarketplaceIntegration[]> {
  return unwrap(apiClient.get<ApiSuccess<MarketplaceIntegration[]>>('/marketplace/integrations'))
}

export function createMarketplaceIntegration(payload: MarketplaceIntegrationPayload): Promise<MarketplaceIntegration> {
  return unwrap(apiClient.post<ApiSuccess<MarketplaceIntegration>>('/marketplace/integrations', payload))
}

export function updateMarketplaceIntegration(uuid: string, payload: MarketplaceIntegrationPayload): Promise<MarketplaceIntegration> {
  return unwrap(apiClient.put<ApiSuccess<MarketplaceIntegration>>(`/marketplace/integrations/${uuid}`, payload))
}

export function syncMarketplaceMerchants(uuid: string): Promise<MarketplaceIntegration> {
  return unwrap(apiClient.post<ApiSuccess<MarketplaceIntegration>>(`/marketplace/integrations/${uuid}/sync-merchants`))
}

export function pollMarketplaceIntegration(uuid: string): Promise<MarketplacePollResult> {
  return unwrap(apiClient.post<ApiSuccess<MarketplacePollResult>>(`/marketplace/integrations/${uuid}/poll`))
}

export function listMarketplaceEvents(uuid: string): Promise<MarketplaceEvent[]> {
  return unwrap(apiClient.get<ApiSuccess<MarketplaceEvent[]>>(`/marketplace/integrations/${uuid}/events`))
}

export function getMarketplaceOperationsSummary(uuid: string): Promise<MarketplaceOperationsSummary> {
  return unwrap(apiClient.get<ApiSuccess<MarketplaceOperationsSummary>>(`/marketplace/integrations/${uuid}/operations-summary`))
}

export function getMarketplaceCatalogPreview(uuid: string): Promise<MarketplaceCatalogPreview> {
  return unwrap(apiClient.get<ApiSuccess<MarketplaceCatalogPreview>>(`/marketplace/integrations/${uuid}/catalog/preview`))
}

export function syncMarketplaceCatalog(uuid: string): Promise<MarketplaceCatalogSync> {
  return unwrap(apiClient.post<ApiSuccess<MarketplaceCatalogSync>>(`/marketplace/integrations/${uuid}/catalog/sync`))
}

export function listMarketplaceCatalogSyncs(uuid: string): Promise<MarketplaceCatalogSync[]> {
  return unwrap(apiClient.get<ApiSuccess<MarketplaceCatalogSync[]>>(`/marketplace/integrations/${uuid}/catalog/syncs`))
}

export function getMarketplaceMerchantStatus(uuid: string): Promise<MarketplaceMerchantStatusSnapshot> {
  return unwrap(apiClient.get<ApiSuccess<MarketplaceMerchantStatusSnapshot>>(`/marketplace/integrations/${uuid}/merchant-status`))
}

export function createMarketplaceInterruption(uuid: string, payload: { description: string; duration: number }) {
  return unwrap(apiClient.post<ApiSuccess<unknown>>(`/marketplace/integrations/${uuid}/interruptions`, payload))
}

export function deleteMarketplaceInterruption(uuid: string, interruptionId: string) {
  return unwrap(apiClient.delete<ApiSuccess<unknown>>(`/marketplace/integrations/${uuid}/interruptions/${interruptionId}`))
}

export function syncMarketplaceOpeningHours(uuid: string): Promise<MarketplaceOpeningHoursSyncResult> {
  return unwrap(apiClient.post<ApiSuccess<MarketplaceOpeningHoursSyncResult>>(`/marketplace/integrations/${uuid}/opening-hours/sync`))
}

export function listMarketplaceOrders(uuid: string): Promise<MarketplaceOrder[]> {
  return unwrap(apiClient.get<ApiSuccess<MarketplaceOrder[]>>(`/marketplace/integrations/${uuid}/orders`))
}

export function listMarketplaceOrdersPage(uuid: string, filters: MarketplaceOrderFilters = {}): Promise<MarketplaceOrderPageResult> {
  return listPaginated<MarketplaceOrder>(`/marketplace/integrations/${uuid}/orders`, filters)
}

export function checkMarketplaceHealth(uuid: string): Promise<MarketplaceHealthCheck> {
  return unwrap(apiClient.get<ApiSuccess<MarketplaceHealthCheck>>(`/marketplace/integrations/${uuid}/health`))
}

export function getMarketplaceOrder(uuid: string): Promise<MarketplaceOrder> {
  return unwrap(apiClient.get<ApiSuccess<MarketplaceOrder>>(`/marketplace/orders/${uuid}`))
}

export function listMarketplaceCancellationReasons(uuid: string): Promise<MarketplaceCancellationReason[]> {
  return unwrap(apiClient.get<ApiSuccess<MarketplaceCancellationReason[]>>(`/marketplace/orders/${uuid}/cancellation-reasons`))
}

export function performMarketplaceOrderAction(uuid: string, payload: MarketplaceOrderActionPayload) {
  return unwrap(apiClient.post<ApiSuccess<unknown>>(`/marketplace/orders/${uuid}/actions`, payload))
}

export function importMarketplaceOrder(uuid: string): Promise<MarketplaceOrder> {
  return unwrap(apiClient.post<ApiSuccess<MarketplaceOrder>>(`/marketplace/orders/${uuid}/import`))
}

export function refreshMarketplaceOrder(uuid: string): Promise<MarketplaceOrder> {
  return unwrap(apiClient.post<ApiSuccess<MarketplaceOrder>>(`/marketplace/orders/${uuid}/refresh`))
}

export function retryMarketplaceEvent(uuid: string): Promise<MarketplaceEvent> {
  return unwrap(apiClient.post<ApiSuccess<MarketplaceEvent>>(`/marketplace/events/${uuid}/retry`))
}

export function refreshMarketplaceCatalogSync(uuid: string): Promise<MarketplaceCatalogSync> {
  return unwrap(apiClient.post<ApiSuccess<MarketplaceCatalogSync>>(`/marketplace/catalog/syncs/${uuid}/refresh`))
}
