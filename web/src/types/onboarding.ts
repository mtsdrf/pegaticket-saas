export interface OnboardingChecklistStep {
  key: string
  label: string
  to: string
  link_label: string
  completed: boolean
}

/** Espelha `OnboardingService::checklist()` (backend). */
export interface OnboardingChecklist {
  has_product: boolean
  has_client: boolean
  has_first_order: boolean
  has_store_address?: boolean
  storefront_configured?: boolean
  steps: OnboardingChecklistStep[]
  is_dismissed: boolean
  dismissed_at: string | null
  completed: number
  total: number
}
