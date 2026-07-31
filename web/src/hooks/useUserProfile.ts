import { useContext } from 'react'
import { UserProfileContext } from '../contexts/user-profile-context'

export function useUserProfile() {
  const context = useContext(UserProfileContext)
  if (!context) {
    throw new Error('useUserProfile must be used within a UserProfileProvider')
  }
  return context
}
