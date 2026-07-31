import AltRouteOutlinedIcon from '@mui/icons-material/AltRouteOutlined'
import LocalShippingOutlinedIcon from '@mui/icons-material/LocalShippingOutlined'
import MapOutlinedIcon from '@mui/icons-material/MapOutlined'
import MyLocationOutlinedIcon from '@mui/icons-material/MyLocationOutlined'
import NavigationOutlinedIcon from '@mui/icons-material/NavigationOutlined'
import PaidOutlinedIcon from '@mui/icons-material/PaidOutlined'
import RefreshIcon from '@mui/icons-material/Refresh'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Paper,
  Skeleton,
  Stack,
  TextField,
  ToggleButton,
  ToggleButtonGroup,
  Tooltip,
  Typography,
} from '@mui/material'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { RouteCandidateCard } from '../../components/route/RouteCandidateCard'
import { RouteMap } from '../../components/route/RouteMap'
import { RouteResultStopCard } from '../../components/route/RouteResultStopCard'
import { OrderDetailDialog } from '../../components/order/OrderDetailDialog'
import { PageHeader } from '../../components/layout/PageHeader'
import { useAuth } from '../../hooks/useAuth'
import * as orderService from '../../services/orderService'
import { PAGE_CONTAINER_SX, UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { notifyRouteArrival, requestNotificationPermission } from '../../utils/localNotifications'
import {
  distanceInMeters,
  fetchOptimizedTrip,
  googleMapsRouteUrl,
  wazeNavigationUrl,
  type GeoPoint,
  type OptimizedTrip,
} from '../../services/osrmService'
import * as routeService from '../../services/routeService'
import { getApiErrorMessage } from '../../types/api'
import { isLocatedStop, type LocatedRouteStop, type RouteCandidatesResponse, type RouteType } from '../../types/route'
import { formatCurrency } from '../../utils/format'
import { toIsoDate } from '../../utils/period'
import { stopValue } from '../../utils/routeValue'

type GeoStatus = 'idle' | 'loading' | 'error'
type TripStatus = 'idle' | 'loading' | 'error' | 'ready'

/** Raio de proximidade pra considerar "o usuário está nesta parada agora". */
const NEARBY_THRESHOLD_METERS = 150

function geolocationErrorMessage(error: GeolocationPositionError): string {
  if (error.code === error.PERMISSION_DENIED) {
    return 'Permissão de localização negada. Ative a localização do navegador/celular para montar a rota e tente novamente.'
  }
  if (error.code === error.POSITION_UNAVAILABLE) {
    return 'Não foi possível obter sua localização atual. Verifique o GPS/sinal e tente novamente.'
  }
  if (error.code === error.TIMEOUT) {
    return 'A busca pela sua localização demorou demais. Tente novamente.'
  }
  return 'Não foi possível obter sua localização atual.'
}

export function RoutePlannerPage() {
  const { activeTenantUuid } = useAuth()

  const [date, setDate] = useState(() => toIsoDate(new Date()))
  const [type, setType] = useState<RouteType>('delivery')

  const [candidates, setCandidates] = useState<RouteCandidatesResponse | null>(null)
  const [isLoadingCandidates, setIsLoadingCandidates] = useState(false)
  const [candidatesError, setCandidatesError] = useState<string | null>(null)

  const [geoStatus, setGeoStatus] = useState<GeoStatus>('idle')
  const [geoError, setGeoError] = useState<string | null>(null)

  const [tripStatus, setTripStatus] = useState<TripStatus>('idle')
  const [tripError, setTripError] = useState<string | null>(null)
  const [origin, setOrigin] = useState<GeoPoint | null>(null)
  const [trip, setTrip] = useState<OptimizedTrip | null>(null)
  const [currentPosition, setCurrentPosition] = useState<GeoPoint | null>(null)
  // Paradas já notificadas como "chegou" na rota atual — evita notificar de
  // novo a cada re-render enquanto o motorista permanece perto (só dispara na
  // transição false -> true), sem duplicar a lógica de proximidade da UI.
  const notifiedStopsRef = useRef<Set<string>>(new Set())

  const [processingKey, setProcessingKey] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  /** `OrderDetailDialog` montado uma vez no nível da página (não por card) — controlado por esse uuid. */
  const [openOrderDetailUuid, setOpenOrderDetailUuid] = useState<string | null>(null)
  // Overrides locais (sessão atual) sobre o status vindo do payload de `/routes/candidates`
  // (`order.is_delivered`/`order.is_paid`/`installment.is_paid`). Ausência na Map = usa o
  // valor do payload; presença = ação confirmada nesta tela (marcar OU desfazer) tem prioridade.
  const [deliveredOverrides, setDeliveredOverrides] = useState<Map<string, boolean>>(new Map())
  const [paidOverrides, setPaidOverrides] = useState<Map<string, boolean>>(new Map())
  const [installmentPaidOverrides, setInstallmentPaidOverrides] = useState<Map<string, boolean>>(new Map())

  const loadCandidates = useCallback(() => {
    if (!activeTenantUuid) return
    setIsLoadingCandidates(true)
    setCandidatesError(null)
    routeService
      .getRouteCandidates(type, date)
      .then(setCandidates)
      .catch((error) => setCandidatesError(getApiErrorMessage(error, 'Não foi possível carregar os pedidos/parcelas desta data.')))
      .finally(() => setIsLoadingCandidates(false))
  }, [activeTenantUuid, type, date])

  useEffect(() => {
    loadCandidates()
    // Nova consulta invalida qualquer rota já montada (paradas mudaram).
    setTrip(null)
    setTripStatus('idle')
    setTripError(null)
    setOrigin(null)
    setDeliveredOverrides(new Map())
    setPaidOverrides(new Map())
    setInstallmentPaidOverrides(new Map())
  }, [loadCandidates])

  // Acompanha a posição do usuário ao vivo enquanto a rota está montada, pra
  // destacar a parada em que ele está no momento (não é só a origem
  // capturada uma vez ao montar a rota — o motorista se move entre paradas).
  useEffect(() => {
    if (tripStatus !== 'ready' || !('geolocation' in navigator)) return

    const watchId = navigator.geolocation.watchPosition(
      (position) => setCurrentPosition({ lat: position.coords.latitude, lng: position.coords.longitude }),
      () => undefined,
      { enableHighAccuracy: true, maximumAge: 20000 },
    )

    return () => navigator.geolocation.clearWatch(watchId)
  }, [tripStatus])

  // Notifica (via Service Worker) quando a posição atual entra no raio de uma
  // parada — só na transição false -> true (comparado com `notifiedStopsRef`),
  // nunca de novo enquanto o motorista permanecer na área.
  useEffect(() => {
    if (tripStatus !== 'ready' || !trip || !currentPosition) return

    const nearbyNow = new Set<string>()

    trip.stops.forEach((optimized) => {
      const stopPoint: GeoPoint = { lat: optimized.location[1], lng: optimized.location[0] }
      if (distanceInMeters(currentPosition, stopPoint) <= NEARBY_THRESHOLD_METERS) {
        nearbyNow.add(optimized.stop.client_uuid)

        if (!notifiedStopsRef.current.has(optimized.stop.client_uuid)) {
          void notifyRouteArrival(optimized.stop.client_uuid, optimized.stop.client_name)
        }
      }
    })

    notifiedStopsRef.current = nearbyNow
  }, [currentPosition, trip, tripStatus])

  const routableStops = useMemo<LocatedRouteStop[]>(
    () => (candidates?.stops.filter(isLocatedStop) ?? []),
    [candidates],
  )
  const unlocatedStops = useMemo(
    () => (candidates?.stops.filter((stop) => !isLocatedStop(stop)) ?? []),
    [candidates],
  )

  const buildTrip = useCallback(
    async (originPoint: GeoPoint) => {
      setTripStatus('loading')
      setTripError(null)
      notifiedStopsRef.current = new Set()
      try {
        const result = await fetchOptimizedTrip(originPoint, routableStops)
        setTrip(result)
        setTripStatus('ready')
      } catch (error) {
        setTripStatus('error')
        setTripError(getApiErrorMessage(error, 'Não foi possível montar a rota agora.'))
      }
    },
    [routableStops],
  )

  const handleBuildRoute = useCallback(() => {
    if (routableStops.length === 0) return

    setGeoError(null)
    setTripError(null)
    // Pedir permissão de notificação aqui, no clique explícito do usuário —
    // nunca no carregamento da página. Não bloqueia a montagem da rota.
    void requestNotificationPermission()

    if (!('geolocation' in navigator)) {
      setGeoStatus('error')
      setGeoError('Seu navegador não oferece suporte a geolocalização. Não é possível montar a rota automaticamente.')
      return
    }

    setGeoStatus('loading')
    navigator.geolocation.getCurrentPosition(
      (position) => {
        const nextOrigin = { lat: position.coords.latitude, lng: position.coords.longitude }
        setOrigin(nextOrigin)
        setGeoStatus('idle')
        void buildTrip(nextOrigin)
      },
      (error) => {
        setGeoStatus('error')
        setGeoError(geolocationErrorMessage(error))
      },
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 },
    )
  }, [routableStops, buildTrip])

  const handleDeliver = useCallback(async (orderUuid: string) => {
    setActionError(null)
    setProcessingKey(`order:${orderUuid}`)
    try {
      await orderService.deliverOrder(orderUuid)
      setDeliveredOverrides((prev) => new Map(prev).set(orderUuid, true))
    } catch (error) {
      setActionError(getApiErrorMessage(error, 'Não foi possível marcar este pedido como entregue agora.'))
    } finally {
      setProcessingKey(null)
    }
  }, [])

  const handleUndeliver = useCallback(async (orderUuid: string) => {
    setActionError(null)
    setProcessingKey(`order:${orderUuid}`)
    try {
      await orderService.undeliverOrder(orderUuid)
      setDeliveredOverrides((prev) => new Map(prev).set(orderUuid, false))
    } catch (error) {
      setActionError(getApiErrorMessage(error, 'Não foi possível desfazer a entrega deste pedido agora.'))
    } finally {
      setProcessingKey(null)
    }
  }, [])

  const handlePayOrder = useCallback(async (orderUuid: string) => {
    setActionError(null)
    setProcessingKey(`order-pay:${orderUuid}`)
    try {
      await orderService.payOrder(orderUuid)
      setPaidOverrides((prev) => new Map(prev).set(orderUuid, true))
    } catch (error) {
      setActionError(getApiErrorMessage(error, 'Não foi possível marcar este pedido como pago agora.'))
    } finally {
      setProcessingKey(null)
    }
  }, [])

  const handleUnpayOrder = useCallback(async (orderUuid: string) => {
    setActionError(null)
    setProcessingKey(`order-pay:${orderUuid}`)
    try {
      await orderService.unpayOrder(orderUuid)
      setPaidOverrides((prev) => new Map(prev).set(orderUuid, false))
    } catch (error) {
      setActionError(getApiErrorMessage(error, 'Não foi possível desfazer o pagamento deste pedido agora.'))
    } finally {
      setProcessingKey(null)
    }
  }, [])

  const handlePayInstallment = useCallback(async (orderUuid: string, installmentUuid: string) => {
    setActionError(null)
    setProcessingKey(`installment:${installmentUuid}`)
    try {
      await orderService.payInstallment(orderUuid, installmentUuid)
      setInstallmentPaidOverrides((prev) => new Map(prev).set(installmentUuid, true))
    } catch (error) {
      setActionError(getApiErrorMessage(error, 'Não foi possível marcar esta parcela como paga agora.'))
    } finally {
      setProcessingKey(null)
    }
  }, [])

  const handleUnpayInstallment = useCallback(async (orderUuid: string, installmentUuid: string) => {
    setActionError(null)
    setProcessingKey(`installment:${installmentUuid}`)
    try {
      await orderService.unpayInstallment(orderUuid, installmentUuid)
      setInstallmentPaidOverrides((prev) => new Map(prev).set(installmentUuid, false))
    } catch (error) {
      setActionError(getApiErrorMessage(error, 'Não foi possível desfazer o pagamento desta parcela agora.'))
    } finally {
      setProcessingKey(null)
    }
  }, [])

  const totalStops = candidates?.stops.length ?? 0
  const isBuildingRoute = geoStatus === 'loading' || tripStatus === 'loading'

  return (
    <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 1600 }}>
      <PageHeader title="Montar rota" subtitle="Selecione a data e o tipo de visita para montar o itinerário do dia." />

      <Paper
        variant="outlined"
        sx={{ p: { xs: 1.5, sm: 2 }, ...ELEVATED_SURFACE_SX, mb: 2.5 }}
      >
        <Stack
          direction={{ xs: 'column', sm: 'row' }}
          spacing={1}
          sx={{ alignItems: { xs: 'stretch', sm: 'center' }, flexWrap: 'wrap' }}
        >
          <TextField
            label="Data"
            type="date"
            size="small"
            value={date}
            onChange={(event) => setDate(event.target.value)}
            slotProps={{ inputLabel: { shrink: true } }}
            sx={{ minWidth: { sm: 180 } }}
          />

          <ToggleButtonGroup
            exclusive
            value={type}
            size="small"
            onChange={(_event, next: RouteType | null) => next && setType(next)}
            aria-label="Tipo de visita"
            fullWidth
            sx={{ width: { xs: '100%', sm: 'auto' } }}
          >
            <ToggleButton value="delivery" sx={{ minHeight: UI_SIZE.control, px: 2, textTransform: 'none', flex: { xs: 1, sm: 'initial' }, borderRadius: UI_RADIUS.md }}>
              <LocalShippingOutlinedIcon fontSize="small" sx={{ mr: 1 }} />
              Entregar
            </ToggleButton>
            <ToggleButton value="collection" sx={{ minHeight: UI_SIZE.control, px: 2, textTransform: 'none', flex: { xs: 1, sm: 'initial' }, borderRadius: UI_RADIUS.md }}>
              <PaidOutlinedIcon fontSize="small" sx={{ mr: 1 }} />
              Cobrar
            </ToggleButton>
          </ToggleButtonGroup>
        </Stack>
      </Paper>

      {isLoadingCandidates ? (
        <Stack spacing={1.5}>
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} variant="rounded" height={96} sx={{ borderRadius: 'var(--mk-radius-lg)' }} />
          ))}
        </Stack>
      ) : candidatesError ? (
        <Alert
          severity="error"
          variant="outlined"
          action={
            <Button color="inherit" size="small" startIcon={<RefreshIcon />} onClick={loadCandidates}>
              Tentar novamente
            </Button>
          }
        >
          {candidatesError}
        </Alert>
      ) : totalStops === 0 ? (
        <Paper
          variant="outlined"
          sx={{
            p: { xs: 3, sm: 4 },
            ...ELEVATED_SURFACE_SX,
            textAlign: 'center',
            color: 'var(--mk-muted)',
          }}
        >
          <Typography sx={{ fontWeight: 600, color: 'var(--mk-text)', fontSize: 15, mb: 0.5 }}>
            {type === 'delivery' ? 'Nenhum pedido para essa data.' : 'Nenhuma parcela para essa data.'}
          </Typography>
          <Typography sx={{ fontSize: 13.5 }}>Escolha outra data ou tipo de visita.</Typography>
        </Paper>
      ) : (
        <Stack spacing={2.5}>
          <Box>
            <Typography sx={{ fontSize: 13, fontWeight: 600, color: 'var(--mk-muted)', mb: 1.25, textTransform: 'uppercase', letterSpacing: 0.4 }}>
              Paradas roteirizáveis ({routableStops.length})
            </Typography>

            <Button
              variant="contained"
              fullWidth
              startIcon={isBuildingRoute ? <CircularProgress size={16} sx={{ color: 'inherit' }} /> : <AltRouteOutlinedIcon />}
              disabled={routableStops.length === 0 || isBuildingRoute}
              onClick={handleBuildRoute}
              sx={{ minHeight: UI_SIZE.controlLarge, mb: 1.25, borderRadius: UI_RADIUS.md }}
            >
              {geoStatus === 'loading' ? 'Obtendo localização…' : tripStatus === 'loading' ? 'Calculando itinerário…' : 'Montar rota'}
            </Button>

            {routableStops.length === 0 ? (
              <Alert severity="info" variant="outlined">
                Nenhuma parada com localização confirmada nesta data — veja a lista "Sem localização" abaixo para contato manual.
              </Alert>
            ) : (
              <Stack spacing={1.25} sx={{ maxHeight: { xs: 360, sm: 480 }, overflowY: 'auto', pr: 0.5 }}>
                {routableStops.map((stop) => (
                  <RouteCandidateCard key={stop.client_uuid} stop={stop} type={type} />
                ))}
              </Stack>
            )}
          </Box>

          {unlocatedStops.length > 0 && (
            <Box>
              <Typography
                sx={{ fontSize: 13, fontWeight: 600, color: 'var(--mk-muted)', mb: 1.25, textTransform: 'uppercase', letterSpacing: 0.4 }}
              >
                Sem localização ({unlocatedStops.length})
              </Typography>
              <Stack spacing={1.25} sx={{ maxHeight: { xs: 360, sm: 480 }, overflowY: 'auto', pr: 0.5 }}>
                {unlocatedStops.map((stop) => (
                  <RouteCandidateCard key={stop.client_uuid} stop={stop} type={type} />
                ))}
              </Stack>
            </Box>
          )}

          {geoError && (
            <Alert
              severity="error"
              variant="outlined"
              action={
                <Button color="inherit" size="small" startIcon={<MyLocationOutlinedIcon />} onClick={handleBuildRoute}>
                  Tentar novamente
                </Button>
              }
            >
              {geoError}
            </Alert>
          )}

          {tripError && (
            <Alert
              severity="error"
              variant="outlined"
              action={
                <Button color="inherit" size="small" startIcon={<RefreshIcon />} onClick={handleBuildRoute}>
                  Tentar novamente
                </Button>
              }
            >
              {tripError}
            </Alert>
          )}

          {actionError && (
            <Alert severity="error" variant="outlined" onClose={() => setActionError(null)}>
              {actionError}
            </Alert>
          )}

          {tripStatus === 'ready' && trip && origin && (
            <Box>
              <Typography
                sx={{ fontSize: 13, fontWeight: 600, color: 'var(--mk-muted)', mb: 1.25, textTransform: 'uppercase', letterSpacing: 0.4 }}
              >
                Itinerário otimizado
              </Typography>

              <RouteMap origin={origin} stops={trip.stops} geometry={trip.geometry} />

              {trip.stops.length > 0 && (
                <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ mt: 1.5 }}>
                  <Button
                    variant="outlined"
                    startIcon={<MapOutlinedIcon />}
                    component="a"
                    href={googleMapsRouteUrl(origin, trip.stops)}
                    target="_blank"
                    rel="noreferrer"
                    sx={{ minHeight: UI_SIZE.control, borderRadius: UI_RADIUS.md }}
                  >
                    Abrir rota completa no Google Maps
                  </Button>

                  <Tooltip title="O Waze não suporta múltiplas paradas — abre a navegação até a próxima parada da rota.">
                    <Button
                      variant="outlined"
                      startIcon={<NavigationOutlinedIcon />}
                      component="a"
                      href={wazeNavigationUrl({ lat: trip.stops[0].location[1], lng: trip.stops[0].location[0] })}
                      target="_blank"
                      rel="noreferrer"
                      sx={{ minHeight: UI_SIZE.control, borderRadius: UI_RADIUS.md }}
                    >
                      Abrir próxima parada no Waze
                    </Button>
                  </Tooltip>
                </Stack>
              )}

              <Stack spacing={1.25} sx={{ mt: 2, maxHeight: { xs: 480, sm: 640 }, overflowY: 'auto', pr: 0.5 }}>
                {trip.stops.map((optimized) => {
                  const stopPoint: GeoPoint = { lat: optimized.location[1], lng: optimized.location[0] }
                  const isNearby = Boolean(
                    currentPosition && distanceInMeters(currentPosition, stopPoint) <= NEARBY_THRESHOLD_METERS,
                  )

                  return (
                    <RouteResultStopCard
                      key={optimized.stop.client_uuid}
                      optimized={optimized}
                      type={type}
                      processingKey={processingKey}
                      deliveredOverrides={deliveredOverrides}
                      paidOverrides={paidOverrides}
                      installmentPaidOverrides={installmentPaidOverrides}
                      isNearby={isNearby}
                      onDeliver={handleDeliver}
                      onUndeliver={handleUndeliver}
                      onPayOrder={handlePayOrder}
                      onUnpayOrder={handleUnpayOrder}
                      onPayInstallment={handlePayInstallment}
                      onUnpayInstallment={handleUnpayInstallment}
                      onViewOrder={setOpenOrderDetailUuid}
                    />
                  )
                })}
              </Stack>

              <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)', mt: 1.5 }}>
                Total roteirizado: {formatCurrency(trip.stops.reduce((sum, optimized) => sum + stopValue(optimized.stop, type), 0))}
              </Typography>
            </Box>
          )}
        </Stack>
      )}

      <OrderDetailDialog
        orderUuid={openOrderDetailUuid}
        open={openOrderDetailUuid !== null}
        onClose={() => setOpenOrderDetailUuid(null)}
        onChanged={loadCandidates}
      />
    </Box>
  )
}
