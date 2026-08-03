import CameraAltOutlinedIcon from '@mui/icons-material/CameraAltOutlined'
import KeyboardOutlinedIcon from '@mui/icons-material/KeyboardOutlined'
import PowerSettingsNewOutlinedIcon from '@mui/icons-material/PowerSettingsNewOutlined'
import CameraswitchOutlinedIcon from '@mui/icons-material/CameraswitchOutlined'
import QrCodeScannerOutlinedIcon from '@mui/icons-material/QrCodeScannerOutlined'
import { Alert, Box, Button, CircularProgress, MenuItem, Stack, TextField, Typography } from '@mui/material'
import { Html5Qrcode, Html5QrcodeScannerState, Html5QrcodeSupportedFormats, type CameraDevice } from 'html5-qrcode'
import { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react'
import { UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'

type ScannerStatus = 'idle' | 'starting' | 'running' | 'error'

interface QrCodeScannerPanelProps {
  /** Enquanto `true`, o scanner mantém a câmera ligada mas ignora leituras (cooldown pós check-in). */
  paused: boolean
  onDetected: (text: string) => void
  onSwitchToManual: () => void
}

function describeCameraError(error: unknown): string {
  const name = error instanceof DOMException ? error.name : ''
  const raw = error instanceof Error ? error.message : String(error)

  if (name === 'AbortError' || /aborted by the user agent/i.test(raw)) {
    return 'A ativação da câmera foi interrompida. Tente novamente e aguarde a câmera iniciar antes de trocar de modo.'
  }
  if (name === 'NotAllowedError' || /Permission denied/i.test(raw)) {
    return 'Permissão de câmera negada. Autorize o acesso à câmera nas configurações do navegador ou use a busca manual.'
  }
  if (name === 'NotFoundError' || /NotFoundError/i.test(raw)) {
    return 'Nenhuma câmera foi encontrada neste dispositivo. Use a busca manual.'
  }
  if (name === 'NotReadableError' || /NotReadableError/i.test(raw)) {
    return 'A câmera está em uso por outro aplicativo. Feche-o e tente novamente, ou use a busca manual.'
  }

  return 'Não foi possível acessar a câmera. Use a busca manual.'
}

function normalizeCameraLabel(camera: CameraDevice, index: number): string {
  const label = camera.label.trim()

  if (/back|rear|traseira|environment/i.test(label)) {
    return `Câmera traseira${label ? ` · ${label}` : ''}`
  }
  if (/front|frontal|user/i.test(label)) {
    return `Câmera frontal${label ? ` · ${label}` : ''}`
  }

  return label || `Câmera ${index + 1}`
}

function pickDefaultCameraId(cameras: CameraDevice[]): string {
  const rear = cameras.find((camera) => /back|rear|traseira|environment/i.test(camera.label))
  return rear?.id ?? cameras[0]?.id ?? ''
}

/**
 * Painel de leitura QR controlado pelo operador: a câmera começa DESLIGADA
 * e só é ativada quando ele clica em "Ativar leitor QR". Isso evita deixar
 * a permissão/captura da câmera abertas o tempo todo no navegador.
 */
export function QrCodeScannerPanel({ paused, onDetected, onSwitchToManual }: QrCodeScannerPanelProps) {
  const elementId = useId().replace(/:/g, '')
  const scannerRef = useRef<Html5Qrcode | null>(null)
  const startSequenceRef = useRef(0)
  const onDetectedRef = useRef(onDetected)
  const pausedRef = useRef(paused)
  onDetectedRef.current = onDetected
  pausedRef.current = paused

  const [status, setStatus] = useState<ScannerStatus>('idle')
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [cameras, setCameras] = useState<CameraDevice[]>([])
  const [selectedCameraId, setSelectedCameraId] = useState('')

  const cameraOptions = useMemo(
    () =>
      cameras.map((camera, index) => ({
        id: camera.id,
        label: normalizeCameraLabel(camera, index),
      })),
    [cameras],
  )

  const stopScanner = useCallback(async () => {
    const scanner = scannerRef.current
    if (!scanner) return

    try {
      const state = scanner.getState()
      if (state === Html5QrcodeScannerState.SCANNING || state === Html5QrcodeScannerState.PAUSED) {
        await scanner.stop()
      }
    } catch {
      // scanner já parado ou ainda não iniciado
    }

    try {
      await scanner.clear()
    } catch {
      // elemento já limpo
    }
  }, [])

  const startScanner = useCallback(
    async (cameraId: string) => {
      if (!cameraId) return

      const sequence = ++startSequenceRef.current
      setStatus('starting')
      setErrorMessage(null)

      const existingScanner = scannerRef.current
      if (existingScanner) {
        await stopScanner()
      }

      const scanner = new Html5Qrcode(elementId, {
        formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
        verbose: false,
      })

      scannerRef.current = scanner

      try {
        await scanner.start(
          { deviceId: { exact: cameraId } },
          { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1 },
          (decodedText) => {
            if (pausedRef.current) return
            onDetectedRef.current(decodedText)
          },
          () => {
            // frame sem QR detectado
          },
        )
        if (sequence !== startSequenceRef.current) {
          await stopScanner()
          return
        }
        setStatus('running')
      } catch (error) {
        if (sequence !== startSequenceRef.current) {
          return
        }
        setStatus('error')
        setErrorMessage(describeCameraError(error))
      }
    },
    [elementId, stopScanner],
  )

  const handleActivate = useCallback(async () => {
    setStatus('starting')
    setErrorMessage(null)

    try {
      const devices = await Html5Qrcode.getCameras()
      setCameras(devices)

      const nextCameraId = selectedCameraId || pickDefaultCameraId(devices)
      setSelectedCameraId(nextCameraId)

      if (!nextCameraId) {
        setStatus('error')
        setErrorMessage('Nenhuma câmera foi encontrada neste dispositivo. Use a busca manual.')
        return
      }

      await startScanner(nextCameraId)
    } catch (error) {
      setStatus('error')
      setErrorMessage(describeCameraError(error))
    }
  }, [selectedCameraId, startScanner])

  const handleDeactivate = useCallback(async () => {
    startSequenceRef.current += 1
    await stopScanner()
    setStatus('idle')
    setErrorMessage(null)
  }, [stopScanner])

  const handleCameraChange = useCallback(
    async (cameraId: string) => {
      setSelectedCameraId(cameraId)
      if (status === 'running') {
        await startScanner(cameraId)
      }
    },
    [startScanner, status],
  )

  useEffect(() => {
    const scanner = scannerRef.current
    if (!scanner || status !== 'running') return

    try {
      const state = scanner.getState()
      if (paused && state === Html5QrcodeScannerState.SCANNING) {
        scanner.pause(true)
      } else if (!paused && state === Html5QrcodeScannerState.PAUSED) {
        scanner.resume()
      }
    } catch {
      // troca fora de ordem; próxima interação estabiliza
    }
  }, [paused, status])

  useEffect(() => {
    return () => {
      startSequenceRef.current += 1
      void stopScanner()
      scannerRef.current = null
    }
  }, [stopScanner])

  if (status === 'error') {
    return (
      <Box sx={{ p: 2.5, ...ELEVATED_SURFACE_SX }}>
        <Stack spacing={2}>
          <Alert severity="error" variant="outlined" role="alert">
            {errorMessage}
          </Alert>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.25}>
            <Button
              variant="contained"
              size="large"
              startIcon={<QrCodeScannerOutlinedIcon />}
              onClick={() => void handleActivate()}
              sx={{ minHeight: UI_SIZE.controlLarge }}
            >
              Tentar novamente
            </Button>
            <Button
              variant="outlined"
              size="large"
              startIcon={<KeyboardOutlinedIcon />}
              onClick={onSwitchToManual}
              sx={{ minHeight: UI_SIZE.controlLarge }}
            >
              Buscar manualmente
            </Button>
          </Stack>
        </Stack>
      </Box>
    )
  }

  return (
    <Box sx={{ p: 2, ...ELEVATED_SURFACE_SX }}>
      <Stack spacing={2}>
        <Stack spacing={0.75}>
          <Typography sx={{ fontWeight: 700 }}>Leitor QR</Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
            A câmera só é ligada quando você ativar a leitura. Depois disso, você pode trocar entre frontal e traseira.
          </Typography>
        </Stack>

        {cameraOptions.length > 0 && (
          <TextField
            select
            label="Câmera"
            value={selectedCameraId}
            onChange={(event) => void handleCameraChange(event.target.value)}
            fullWidth
            size="small"
            disabled={status === 'starting'}
          >
            {cameraOptions.map((option) => (
              <MenuItem key={option.id} value={option.id}>
                {option.label}
              </MenuItem>
            ))}
          </TextField>
        )}

        <Box
          sx={{
            display: 'flex',
            justifyContent: 'center',
            width: '100%',
          }}
        >
          <Box
            sx={{
              position: 'relative',
              width: '100%',
              maxWidth: 520,
              aspectRatio: '1 / 1',
              borderRadius: 'var(--pt-radius-lg)',
              overflow: 'hidden',
              bgcolor: 'var(--pt-surface-soft-bg)',
              border: '1px solid var(--pt-border)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              '& video': {
                width: '100% !important',
                height: '100% !important',
                objectFit: 'cover',
              },
              '& #qr-shaded-region': {
                border: 'none !important',
              },
            }}
          >
            <Box id={elementId} sx={{ width: '100%', height: '100%' }} />

            {status === 'idle' && (
              <Stack
                spacing={1.5}
                sx={{
                  position: 'absolute',
                  inset: 0,
                  alignItems: 'center',
                  justifyContent: 'center',
                  px: 3,
                  textAlign: 'center',
                  bgcolor: 'var(--pt-surface-soft-bg)',
                }}
              >
                <QrCodeScannerOutlinedIcon sx={{ fontSize: 42, color: 'var(--pt-primary)' }} />
                <Typography sx={{ fontWeight: 700 }}>Câmera desligada</Typography>
                <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', maxWidth: 320 }}>
                  Clique para ativar a leitura de QR Code quando estiver pronto para validar ingressos.
                </Typography>
              </Stack>
            )}

            {status === 'starting' && (
              <Box
                sx={{
                  position: 'absolute',
                  inset: 0,
                  display: 'flex',
                  flexDirection: 'column',
                  alignItems: 'center',
                  justifyContent: 'center',
                  gap: 1,
                  bgcolor: 'var(--pt-surface-soft-bg)',
                }}
              >
                <CircularProgress size={28} sx={{ color: 'var(--pt-primary)' }} />
                <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>Ativando câmera…</Typography>
              </Box>
            )}

            {status === 'running' && (
              <Box
                aria-hidden
                sx={{
                  position: 'absolute',
                  inset: 0,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  pointerEvents: 'none',
                }}
              >
                <Box
                  sx={{
                    width: '62%',
                    height: '62%',
                    borderRadius: 'var(--pt-radius-md)',
                    border: '3px solid',
                    borderColor: paused ? 'var(--pt-warning)' : 'var(--pt-primary)',
                    boxShadow: '0 0 0 999px color-mix(in srgb, black 35%, transparent)',
                    transition: 'border-color 160ms ease',
                  }}
                />
              </Box>
            )}

            {status === 'running' && paused && (
              <Box
                sx={{
                  position: 'absolute',
                  inset: 0,
                  display: 'flex',
                  flexDirection: 'column',
                  alignItems: 'center',
                  justifyContent: 'center',
                  gap: 1,
                  bgcolor: 'color-mix(in srgb, black 45%, transparent)',
                }}
              >
                <CircularProgress size={28} sx={{ color: 'var(--pt-warning)' }} />
                <Typography sx={{ fontSize: 13, fontWeight: 600, color: '#fff' }}>Processando…</Typography>
              </Box>
            )}
          </Box>
        </Box>

        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.25} sx={{ justifyContent: 'center' }}>
          {status === 'running' ? (
            <>
              <Button
                variant="outlined"
                size="large"
                color="inherit"
                startIcon={<PowerSettingsNewOutlinedIcon />}
                onClick={() => void handleDeactivate()}
                sx={{ minHeight: UI_SIZE.controlLarge }}
              >
                Desligar câmera
              </Button>
              {cameraOptions.length > 1 && (
                <Button
                  variant="text"
                  size="large"
                  startIcon={<CameraswitchOutlinedIcon />}
                  onClick={() => {
                    const currentIndex = cameraOptions.findIndex((option) => option.id === selectedCameraId)
                    const nextIndex = currentIndex >= 0 ? (currentIndex + 1) % cameraOptions.length : 0
                    const nextCameraId = cameraOptions[nextIndex]?.id ?? selectedCameraId
                    void handleCameraChange(nextCameraId)
                  }}
                  sx={{ minHeight: UI_SIZE.controlLarge }}
                >
                  Trocar câmera
                </Button>
              )}
            </>
          ) : (
            <Button
              variant="contained"
              size="large"
              startIcon={<CameraAltOutlinedIcon />}
              onClick={() => void handleActivate()}
              disabled={status === 'starting'}
              sx={{ minHeight: UI_SIZE.controlLarge }}
            >
              Ativar leitor QR
            </Button>
          )}

          <Button
            variant="text"
            size="large"
            startIcon={<KeyboardOutlinedIcon fontSize="small" />}
            onClick={onSwitchToManual}
            sx={{ minHeight: UI_SIZE.controlLarge }}
          >
            Prefiro digitar
          </Button>
        </Stack>

        <Stack direction="row" spacing={1} sx={{ alignItems: 'center', justifyContent: 'center', color: 'var(--pt-muted)' }}>
          <CameraAltOutlinedIcon sx={{ fontSize: 16 }} />
          <Typography sx={{ fontSize: 12.5, textAlign: 'center' }}>
            {status === 'running'
              ? paused
                ? 'Aguarde, confirmando o último ingresso lido…'
                : 'Aponte a câmera para o QR Code do ingresso.'
              : 'A câmera permanece desligada até você iniciar a leitura.'}
          </Typography>
        </Stack>
      </Stack>
    </Box>
  )
}
