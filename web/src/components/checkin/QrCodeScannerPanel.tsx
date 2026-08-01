import CameraAltOutlinedIcon from '@mui/icons-material/CameraAltOutlined'
import KeyboardOutlinedIcon from '@mui/icons-material/KeyboardOutlined'
import { Alert, Box, Button, CircularProgress, Stack, Typography } from '@mui/material'
import { Html5Qrcode, Html5QrcodeScannerState, Html5QrcodeSupportedFormats } from 'html5-qrcode'
import { useEffect, useId, useRef, useState } from 'react'
import { UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'

type ScannerStatus = 'starting' | 'running' | 'error'

interface QrCodeScannerPanelProps {
  /** Enquanto `true`, o scanner mantém a câmera ligada mas ignora leituras (cooldown pós check-in). */
  paused: boolean
  onDetected: (text: string) => void
  onSwitchToManual: () => void
}

/**
 * Descreve o erro de câmera de forma amigável — `html5-qrcode` repassa o
 * `DOMException` cru do `getUserMedia` (ou uma string), então mapeamos os
 * casos mais comuns (permissão negada, sem câmera, em uso por outro app).
 */
function describeCameraError(error: unknown): string {
  const name = error instanceof DOMException ? error.name : ''
  const raw = error instanceof Error ? error.message : String(error)

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

/**
 * Preview de câmera para leitura de QR Code, usando a API de baixo nível
 * `Html5Qrcode` (não o widget `Html5QrcodeScanner`) para manter o layout
 * 100% nos tokens `--pt-*` do projeto em vez do CSS default da lib.
 */
export function QrCodeScannerPanel({ paused, onDetected, onSwitchToManual }: QrCodeScannerPanelProps) {
  const elementId = useId().replace(/:/g, '')
  const scannerRef = useRef<Html5Qrcode | null>(null)
  const onDetectedRef = useRef(onDetected)
  onDetectedRef.current = onDetected
  const pausedRef = useRef(paused)
  pausedRef.current = paused

  const [status, setStatus] = useState<ScannerStatus>('starting')
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false
    const scanner = new Html5Qrcode(elementId, {
      formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
      verbose: false,
    })
    scannerRef.current = scanner

    scanner
      .start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1 },
        (decodedText) => {
          if (pausedRef.current) return
          onDetectedRef.current(decodedText)
        },
        () => {
          // callback de "não encontrado neste frame" — disparado a cada frame sem QR, ignorar
        },
      )
      .then(() => {
        if (!cancelled) setStatus('running')
      })
      .catch((error: unknown) => {
        if (cancelled) return
        setStatus('error')
        setErrorMessage(describeCameraError(error))
      })

    return () => {
      cancelled = true
      const current = scannerRef.current
      scannerRef.current = null
      if (!current) return
      // `stop()` lança de forma síncrona (não rejeita a Promise) quando o
      // scanner nunca chegou a iniciar (ex.: permissão de câmera negada) —
      // por isso o try/catch em volta da chamada, além do `.catch()` da Promise.
      try {
        const state = current.getState()
        if (state !== Html5QrcodeScannerState.SCANNING && state !== Html5QrcodeScannerState.PAUSED) return
        current
          .stop()
          .then(() => current.clear())
          .catch(() => {
            // já parado nesse meio tempo — nada a fazer
          })
      } catch {
        // idem — scanner nunca chegou a iniciar
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- start/stop só deve rodar na montagem/desmontagem deste painel
  }, [elementId])

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
      // troca de estado fora de ordem (ex.: câmera parando) — ignorar, próximo ciclo corrige
    }
  }, [paused, status])

  if (status === 'error') {
    return (
      <Box sx={{ p: 2.5, ...ELEVATED_SURFACE_SX }}>
        <Stack spacing={2}>
          <Alert severity="error" variant="outlined" role="alert">
            {errorMessage}
          </Alert>
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
      </Box>
    )
  }

  return (
    <Box sx={{ p: 2, ...ELEVATED_SURFACE_SX }}>
      <Stack spacing={1.5}>
        <Box
          sx={{
            position: 'relative',
            width: '100%',
            aspectRatio: '1 / 1',
            maxWidth: 360,
            mx: 'auto',
            borderRadius: 'var(--pt-radius-lg)',
            overflow: 'hidden',
            bgcolor: 'var(--pt-surface-soft-bg)',
            border: '1px solid var(--pt-border)',
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

        <Stack direction="row" spacing={1} sx={{ alignItems: 'center', justifyContent: 'center', color: 'var(--pt-muted)' }}>
          <CameraAltOutlinedIcon sx={{ fontSize: 16 }} />
          <Typography sx={{ fontSize: 12.5 }}>
            {paused ? 'Aguarde, confirmando o último ingresso lido…' : 'Aponte a câmera para o QR Code do ingresso.'}
          </Typography>
        </Stack>

        <Button
          variant="text"
          size="small"
          startIcon={<KeyboardOutlinedIcon fontSize="small" />}
          onClick={onSwitchToManual}
          sx={{ minHeight: UI_SIZE.control, alignSelf: 'center' }}
        >
          Prefiro digitar
        </Button>
      </Stack>
    </Box>
  )
}
