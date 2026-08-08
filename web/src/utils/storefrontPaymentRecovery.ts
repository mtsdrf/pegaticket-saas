import type { PaymentMethod } from '../constants/paymentMethods'

export type RecoveryPaymentFlowMethod = 'pix' | 'credit_card' | 'debit_card'

export interface StorefrontPaymentRecoveryDraft {
  saleUuid: string
  slug: string
  savedAt: number
  clientName: string
  clientLastName: string
  clientEmail: string
  clientPhone: string
  clientTaxId: string
  notes: string
  intendedPaymentMethod: PaymentMethod | ''
  needsChange: boolean
  changeForAmount: string
  couponCode: string
  appliedCouponCode: string | null
  appliedDiscount: number
  participantsByItem: Record<string, Array<{ name: string; document: string }>>
  paymentFlow: {
    saleUuid: string
    method: RecoveryPaymentFlowMethod
    amount: string
    payer: {
      name: string
      email: string
      phone: string
      taxId: string
    }
    slug: string
    eventSlug: string | null
    sessionId: string
  } | null
  creditCardForm?: {
    payerTaxId: string
    holderName: string
    holderTaxId: string
    installments: string
  }
  debitCardForm?: {
    payerTaxId: string
    holderName: string
    holderTaxId: string
    payerPhone: string
    addressLine1: string
    addressNumber: string
    addressComplement: string
    addressDistrict: string
    addressCity: string
    addressState: string
    addressZipCode: string
  }
}

const STORAGE_PREFIX = 'pegaticket.storefront.payment-recovery.'
const MAX_AGE_MS = 7 * 24 * 60 * 60 * 1000

function getStorageKey(saleUuid: string): string {
  return `${STORAGE_PREFIX}${saleUuid}`
}

function isFresh(savedAt: number): boolean {
  return Number.isFinite(savedAt) && Date.now() - savedAt <= MAX_AGE_MS
}

function sanitizeDraft(raw: unknown): StorefrontPaymentRecoveryDraft | null {
  if (!raw || typeof raw !== 'object') return null
  const candidate = raw as Partial<StorefrontPaymentRecoveryDraft>
  if (typeof candidate.saleUuid !== 'string' || typeof candidate.slug !== 'string' || typeof candidate.savedAt !== 'number') {
    return null
  }
  if (!isFresh(candidate.savedAt)) return null

  return {
    saleUuid: candidate.saleUuid,
    slug: candidate.slug,
    savedAt: candidate.savedAt,
    clientName: candidate.clientName ?? '',
    clientLastName: candidate.clientLastName ?? '',
    clientEmail: candidate.clientEmail ?? '',
    clientPhone: candidate.clientPhone ?? '',
    clientTaxId: candidate.clientTaxId ?? '',
    notes: candidate.notes ?? '',
    intendedPaymentMethod: candidate.intendedPaymentMethod ?? '',
    needsChange: candidate.needsChange ?? false,
    changeForAmount: candidate.changeForAmount ?? '',
    couponCode: candidate.couponCode ?? '',
    appliedCouponCode: candidate.appliedCouponCode ?? null,
    appliedDiscount: candidate.appliedDiscount ?? 0,
    participantsByItem: candidate.participantsByItem ?? {},
    paymentFlow: candidate.paymentFlow ?? null,
    creditCardForm: candidate.creditCardForm,
    debitCardForm: candidate.debitCardForm,
  }
}

export function loadStorefrontPaymentRecoveryDraft(saleUuid: string): StorefrontPaymentRecoveryDraft | null {
  try {
    const raw = window.localStorage.getItem(getStorageKey(saleUuid))
    if (!raw) return null
    const parsed = JSON.parse(raw)
    const draft = sanitizeDraft(parsed)
    if (!draft) {
      window.localStorage.removeItem(getStorageKey(saleUuid))
      return null
    }
    return draft
  } catch {
    return null
  }
}

export function saveStorefrontPaymentRecoveryDraft(draft: StorefrontPaymentRecoveryDraft): void {
  try {
    window.localStorage.setItem(
      getStorageKey(draft.saleUuid),
      JSON.stringify({
        ...draft,
        savedAt: Date.now(),
      } satisfies StorefrontPaymentRecoveryDraft),
    )
  } catch {
    // best effort only
  }
}

export function updateStorefrontPaymentRecoveryDraft(
  saleUuid: string,
  updater: (current: StorefrontPaymentRecoveryDraft | null) => StorefrontPaymentRecoveryDraft | null,
): void {
  const next = updater(loadStorefrontPaymentRecoveryDraft(saleUuid))
  if (!next) {
    clearStorefrontPaymentRecoveryDraft(saleUuid)
    return
  }
  saveStorefrontPaymentRecoveryDraft(next)
}

export function clearStorefrontPaymentRecoveryDraft(saleUuid: string): void {
  try {
    window.localStorage.removeItem(getStorageKey(saleUuid))
  } catch {
    // ignore
  }
}
