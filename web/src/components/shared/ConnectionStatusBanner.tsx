import CheckCircleOutlineOutlinedIcon from '@mui/icons-material/CheckCircleOutlineOutlined'
import CloudOffOutlinedIcon from '@mui/icons-material/CloudOffOutlined'
import WifiOffOutlinedIcon from '@mui/icons-material/WifiOffOutlined'
import { Box, Chip, Stack, Typography } from '@mui/material'
import { useEffect, useRef, useState } from 'react'
import {
  API_OFFLINE_EVENT,
  API_ONLINE_EVENT,
  type ConnectionEventDetail,
} from '../../utils/connectionEvents'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'

type ConnectionBannerState = 'hidden' | 'offline-browser' | 'offline-api' | 'restored'

const RESTORED_VISIBILITY_MS = 3200

export function ConnectionStatusBanner() {
  const [bannerState, setBannerState] = useState<ConnectionBannerState>(() =>
    navigator.onLine ? 'hidden' : 'offline-browser',
  )
  const [message, setMessage] = useState(() =>
    navigator.onLine ? '' : 'Seu dispositivo está sem internet. O sistema continuará tentando reconectar.',
  )
  const restoreTimerRef = useRef<number | null>(null)
  const hasLostConnectionRef = useRef(!navigator.onLine)

  useEffect(() => {
    function clearRestoreTimer() {
      if (restoreTimerRef.current) {
        window.clearTimeout(restoreTimerRef.current)
        restoreTimerRef.current = null
      }
    }

    function showBrowserOffline() {
      clearRestoreTimer()
      hasLostConnectionRef.current = true
      setBannerState('offline-browser')
      setMessage('Seu dispositivo está sem internet. Algumas áreas podem ficar indisponíveis até a conexão voltar.')
    }

    function showApiOffline() {
      clearRestoreTimer()
      hasLostConnectionRef.current = true
      setBannerState('offline-api')
      setMessage('Não foi possível alcançar o servidor agora. O app tentará reconectar automaticamente.')
    }

    function showRestored() {
      if (!navigator.onLine) {
        showBrowserOffline()
        return
      }

      if (!hasLostConnectionRef.current) {
        clearRestoreTimer()
        setBannerState('hidden')
        setMessage('')
        return
      }

      clearRestoreTimer()
      setBannerState('restored')
      setMessage('Conexão restabelecida. Você já pode continuar operando normalmente.')
      restoreTimerRef.current = window.setTimeout(() => {
        setBannerState('hidden')
        setMessage('')
        restoreTimerRef.current = null
      }, RESTORED_VISIBILITY_MS)
    }

    function handleBrowserOffline() {
      showBrowserOffline()
    }

    function handleBrowserOnline() {
      showRestored()
    }

    function handleApiOffline(event: Event) {
      const detail = (event as CustomEvent<ConnectionEventDetail>).detail
      if (detail?.source === 'api') {
        showApiOffline()
      }
    }

    function handleApiOnline(event: Event) {
      const detail = (event as CustomEvent<ConnectionEventDetail>).detail
      if (detail?.source === 'api') {
        showRestored()
      }
    }

    window.addEventListener('offline', handleBrowserOffline)
    window.addEventListener('online', handleBrowserOnline)
    window.addEventListener(API_OFFLINE_EVENT, handleApiOffline)
    window.addEventListener(API_ONLINE_EVENT, handleApiOnline)

    return () => {
      clearRestoreTimer()
      window.removeEventListener('offline', handleBrowserOffline)
      window.removeEventListener('online', handleBrowserOnline)
      window.removeEventListener(API_OFFLINE_EVENT, handleApiOffline)
      window.removeEventListener(API_ONLINE_EVENT, handleApiOnline)
    }
  }, [])

  if (bannerState === 'hidden') return null

  const isOffline = bannerState === 'offline-browser' || bannerState === 'offline-api'
  const Icon =
    bannerState === 'offline-browser'
      ? WifiOffOutlinedIcon
      : bannerState === 'offline-api'
        ? CloudOffOutlinedIcon
        : CheckCircleOutlineOutlinedIcon

  return (
    <Box
      role="status"
      aria-live="polite"
      sx={{
        position: 'fixed',
        top: { xs: 12, sm: 16 },
        left: '50%',
        transform: 'translateX(-50%)',
        zIndex: (theme) => theme.zIndex.snackbar + 1,
        width: 'min(calc(100vw - 24px), 720px)',
        pointerEvents: 'none',
      }}
    >
      <Stack
        direction="row"
        spacing={1.25}
        sx={{
          ...ELEVATED_SURFACE_SX,
          alignItems: 'center',
          px: { xs: 1.5, sm: 2 },
          py: 1.1,
          border: '1px solid',
          borderColor: isOffline
            ? 'color-mix(in srgb, var(--mk-warning) 45%, var(--mk-border))'
            : 'color-mix(in srgb, var(--mk-success) 36%, var(--mk-border))',
          background: isOffline
            ? 'linear-gradient(180deg, color-mix(in srgb, #FFF2CC 92%, white) 0%, color-mix(in srgb, #FFE7A3 90%, white) 100%)'
            : 'linear-gradient(180deg, color-mix(in srgb, #EAF8EE 96%, white) 0%, color-mix(in srgb, #D6F2DF 94%, white) 100%)',
          color: '#10213E',
        }}
      >
        <Box
          sx={{
            display: 'inline-flex',
            alignItems: 'center',
            justifyContent: 'center',
            width: 34,
            height: 34,
            borderRadius: '12px',
            background: isOffline ? 'rgba(255, 186, 66, 0.22)' : 'rgba(38, 164, 88, 0.14)',
            flexShrink: 0,
          }}
        >
          <Icon sx={{ fontSize: 18, color: isOffline ? '#8A5A00' : '#1F7A43' }} />
        </Box>

        <Box sx={{ minWidth: 0, flex: 1 }}>
          <Stack
            direction={{ xs: 'column', sm: 'row' }}
            spacing={{ xs: 0.35, sm: 1 }}
            sx={{ alignItems: { xs: 'flex-start', sm: 'center' }, minWidth: 0 }}
          >
            <Typography sx={{ fontSize: 13.5, fontWeight: 700, lineHeight: 1.25 }}>
              {isOffline ? 'Conexão instável' : 'Conexão restabelecida'}
            </Typography>
            <Chip
              size="small"
              label={isOffline ? 'Operação com atenção' : 'Online novamente'}
              sx={{
                height: 22,
                fontSize: 11.5,
                fontWeight: 700,
                backgroundColor: isOffline ? 'rgba(255, 186, 66, 0.24)' : 'rgba(38, 164, 88, 0.18)',
                color: isOffline ? '#7A5000' : '#1F7A43',
              }}
            />
          </Stack>
          <Typography sx={{ mt: 0.15, fontSize: 12.5, lineHeight: 1.45, color: 'rgba(16, 33, 62, 0.86)' }}>
            {message}
          </Typography>
        </Box>
      </Stack>
    </Box>
  )
}
