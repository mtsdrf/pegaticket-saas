import { Box, FormControlLabel, MenuItem, Stack, Switch, TextField, Typography } from '@mui/material'
import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import * as taxRuleService from '../../services/taxRuleService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { TAX_TYPE_OPTIONS, type TaxRulePayload, type TaxRuleScope, type TaxType } from '../../types/taxRule'

function parseCsvList(value: string, transform?: (item: string) => string): string[] | undefined {
  const items = value
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)
    .map((item) => (transform ? transform(item) : item))

  return items.length > 0 ? items : undefined
}

export function TaxRuleFormPage() {
  const { uuid } = useParams<{ uuid: string }>()
  const isEditMode = Boolean(uuid)
  const navigate = useNavigate()

  const [taxType, setTaxType] = useState<TaxType>('icms')
  const [ratePercent, setRatePercent] = useState('')
  const [validFrom, setValidFrom] = useState('')
  const [validTo, setValidTo] = useState('')
  const [cfop, setCfop] = useState('')
  const [ncm, setNcm] = useState('')
  const [ufOrigin, setUfOrigin] = useState('')
  const [ufDest, setUfDest] = useState('')
  const [isActive, setIsActive] = useState(true)
  const [isLoadingRecord, setIsLoadingRecord] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    if (!uuid) {
      setIsLoadingRecord(false)
      return
    }

    setIsLoadingRecord(true)
    setLoadError(null)

    taxRuleService
      .getTaxRule(uuid)
      .then((rule) => {
        setTaxType(rule.tax_type)
        setRatePercent(String(rule.rate_percent))
        setValidFrom(rule.valid_from ? rule.valid_from.slice(0, 10) : '')
        setValidTo(rule.valid_to ? rule.valid_to.slice(0, 10) : '')
        setCfop(rule.scope?.cfop?.join(', ') ?? '')
        setNcm(rule.scope?.ncm?.join(', ') ?? '')
        setUfOrigin(rule.scope?.uf_origin?.join(', ') ?? '')
        setUfDest(rule.scope?.uf_dest?.join(', ') ?? '')
        setIsActive(rule.is_active)
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar a regra tributária agora.')))
      .finally(() => setIsLoadingRecord(false))
  }, [uuid])

  function buildScope(): TaxRuleScope | null {
    const scope: TaxRuleScope = {}
    const cfopList = parseCsvList(cfop)
    const ncmList = parseCsvList(ncm)
    const ufOriginList = parseCsvList(ufOrigin, (item) => item.toUpperCase())
    const ufDestList = parseCsvList(ufDest, (item) => item.toUpperCase())

    if (cfopList) scope.cfop = cfopList
    if (ncmList) scope.ncm = ncmList
    if (ufOriginList) scope.uf_origin = ufOriginList
    if (ufDestList) scope.uf_dest = ufDestList

    return Object.keys(scope).length > 0 ? scope : null
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    const payload: TaxRulePayload = {
      tax_type: taxType,
      rate_percent: Number(ratePercent),
      valid_from: validFrom || null,
      valid_to: validTo || null,
      scope: buildScope(),
      is_active: isActive,
    }

    try {
      if (uuid) {
        await taxRuleService.updateTaxRule(uuid, payload)
      } else {
        await taxRuleService.createTaxRule(payload)
      }
      navigate('/configuracoes/regras-tributarias')
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível salvar a regra tributária agora.'))
      if (error instanceof ApiRequestError) setFieldErrors(error.errors)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backLabel="Regras tributárias"
      backTo="/configuracoes/regras-tributarias"
      title={isEditMode ? 'Editar regra tributária' : 'Nova regra tributária'}
      subtitle={isEditMode ? 'Atualize a alíquota, o escopo e a vigência da regra.' : 'Cadastre uma regra tributária por tipo de tributo e vigência.'}
      breadcrumbs={[
        { label: 'Configurações', to: '/configuracoes' },
        { label: 'Regras tributárias', to: '/configuracoes/regras-tributarias' },
        { label: isEditMode ? 'Editar' : 'Nova' },
      ]}
      loadError={loadError}
      isLoadingRecord={isLoadingRecord}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={(event) => void handleSubmit(event)}
    >
      <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', md: 'repeat(2, minmax(0, 1fr))' }, gap: 2, mb: 2 }}>
        <TextField
          select
          label="Tributo"
          value={taxType}
          onChange={(event) => setTaxType(event.target.value as TaxType)}
          error={Boolean(fieldErrors.tax_type)}
          helperText={fieldErrors.tax_type?.[0]}
          required
          fullWidth
        >
          {TAX_TYPE_OPTIONS.map((option) => (
            <MenuItem key={option.value} value={option.value}>
              {option.label}
            </MenuItem>
          ))}
        </TextField>

        <TextField
          label="Alíquota (%)"
          type="number"
          value={ratePercent}
          onChange={(event) => setRatePercent(event.target.value)}
          error={Boolean(fieldErrors.rate_percent)}
          helperText={fieldErrors.rate_percent?.[0]}
          required
          fullWidth
          slotProps={{ htmlInput: { min: 0, max: 100, step: '0.0001' } }}
        />

        <TextField
          label="Válida a partir de"
          type="date"
          value={validFrom}
          onChange={(event) => setValidFrom(event.target.value)}
          error={Boolean(fieldErrors.valid_from)}
          helperText={fieldErrors.valid_from?.[0] ?? 'Deixe em branco para valer imediatamente.'}
          fullWidth
          slotProps={{ inputLabel: { shrink: true } }}
        />

        <TextField
          label="Válida até"
          type="date"
          value={validTo}
          onChange={(event) => setValidTo(event.target.value)}
          error={Boolean(fieldErrors.valid_to)}
          helperText={fieldErrors.valid_to?.[0] ?? 'Deixe em branco para não definir data final.'}
          fullWidth
          slotProps={{ inputLabel: { shrink: true } }}
        />
      </Box>

      <Typography sx={{ fontWeight: 700, fontSize: 15, mb: 1 }}>Escopo da regra</Typography>
      <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)', mb: 2 }}>
        Informe listas separadas por vírgula. Se deixar tudo em branco, a regra vale como regra geral para o tributo informado.
      </Typography>

      <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', md: 'repeat(2, minmax(0, 1fr))' }, gap: 2, mb: 2 }}>
        <TextField
          label="CFOPs"
          value={cfop}
          onChange={(event) => setCfop(event.target.value)}
          error={Boolean(fieldErrors['scope.cfop'])}
          helperText={fieldErrors['scope.cfop']?.[0] ?? 'Ex.: 5102, 6102'}
          fullWidth
        />
        <TextField
          label="NCMs"
          value={ncm}
          onChange={(event) => setNcm(event.target.value)}
          error={Boolean(fieldErrors['scope.ncm'])}
          helperText={fieldErrors['scope.ncm']?.[0] ?? 'Ex.: 22030000, 21069090'}
          fullWidth
        />
        <TextField
          label="UFs de origem"
          value={ufOrigin}
          onChange={(event) => setUfOrigin(event.target.value.toUpperCase())}
          error={Boolean(fieldErrors['scope.uf_origin'])}
          helperText={fieldErrors['scope.uf_origin']?.[0] ?? 'Ex.: SP, RJ'}
          fullWidth
        />
        <TextField
          label="UFs de destino"
          value={ufDest}
          onChange={(event) => setUfDest(event.target.value.toUpperCase())}
          error={Boolean(fieldErrors['scope.uf_dest'])}
          helperText={fieldErrors['scope.uf_dest']?.[0] ?? 'Ex.: MG, ES'}
          fullWidth
        />
      </Box>

      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
        <FormControlLabel
          control={<Switch checked={isActive} onChange={(event) => setIsActive(event.target.checked)} />}
          label="Regra ativa"
        />
      </Stack>
    </CrudFormShell>
  )
}
