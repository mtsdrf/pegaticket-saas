import { createContext } from 'react'
import type { UserProfile } from '../types/profile'

export interface UserProfileContextValue {
  profile: UserProfile | null
  isLoading: boolean
  error: string | null
  refresh: () => Promise<void>
  setProfile: (profile: UserProfile) => void
}

export const UserProfileContext = createContext<UserProfileContextValue | null>(null)
