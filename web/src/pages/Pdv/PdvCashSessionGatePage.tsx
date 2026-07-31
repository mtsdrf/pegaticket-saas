import PointOfSaleOutlinedIcon from '@mui/icons-material/PointOfSaleOutlined'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  InputAdornment,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { AxiosError } from 'axios'
import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { Outlet } from 'react-router-dom'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import { ACCESS } from '../../access/requirements'
import { OfflineContingencyGuide } from '../../components/shared/OfflineContingencyGuide'
import * as pdvService from '../../services/pdvService'
import { getPdvOfflineSnapshot } from '../../services/pdvOfflineStore'
import { PAGE_CONTAINER_SX } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type { CashSession } from '../../types/pdv'
import { PdvSessionContext } from './PdvSessionContext'

/**
 * Gate de caixa: envolve toda a árvore `/pdv/*`. Consulta a sessão aberta
 * atual; sem caixa aberto mostra a abertura (troco inicial), com caixa aberto
 * libera as sub-rotas via `<Outlet />` + `PdvSessionContext`. Mesmo espírito
 * de `AccountingProtectedRoute`/`PortalProtectedRoute`, mas o critério é "tem
 * caixa aberto" em vez de "está autenticado".
 */
export function PdvCashSessionGatePage() {
  const { activeTenantUuid } = useAuth()
  const { can } = useAccessControl()
  const canOpen = can(ACCESS.pdvOpen)

  const [session, setSession] = useState<CashSession | null>(null)
  const [isOfflineSnapshot, setIsOfflineSnapshot] = useState(false)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [showOfflineContingency, setShowOfflineContingency] = useState(false)

  const [openingAmount, setOpeningAmount] = useState('0')
  const [isOpening, setIsOpening] = useState(false)
  const [openError, setOpenError] = useState<string | null>(null)

  const reload = useCallback(async () => {
    setIsLoading(true)
    setLoadError(null)
    try {
      setSession(await pdvService.getCurrentCashSession())
      setIsOfflineSnapshot(false)
      setShowOfflineContingency(false)
    } catch (error) {
      const canUseOfflineFallback = error instanceof AxiosError && !error.response
      if (canUseOfflineFallback && activeTenantUuid) {
        const snapshot = await getPdvOfflineSnapshot(activeTenantUuid)
        if (snapshot?.data.cash_session) {
          setSession(snapshot.data.cash_session)
          setIsOfflineSnapshot(true)
          setShowOfflineContingency(false)
          return
        }
      }

      setShowOfflineContingency(canUseOfflineFallback)
      setLoadError(getApiErrorMessage(error, 'Não foi possível verificar o caixa agora.'))
    } finally {
      setIsLoading(false)
    }
  }, [activeTenantUuid])

  useEffect(() => {
    void reload()
  }, [reload])

  async function handleOpen(event: FormEvent) {
    event.preventDefault()
    const amount = Number(openingAmount)
    if (!Number.isFinite(amount) || amount < 0) {
      setOpenError('Informe um valor de troco inicial válido.')
      return
    }

    setIsOpening(true)
    setOpenError(null)
    try {
      const opened = await pdvService.openCashSession({ opening_amount: amount })
      setSession(opened)
      setIsOfflineSnapshot(false)
    } catch (error) {
      setOpenError(getApiErrorMessage(error, 'Não foi possível abrir o caixa agora.'))
    } finally {
      setIsOpening(false)
    }
  }

  if (isLoading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', py: 10 }}>
        <CircularProgress size={28} />
      </Box>
    )
  }

  if (loadError) {
    return (
      <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 460, py: 4 }}>
        <Stack spacing={2}>
          <Alert severity="error" action={<Button onClick={() => void reload()}>Tentar novamente</Button>}>
            {loadError}
          </Alert>
          {showOfflineContingency ? <OfflineContingencyGuide context="pdv-no-snapshot" /> : null}
        </Stack>
      </Box>
    )
  }

  if (!session) {
    return (
      <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 460, py: { xs: 2, sm: 6 } }}>
        <Paper
          component="form"
          onSubmit={handleOpen}
          sx={{
            ...ELEVATED_SURFACE_SX,
            p: { xs: 3, sm: 4 },
          }}
        >
          <Stack spacing={2.5}>
            <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
              <Box
                sx={{
                  ...SOFT_PANEL_SX,
                  display: 'inline-flex',
                  p: 1.25,
                  color: 'var(--mk-primary)',
                }}
              >
                <PointOfSaleOutlinedIcon />
              </Box>
              <Box>
                <Typography variant="h6" sx={{ fontWeight: 700 }}>
                  Abrir caixa
                </Typography>
                <Typography variant="body2" sx={{ color: 'var(--mk-muted)' }}>
                  Informe o valor em dinheiro no caixa para começar as vendas.
                </Typography>
              </Box>
            </Stack>

            {openError ? <Alert severity="error">{openError}</Alert> : null}
            {!canOpen ? (
              <Alert severity="warning">Você não tem permissão para abrir o caixa. Contate um administrador.</Alert>
            ) : null}

            <TextField
              label="Troco inicial"
              value={openingAmount}
              onChange={(event) => setOpeningAmount(event.target.value)}
              type="number"
              inputMode="decimal"
              autoFocus
              required
              disabled={!canOpen || isOpening}
              slotProps={{
                htmlInput: { min: 0, step: '0.01' },
                input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
              }}
            />

            <Button type="submit" variant="contained" size="large" disabled={!canOpen || isOpening}>
              {isOpening ? 'Abrindo…' : 'Abrir caixa'}
            </Button>
          </Stack>
        </Paper>
      </Box>
    )
  }

  return (
    <PdvSessionContext.Provider value={{ session, isOfflineSnapshot, reload }}>
      <Outlet />
    </PdvSessionContext.Provider>
  )
}
