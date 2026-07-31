import { Alert, Button, FormControlLabel, InputAdornment, MenuItem, Skeleton, Stack, Switch, TextField, Typography } from '@mui/material'
import { useEffect, useState } from 'react'
import { ACCESS } from '../../../access/requirements'
import { useAuth } from '../../../hooks/useAuth'
import * as reactivationRuleService from '../../../services/reactivationRuleService'
import { ApiRequestError, getApiErrorMessage } from '../../../types/api'
import type { ReactivationCouponType, ReactivationRule } from '../../../types/reactivationRule'

const REACTIVATION_COUPON_TYPES: Array<{ value: ReactivationCouponType; label: string }> = [
  { value: 'percentage', label: 'Percentual (%)' },
  { value: 'fixed', label: 'Valor fixo (R$)' },
]

/**
 * Bloco "Retenção e Marketing" — régua de reativação de cliente (roadmap
 * A5, item 18), extraído de `TenantSettingsPage` (2026-07-24). Singleton
 * por tenant (`GET/PUT /reactivation-rule`), permissão própria
 * `reactivation` (faixa Ouro+Diamante). Gerado por `reactivation:process`
 * (cron diário): clientes sem pedido há `days_without_order` dias recebem
 * um cupom de reativação, respeitando cooldown no backend.
 */
export function RetentionBlock() {
  const [rule, setRule] = useState<ReactivationRule | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [daysWithoutOrder, setDaysWithoutOrder] = useState('')
  const [couponType, setCouponType] = useState<ReactivationCouponType>('percentage')
  const [couponValue, setCouponValue] = useState('')
  const [couponValidityDays, setCouponValidityDays] = useState('')
  const [isActive, setIsActive] = useState(false)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)

  const { hasPermission } = useAuth()
  const canUpdate = hasPermission(ACCESS.reactivationUpdate)

  function applyRule(data: ReactivationRule) {
    setRule(data)
    setDaysWithoutOrder(String(data.days_without_order))
    setCouponType(data.coupon_type)
    setCouponValue(String(data.coupon_value))
    setCouponValidityDays(String(data.coupon_validity_days))
    setIsActive(data.is_active)
  }

  function load() {
    setIsLoading(true)
    setLoadError(null)
    reactivationRuleService
      .getReactivationRule()
      .then(applyRule)
      .catch((error: unknown) => {
        setLoadError(getApiErrorMessage(error, 'Não foi possível carregar a régua de reativação agora.'))
      })
      .finally(() => setIsLoading(false))
  }

  useEffect(() => {
    load()
  }, [])

  const parsedDays = Number(daysWithoutOrder)
  const parsedValue = Number(couponValue.replace(',', '.'))
  const parsedValidity = Number(couponValidityDays)
  const isFormValid =
    daysWithoutOrder.trim() !== '' &&
    Number.isInteger(parsedDays) &&
    parsedDays >= 1 &&
    couponValue.trim() !== '' &&
    !Number.isNaN(parsedValue) &&
    parsedValue >= 0.01 &&
    (couponType !== 'percentage' || parsedValue <= 100) &&
    couponValidityDays.trim() !== '' &&
    Number.isInteger(parsedValidity) &&
    parsedValidity >= 1

  async function handleSubmit() {
    setFormError(null)
    setFieldErrors({})
    setSuccessMessage(null)
    setIsSubmitting(true)

    try {
      const updated = await reactivationRuleService.updateReactivationRule({
        days_without_order: parsedDays,
        coupon_type: couponType,
        coupon_value: parsedValue,
        coupon_validity_days: parsedValidity,
        is_active: isActive,
      })
      applyRule(updated)
      setSuccessMessage('Régua de reativação salva com sucesso.')
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível salvar a régua de reativação agora.'))
      if (error instanceof ApiRequestError) {
        setFieldErrors(error.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  if (loadError && !rule) {
    return (
      <Alert
        severity="error"
        action={
          <Button color="inherit" size="small" onClick={load}>
            Tentar novamente
          </Button>
        }
      >
        {loadError}
      </Alert>
    )
  }

  if (isLoading && !rule) {
    return <Skeleton variant="rounded" height={180} />
  }

  if (!rule) return null

  return (
    <>
      <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)', mb: 2 }}>
        Cliente sem pedido há um número de dias recebe automaticamente um cupom de reativação por WhatsApp.
      </Typography>

      {formError && (
        <Alert severity="error" sx={{ mb: 2.5 }}>
          {formError}
        </Alert>
      )}
      {successMessage && (
        <Alert severity="success" sx={{ mb: 2.5 }} onClose={() => setSuccessMessage(null)}>
          {successMessage}
        </Alert>
      )}

      <FormControlLabel
        control={<Switch checked={isActive} onChange={(event) => setIsActive(event.target.checked)} disabled={!canUpdate} />}
        label="Ativar régua de reativação"
      />

      <Stack spacing={2} sx={{ mt: 2 }}>
        <TextField
          label="Dias sem pedido"
          type="number"
          value={daysWithoutOrder}
          onChange={(event) => setDaysWithoutOrder(event.target.value)}
          error={Boolean(fieldErrors.days_without_order)}
          helperText={fieldErrors.days_without_order?.[0] ?? 'Cliente elegível quando o último pedido é mais antigo que isso.'}
          disabled={!canUpdate}
          required
          fullWidth
          sx={{ maxWidth: { sm: 320 } }}
          slotProps={{
            input: { endAdornment: <InputAdornment position="end">dias</InputAdornment> },
            htmlInput: { min: 1, step: 1 },
          }}
        />

        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
          <TextField
            select
            label="Tipo de cupom"
            value={couponType}
            onChange={(event) => setCouponType(event.target.value as ReactivationCouponType)}
            error={Boolean(fieldErrors.coupon_type)}
            helperText={fieldErrors.coupon_type?.[0]}
            disabled={!canUpdate}
            required
            fullWidth
          >
            {REACTIVATION_COUPON_TYPES.map((option) => (
              <MenuItem key={option.value} value={option.value}>
                {option.label}
              </MenuItem>
            ))}
          </TextField>
          <TextField
            label="Valor do cupom"
            type="number"
            value={couponValue}
            onChange={(event) => setCouponValue(event.target.value)}
            error={Boolean(fieldErrors.coupon_value)}
            helperText={fieldErrors.coupon_value?.[0]}
            disabled={!canUpdate}
            required
            fullWidth
            slotProps={{
              input: {
                startAdornment: couponType === 'fixed' ? <InputAdornment position="start">R$</InputAdornment> : undefined,
                endAdornment: couponType === 'percentage' ? <InputAdornment position="end">%</InputAdornment> : undefined,
              },
              htmlInput: couponType === 'percentage' ? { min: 0.01, max: 100, step: '0.01' } : { min: 0.01, step: '0.01' },
            }}
          />
        </Stack>

        <TextField
          label="Validade do cupom"
          type="number"
          value={couponValidityDays}
          onChange={(event) => setCouponValidityDays(event.target.value)}
          error={Boolean(fieldErrors.coupon_validity_days)}
          helperText={fieldErrors.coupon_validity_days?.[0] ?? 'Dias até o cupom gerado expirar.'}
          disabled={!canUpdate}
          required
          fullWidth
          sx={{ maxWidth: { sm: 320 } }}
          slotProps={{
            input: { endAdornment: <InputAdornment position="end">dias</InputAdornment> },
            htmlInput: { min: 1, step: 1 },
          }}
        />
      </Stack>

      {canUpdate && (
        <Stack direction="row" sx={{ mt: 3, justifyContent: 'flex-end' }}>
          <Button variant="contained" disabled={isSubmitting || !isFormValid} onClick={() => void handleSubmit()} sx={{ minWidth: 140 }}>
            {isSubmitting ? 'Salvando…' : 'Salvar'}
          </Button>
        </Stack>
      )}
    </>
  )
}
