import {
  Box,
  FormControl,
  InputLabel,
  MenuItem,
  Select,
  Stack,
  TextField,
} from '@mui/material'
import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import { LocalAutocomplete } from '../../components/crud/LocalAutocomplete'
import { useAuth } from '../../hooks/useAuth'
import * as productService from '../../services/productService'
import * as stockLocationService from '../../services/stockLocationService'
import * as stockService from '../../services/stockService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { formatQuantity } from '../../utils/format'
import type { Product } from '../../types/product'
import type { StockLocation } from '../../types/stockLocation'
import type { StockMovement, StockMovementActionType } from '../../types/stock'

interface StockMovementFormState {
  actionType: StockMovementActionType
  product_uuid: string
  location_uuid: string
  destination_location_uuid: string
  quantity: string
  direction: 'increase' | 'decrease'
  reason: string
  notes: string
  movement_uuid: string
}

const DEFAULT_STATE: StockMovementFormState = {
  actionType: 'entry',
  product_uuid: '',
  location_uuid: '',
  destination_location_uuid: '',
  quantity: '',
  direction: 'increase',
  reason: '',
  notes: '',
  movement_uuid: '',
}

const ACTION_OPTIONS: { value: StockMovementActionType; label: string }[] = [
  { value: 'entry', label: 'Entrada' },
  { value: 'exit', label: 'Saída' },
  { value: 'adjustment', label: 'Ajuste' },
  { value: 'transfer', label: 'Transferência' },
  { value: 'return', label: 'Devolução' },
  { value: 'loss', label: 'Perda' },
  { value: 'block', label: 'Bloqueio' },
  { value: 'unblock', label: 'Desbloqueio' },
  { value: 'reserve', label: 'Reserva' },
  { value: 'reserve_cancel', label: 'Cancelamento de reserva' },
]

export function StockMovementFormPage() {
  const navigate = useNavigate()
  const { activeTenantUuid } = useAuth()

  const [form, setForm] = useState<StockMovementFormState>(DEFAULT_STATE)
  const [products, setProducts] = useState<Product[]>([])
  const [locations, setLocations] = useState<StockLocation[]>([])
  const [reserveMovements, setReserveMovements] = useState<StockMovement[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const isReserveCancel = form.actionType === 'reserve_cancel'
  const isTransfer = form.actionType === 'transfer'
  const isAdjustment = form.actionType === 'adjustment'

  useEffect(() => {
    if (!activeTenantUuid) return
    setIsLoading(true)
    setLoadError(null)

    Promise.all([
      productService.listProducts({ per_page: 100 }),
      stockLocationService.listStockLocations({ per_page: 100 }),
      stockService.listStockMovements({ per_page: 100, type: 'reserve' }),
    ])
      .then(([productResult, locationResult, movementResult]) => {
        setProducts(productResult.items)
        setLocations(locationResult.items)
        setReserveMovements(movementResult.items)
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados da movimentação agora.')))
      .finally(() => setIsLoading(false))
  }, [activeTenantUuid])

  const reserveOptions = useMemo(
    () =>
      reserveMovements.map((movement) => ({
        value: movement.uuid,
        label: `${movement.product?.name ?? 'Produto'} • ${movement.location?.name ?? 'Local'} • ${formatQuantity(movement.quantity)}`,
      })),
    [reserveMovements],
  )

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    try {
      if (isReserveCancel) {
        await stockService.createReserveCancel({
          movement_uuid: form.movement_uuid,
          notes: form.notes.trim() || undefined,
        })
      } else if (isTransfer) {
        await stockService.createStockTransfer({
          product_uuid: form.product_uuid,
          location_uuid: form.location_uuid,
          destination_location_uuid: form.destination_location_uuid,
          quantity: Number(form.quantity),
          reason: form.reason.trim(),
          notes: form.notes.trim() || undefined,
        })
      } else if (isAdjustment) {
        await stockService.createStockAdjustment({
          product_uuid: form.product_uuid,
          location_uuid: form.location_uuid,
          quantity: Number(form.quantity),
          direction: form.direction,
          reason: form.reason.trim(),
          notes: form.notes.trim() || undefined,
        })
      } else {
        await stockService.createStockMovement(
          form.actionType as Exclude<StockMovementActionType, 'adjustment' | 'transfer' | 'reserve_cancel'>,
          {
          product_uuid: form.product_uuid,
          location_uuid: form.location_uuid,
          quantity: Number(form.quantity),
          reason: form.reason.trim(),
          notes: form.notes.trim() || undefined,
          },
        )
      }

      navigate('/estoque/movimentos')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível registrar a movimentação agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backLabel="Movimentações"
      backTo="/estoque/movimentos"
      title="Nova movimentação de estoque"
      subtitle="Escolha o tipo de operação e informe os dados necessários."
      breadcrumbs={[
        { label: 'Estoque', to: '/estoque/movimentos' },
        { label: 'Movimentações', to: '/estoque/movimentos' },
        { label: 'Nova' },
      ]}
      loadError={loadError}
      isLoadingRecord={isLoading}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={handleSubmit}
    >
      <Stack spacing={2.2}>
        <FormControl fullWidth>
          <InputLabel id="stock-action-type">Tipo de operação</InputLabel>
          <Select
            labelId="stock-action-type"
            label="Tipo de operação"
            value={form.actionType}
            onChange={(event) =>
              setForm((current) => ({
                ...current,
                actionType: event.target.value as StockMovementActionType,
                movement_uuid: '',
                destination_location_uuid: '',
              }))
            }
          >
            {ACTION_OPTIONS.map((option) => (
              <MenuItem key={option.value} value={option.value}>
                {option.label}
              </MenuItem>
            ))}
          </Select>
        </FormControl>

        {isReserveCancel ? (
          <LocalAutocomplete
            label="Reserva original"
            fullWidth
            required
            options={reserveOptions}
            value={reserveOptions.find((option) => option.value === form.movement_uuid) ?? null}
            onChange={(option) => setForm((current) => ({ ...current, movement_uuid: option?.value ?? '' }))}
            getOptionLabel={(option) => option.label}
            getOptionKey={(option) => option.value}
            error={Boolean(fieldErrors.movement_uuid?.[0])}
            helperText={fieldErrors.movement_uuid?.[0]}
          />
        ) : (
          <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', md: 'repeat(2, minmax(0, 1fr))' }, gap: 2 }}>
            <LocalAutocomplete
              label="Produto"
              fullWidth
              required
              options={products}
              value={products.find((product) => product.uuid === form.product_uuid) ?? null}
              onChange={(product) => setForm((current) => ({ ...current, product_uuid: product?.uuid ?? '' }))}
              getOptionLabel={(product) => product.name}
              getOptionKey={(product) => product.uuid}
              error={Boolean(fieldErrors.product_uuid?.[0])}
              helperText={fieldErrors.product_uuid?.[0]}
            />

            <LocalAutocomplete
              label="Local"
              fullWidth
              required
              options={locations}
              value={locations.find((location) => location.uuid === form.location_uuid) ?? null}
              onChange={(location) => setForm((current) => ({ ...current, location_uuid: location?.uuid ?? '' }))}
              getOptionLabel={(location) => location.name}
              getOptionKey={(location) => location.uuid}
              error={Boolean(fieldErrors.location_uuid?.[0])}
              helperText={fieldErrors.location_uuid?.[0]}
            />

            {isTransfer && (
              <LocalAutocomplete
                label="Local de destino"
                fullWidth
                required
                options={locations}
                value={locations.find((location) => location.uuid === form.destination_location_uuid) ?? null}
                onChange={(location) =>
                  setForm((current) => ({ ...current, destination_location_uuid: location?.uuid ?? '' }))
                }
                getOptionLabel={(location) => location.name}
                getOptionKey={(location) => location.uuid}
                error={Boolean(fieldErrors.destination_location_uuid?.[0])}
                helperText={fieldErrors.destination_location_uuid?.[0]}
              />
            )}

            {isAdjustment && (
              <FormControl fullWidth required>
                <InputLabel id="stock-direction">Direção</InputLabel>
                <Select
                  labelId="stock-direction"
                  label="Direção"
                  value={form.direction}
                  onChange={(event) =>
                    setForm((current) => ({ ...current, direction: event.target.value as 'increase' | 'decrease' }))
                  }
                >
                  <MenuItem value="increase">Aumentar saldo</MenuItem>
                  <MenuItem value="decrease">Diminuir saldo</MenuItem>
                </Select>
              </FormControl>
            )}

            <TextField
              label="Quantidade"
              type="number"
              value={form.quantity}
              onChange={(event) => setForm((current) => ({ ...current, quantity: event.target.value }))}
              error={Boolean(fieldErrors.quantity?.[0])}
              helperText={fieldErrors.quantity?.[0]}
              required
              fullWidth
              slotProps={{ htmlInput: { min: 0.001, step: '0.001' } }}
            />

            <TextField
              label="Motivo"
              value={form.reason}
              onChange={(event) => setForm((current) => ({ ...current, reason: event.target.value }))}
              error={Boolean(fieldErrors.reason?.[0])}
              helperText={fieldErrors.reason?.[0]}
              required
              fullWidth
              slotProps={{ htmlInput: { maxLength: 255 } }}
            />
          </Box>
        )}

        <TextField
          label="Observações"
          value={form.notes}
          onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))}
          error={Boolean(fieldErrors.notes?.[0])}
          helperText={fieldErrors.notes?.[0]}
          minRows={3}
          multiline
          fullWidth
        />
      </Stack>
    </CrudFormShell>
  )
}
