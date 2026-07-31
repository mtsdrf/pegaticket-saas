import { useContext } from 'react'
import { StorefrontCartContext } from '../contexts/storefront-cart-context'

export function useStorefrontCart() {
  const context = useContext(StorefrontCartContext)
  if (!context) {
    throw new Error('useStorefrontCart must be used within a StorefrontCartProvider')
  }
  return context
}
