import { formatCurrency, formatDateBR } from '../../utils/format'

export function formatFiscalDocumentTypeLabel(value: string): string {
  return value === 'nfce' ? 'NFC-e' : value === 'nfe' ? 'NF-e' : value === 'nfse' ? 'NFS-e' : value
}

export function formatFiscalTaxTypeLabel(value: string): string {
  return value === 'icms_st' ? 'ICMS-ST' : value.toUpperCase().replace('_', '-')
}

export function isManualFiscalProvider(provider?: string | null): boolean {
  return (provider ?? '').toLowerCase() === 'manual'
}

export function formatFiscalDocumentStatusLabel(value: string, provider?: string | null): string {
  return value === 'pending'
    ? isManualFiscalProvider(provider)
      ? 'Aguardando acompanhamento interno'
      : 'Pendente'
    : value === 'provider_submitted'
      ? isManualFiscalProvider(provider)
        ? 'Em fluxo interno'
        : 'Enviado ao provider'
    : value === 'authorized'
      ? 'Autorizado'
      : value === 'rejected'
        ? 'Rejeitado'
        : value === 'canceled'
          ? 'Cancelado'
          : value === 'draft'
            ? 'Rascunho'
            : value
}

export function formatFiscalDocumentReferenceLabel(provider?: string | null): string {
  return isManualFiscalProvider(provider) ? 'Referência interna' : 'Referência no provider'
}

export function formatFiscalDocumentCheckedAtLabel(provider?: string | null): string {
  return isManualFiscalProvider(provider) ? 'Última atualização' : 'Última consulta'
}

export function formatFiscalDocumentPreparedSummary(
  generatedAt: string | null,
  itemsCount: number,
  operationProfileName: string | null,
): string {
  return `Documento interno preparado em ${generatedAt ? formatDateBR(generatedAt) : 'data não informada'} com ${itemsCount} item(ns)${
    operationProfileName ? ` usando o perfil ${operationProfileName}` : ''
  }.`
}

export function formatFiscalLineTotal(value: number | null | undefined): string {
  return formatCurrency(value ?? 0)
}
