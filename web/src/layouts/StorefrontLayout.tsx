import { Box, CircularProgress } from '@mui/material'
import { useEffect, useState } from 'react'
import { Outlet, useLocation, useNavigate, useParams } from 'react-router-dom'
import { StorefrontBottomNav, STOREFRONT_BOTTOM_NAV_HEIGHT } from '../components/storefront/StorefrontBottomNav'
import { FLOATING_CHECKOUT_BAR_HEIGHT } from '../components/storefront/FloatingCheckoutBar'
import { InstallPrompt } from '../components/pwa/InstallPrompt'
import { ThemeFab } from '../components/ThemeFab'
import { PortalAuthProvider } from '../contexts/PortalAuthContext'
import { StorefrontCartProvider } from '../contexts/StorefrontCartContext'
import { useStorefrontCart } from '../hooks/useStorefrontCart'
import * as storefrontService from '../services/storefrontService'
import { CART_CHECKOUT_BAR_HEIGHT } from '../styles/layoutStandards'
import type { StorefrontTenant } from '../types/storefront'

/**
 * Nav + toggle de tema — precisa estar DENTRO do `StorefrontCartProvider`
 * pra saber se alguma barra fixa de "Finalizar"/checkout está visível (mesma
 * condição usada em cada página: rota exata + carrinho com item) e afastar o
 * `ThemeFab` pra cima dela, nunca sobrepondo o botão de ação. Cobre tanto o
 * catálogo (`FloatingCheckoutBar`) quanto o carrinho (`StorefrontCartPage`),
 * cada um com sua própria altura.
 */
function StorefrontChrome({ slug, tenant }: { slug: string; tenant: StorefrontTenant | null }) {
  const { pathname } = useLocation()
  const { totalQuantity } = useStorefrontCart()
  const isCatalogRoute = pathname === `/eventos/${slug}`
  const isCartRoute = pathname === `/eventos/${slug}/carrinho`
  const showFloatingBar = isCatalogRoute && totalQuantity > 0
  const showCartBar = isCartRoute && totalQuantity > 0

  // Carrinho: a barra fixa própria (`StorefrontCartPage`) fica em `bottom:0`
  // com z-index maior que o da bottom nav e cobre ela por completo (mesma
  // posição, fundo opaco) — não soma altura de nav nenhuma aqui, só a altura
  // da própria barra. Catálogo: a `FloatingCheckoutBar` fica EMPILHADA acima
  // da bottom nav (nav continua visível embaixo dela), então soma as duas
  // alturas. Nos dois casos, exatos 16px de respiro até o toggle, em
  // qualquer breakpoint (por isso `bottomFixed`, não `bottomOffset`).
  if (showCartBar) {
    return (
      <>
        <StorefrontBottomNav slug={slug} tenant={tenant} />
        <ThemeFab bottomFixed={CART_CHECKOUT_BAR_HEIGHT + 16} />
      </>
    )
  }

  if (showFloatingBar) {
    return (
      <>
        <StorefrontBottomNav slug={slug} tenant={tenant} />
        <ThemeFab bottomFixed={STOREFRONT_BOTTOM_NAV_HEIGHT + FLOATING_CHECKOUT_BAR_HEIGHT + 16} />
      </>
    )
  }

  return (
    <>
      <StorefrontBottomNav slug={slug} tenant={tenant} />
      <ThemeFab bottomOffset={STOREFRONT_BOTTOM_NAV_HEIGHT} />
    </>
  )
}

const DEFAULT_MANIFEST_HREF = '/site.webmanifest'
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? 'https://api.pegaticket.com/api/v1'

/**
 * Layout público da loja (Delivery Fase 1) — irmão de `PortalLayout` em
 * `AppRoutes.tsx`, nunca aninhado dentro do `AppLayout` de staff. Reaproveita
 * o `PortalAuthProvider` já existente (mesma identidade OTP do Portal, não
 * um contexto de auth novo) e adiciona só o `StorefrontCartProvider`
 * (carrinho local, escopado por slug).
 *
 * PWA por tenant: troca o `<link rel="manifest">` do `<head>` pro endpoint
 * dinâmico da loja (`StorefrontManifestController`) enquanto esta rota está
 * montada, e reverte pro manifest genérico do PegaTicket (`/site.webmanifest`)
 * no unmount — nunca deixa o manifest "vazado" pras rotas de staff.
 */
export function StorefrontLayout() {
  const { slug } = useParams<{ slug: string }>()
  const { pathname } = useLocation()
  const navigate = useNavigate()
  const [tenant, setTenant] = useState<StorefrontTenant | null>(null)

  useEffect(() => {
    if (!slug) return

    const link = document.querySelector<HTMLLinkElement>('link[rel="manifest"]')
    const previousHref = link?.getAttribute('href') ?? DEFAULT_MANIFEST_HREF
    link?.setAttribute('href', `${API_BASE_URL}/loja/${slug}/manifest.webmanifest`)

    return () => {
      link?.setAttribute('href', previousHref)
    }
  }, [slug])

  useEffect(() => {
    if (!slug) return
    let cancelled = false
    storefrontService
      .getStorefront(slug)
      .then((result) => {
        if (!cancelled) setTenant(result)
      })
      .catch(() => undefined)
    return () => {
      cancelled = true
    }
  }, [slug])

  useEffect(() => {
    if (!slug || !tenant || tenant.storefront_enabled) return

    if (pathname === `/eventos/${slug}/carrinho` || pathname === `/eventos/${slug}/checkout`) {
      navigate(`/eventos/${slug}`, { replace: true })
    }
  }, [navigate, pathname, slug, tenant])

  if (!slug) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', py: 10 }}>
        <CircularProgress size={28} />
      </Box>
    )
  }

  return (
    <PortalAuthProvider>
      <StorefrontCartProvider slug={slug}>
        {/* Reserva espaço para o nav fixo (altura + safe-area) — o conteúdo
            nunca fica escondido atrás dele; a região do padding fica atrás
            da barra fixa, então não sobra faixa visível. */}
        <Box sx={{ pb: `calc(${STOREFRONT_BOTTOM_NAV_HEIGHT}px + env(safe-area-inset-bottom, 0px))` }}>
          <Outlet />
        </Box>
        <InstallPrompt
          title={tenant?.name ? `Instalar a loja da ${tenant.name}` : 'Instalar esta loja'}
          description="Acesse o catálogo mais rápido, direto da tela inicial do aparelho."
          iosDescription='Toque em Compartilhar e depois em "Adicionar à Tela de Início".'
          dismissKeySuffix={slug}
        />
        <StorefrontChrome slug={slug} tenant={tenant} />
      </StorefrontCartProvider>
    </PortalAuthProvider>
  )
}
