import { STORAGE_KEYS } from '../constants/storage'

interface TrainingCenterStoredProgress {
  completedModuleIds: string[]
  startedModuleIds: string[]
  unlockedTrackIds: string[]
  quizCorrectByModule: Record<string, number>
  lastModuleId: string | null
  lastTrackId: string | null
  updatedAt: string | null
}

const EMPTY_PROGRESS: TrainingCenterStoredProgress = {
  completedModuleIds: [],
  startedModuleIds: [],
  unlockedTrackIds: [],
  quizCorrectByModule: {},
  lastModuleId: null,
  lastTrackId: null,
  updatedAt: null,
}

function storageKey(userUuid: string | null | undefined, tenantUuid: string | null | undefined): string | null {
  if (!userUuid || !tenantUuid) return null
  return `${STORAGE_KEYS.trainingCenterProgress}.${userUuid}.${tenantUuid}`
}

export function readTrainingCenterProgress(
  userUuid: string | null | undefined,
  tenantUuid: string | null | undefined,
): TrainingCenterStoredProgress {
  const key = storageKey(userUuid, tenantUuid)
  if (!key) return EMPTY_PROGRESS

  try {
    const raw = localStorage.getItem(key)
    if (!raw) return EMPTY_PROGRESS

    const parsed = JSON.parse(raw) as Partial<TrainingCenterStoredProgress>

    return {
      completedModuleIds: Array.isArray(parsed.completedModuleIds) ? parsed.completedModuleIds.filter((item): item is string => typeof item === 'string') : [],
      startedModuleIds: Array.isArray(parsed.startedModuleIds) ? parsed.startedModuleIds.filter((item): item is string => typeof item === 'string') : [],
      unlockedTrackIds: Array.isArray(parsed.unlockedTrackIds) ? parsed.unlockedTrackIds.filter((item): item is string => typeof item === 'string') : [],
      quizCorrectByModule:
        parsed.quizCorrectByModule && typeof parsed.quizCorrectByModule === 'object' ? Object.fromEntries(Object.entries(parsed.quizCorrectByModule).filter((entry): entry is [string, number] => typeof entry[0] === 'string' && typeof entry[1] === 'number')) : {},
      lastModuleId: typeof parsed.lastModuleId === 'string' ? parsed.lastModuleId : null,
      lastTrackId: typeof parsed.lastTrackId === 'string' ? parsed.lastTrackId : null,
      updatedAt: typeof parsed.updatedAt === 'string' ? parsed.updatedAt : null,
    }
  } catch {
    return EMPTY_PROGRESS
  }
}

export function writeTrainingCenterProgress(
  userUuid: string | null | undefined,
  tenantUuid: string | null | undefined,
  progress: TrainingCenterStoredProgress,
): void {
  const key = storageKey(userUuid, tenantUuid)
  if (!key) return

  localStorage.setItem(
    key,
    JSON.stringify({
      ...progress,
      updatedAt: new Date().toISOString(),
    }),
  )
}

export function trainingCenterEmptyProgress(): TrainingCenterStoredProgress {
  return EMPTY_PROGRESS
}

export type { TrainingCenterStoredProgress }
