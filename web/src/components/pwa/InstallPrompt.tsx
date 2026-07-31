import CloseIcon from '@mui/icons-material/Close'
import InstallMobileOutlinedIcon from '@mui/icons-material/InstallMobileOutlined'
import IosShareOutlinedIcon from '@mui/icons-material/IosShareOutlined'
import { Box, Button, IconButton, Paper, Stack, Typography } from '@mui/material'
import { useCallback, useEffect, useState } from 'react'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'

/** Evento não padronizado (Chromium) — sem tipo oficial no lib.dom do TS. */
interface BeforeInstallPromptEvent extends Event {
  readonly platforms: string[]
  readonly userChoice: Promise<{ outcome: 'accepted' | 'dismissed'; platform: string }>
  prompt: () => Promise<void>
}

/** Dispensa o banner só pela sessão atual — reaparece se o app não for instalado numa próxima visita. */
const DISMISSED_KEY_BASE = 'maskats.install_prompt_dismissed'

function isStandaloneDisplay(): boolean {
  if (window.matchMedia('(display-mode: standalone)').matches) return true
  // iOS Safari não suporta `display-mode` media query — expõe isso via propriedade própria.
  return Boolean((window.navigator as Navigator & { standalone?: boolean }).standalone)
}

/**
 * iOS Safari nunca dispara `beforeinstallprompt` (limitação da Apple, não é
 * bug) — a única forma de "instalar" é o usuário usar Compartilhar → Adicionar
 * à Tela de Início manualmente. Detectamos esse caso só pra mostrar uma dica
 * estática, nunca fingimos que o botão automático funciona lá.
 */
function isIosSafari(): boolean {
  const ua = window.navigator.userAgent
  const isIos = /iphone|ipad|ipod/i.test(ua)
  const isOtherIosBrowser = /crios|fxios|edgios|opios/i.test(ua)
  return isIos && !isOtherIosBrowser
}

export interface InstallPromptProps {
  /** Default: "Instalar o Maskats" — a loja passa "Instalar a loja da {tenant}". */
  title?: string
  /** Default: copy genérica do app staff — a loja passa um texto próprio. */
  description?: string
  iosDescription?: string
  /**
   * Escopa a chave de dismissal (sessionStorage) por contexto — sem isso,
   * dispensar o banner numa loja pública suprimia o aviso em QUALQUER outra
   * loja/no app staff na mesma aba (achado de code review). Staff usa o
   * default (sem sufixo); StorefrontLayout passa o slug da loja.
   */
  dismissKeySuffix?: string
}

export function InstallPrompt({
  title = 'Instalar o Maskats',
  description = 'Acesse mais rápido, direto da tela inicial do aparelho.',
  iosDescription = 'Toque em Compartilhar e depois em "Adicionar à Tela de Início".',
  dismissKeySuffix,
}: InstallPromptProps = {}) {
  const dismissedKey = dismissKeySuffix ? `${DISMISSED_KEY_BASE}.${dismissKeySuffix}` : DISMISSED_KEY_BASE
  const [deferredPrompt, setDeferredPrompt] = useState<BeforeInstallPromptEvent | null>(null)
  const [isStandalone, setIsStandalone] = useState(() => isStandaloneDisplay())
  const [isDismissed, setIsDismissed] = useState(() => sessionStorage.getItem(dismissedKey) === '1')
  const [showIosTip, setShowIosTip] = useState(false)

  // Resincroniza se a chave mudar (ex.: navegação direta entre duas lojas
  // públicas diferentes sem remount do componente) — o initializer do
  // useState só roda uma vez, então sem isto o dismissal de uma loja
  // "vazaria" pra outra até o componente ser desmontado.
  useEffect(() => {
    setIsDismissed(sessionStorage.getItem(dismissedKey) === '1')
  }, [dismissedKey])

  useEffect(() => {
    if (isStandalone) return

    if (isIosSafari()) {
      setShowIosTip(true)
    }

    function handleBeforeInstallPrompt(event: Event) {
      event.preventDefault()
      setDeferredPrompt(event as BeforeInstallPromptEvent)
    }

    function handleAppInstalled() {
      setIsStandalone(true)
      setDeferredPrompt(null)
    }

    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt)
    window.addEventListener('appinstalled', handleAppInstalled)

    return () => {
      window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt)
      window.removeEventListener('appinstalled', handleAppInstalled)
    }
  }, [isStandalone])

  const handleInstallClick = useCallback(() => {
    if (!deferredPrompt) return

    void deferredPrompt
      .prompt()
      .then(() => deferredPrompt.userChoice)
      .catch(() => undefined)
      .finally(() => setDeferredPrompt(null))
  }, [deferredPrompt])

  const handleDismiss = useCallback(() => {
    sessionStorage.setItem(dismissedKey, '1')
    setIsDismissed(true)
  }, [dismissedKey])

  if (isStandalone || isDismissed) return null
  if (!deferredPrompt && !showIosTip) return null

  return (
    <Paper
      elevation={0}
      role="region"
      aria-label={title}
      sx={{
        position: 'fixed',
        left: 0,
        right: 0,
        bottom: 0,
        zIndex: (theme) => theme.zIndex.snackbar,
        m: { xs: 1, sm: 1.5 },
        p: 1.25,
        ...ELEVATED_SURFACE_SX,
        boxShadow: 'var(--mk-shadow-md)',
      }}
    >
      <Stack direction="row" spacing={1.25} sx={{ alignItems: 'center' }}>
        <Box
          sx={{
            display: { xs: 'none', sm: 'flex' },
            alignItems: 'center',
            justifyContent: 'center',
            width: 36,
            height: 36,
            ...SOFT_PANEL_SX,
            color: 'var(--mk-primary)',
            flexShrink: 0,
          }}
        >
          <InstallMobileOutlinedIcon fontSize="small" />
        </Box>

        <Box sx={{ flex: 1, minWidth: 0 }}>
          <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontSize: 14.5, fontWeight: 700, color: 'var(--mk-text)' }}>{title}</Typography>
          <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>
            {deferredPrompt ? description : iosDescription}
          </Typography>
        </Box>

        {deferredPrompt ? (
          <Button
            variant="contained"
            size="small"
            onClick={handleInstallClick}
            sx={{ minHeight: 44, minWidth: 44, flexShrink: 0, textTransform: 'none' }}
          >
            Instalar
          </Button>
        ) : (
          <IosShareOutlinedIcon fontSize="small" sx={{ color: 'var(--mk-muted)', flexShrink: 0 }} />
        )}

        <IconButton
          aria-label="Dispensar aviso de instalação"
          onClick={handleDismiss}
          size="small"
          sx={{ minHeight: 44, minWidth: 44, flexShrink: 0 }}
        >
          <CloseIcon fontSize="small" />
        </IconButton>
      </Stack>
    </Paper>
  )
}
