import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { OnboardingChecklist } from '../types/onboarding'

export function getOnboardingChecklist(): Promise<OnboardingChecklist> {
  return unwrap(apiClient.get<ApiSuccess<OnboardingChecklist>>('/onboarding/checklist'))
}

export function dismissOnboardingChecklist(): Promise<OnboardingChecklist> {
  return unwrap(apiClient.post<ApiSuccess<OnboardingChecklist>>('/onboarding/checklist/dismiss'))
}

export function restoreOnboardingChecklist(): Promise<OnboardingChecklist> {
  return unwrap(apiClient.delete<ApiSuccess<OnboardingChecklist>>('/onboarding/checklist/dismiss'))
}
