import AddIcon from '@mui/icons-material/Add'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import {
  Alert,
  Box,
  Button,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
  MenuItem,
  Paper,
  Skeleton,
  Stack,
  Switch,
  TextField,
  Tooltip,
  Typography,
} from '@mui/material'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { useParams } from 'react-router-dom'
import * as accountingTaxRuleService from '../../services/accountingTaxRuleService'
import * as accountingService from '../../services/accountingService'
import { FORM_GRID_2_SX, UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import type { AccountingOfficeTenantLink } from '../../types/accounting'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { TAX_TYPE_LABELS, TAX_TYPE_OPTIONS, type TaxRule, type TaxRulePayload, type TaxRuleScope, type TaxType } from '../../types/taxRule'

function parseCsvList(value: string, transform?: (item: string) => string): string[] | undefined {
  const items = value.split(',').map((item) => item.trim()).filter(Boolean).map((item) => (transform ? transform(item) : item))
  return items.length > 0 ? items : undefined
}

function renderScope(rule: TaxRule): string[] {
  if (!rule.scope) return ['Regra geral']
  const entries: string[] = []
  if (rule.scope.cfop?.length) entries.push(`CFOP: ${rule.scope.cfop.join(', ')}`)
  if (rule.scope.ncm?.length) entries.push(`NCM: ${rule.scope.ncm.join(', ')}`)
  if (rule.scope.uf_origin?.length) entries.push(`UF origem: ${rule.scope.uf_origin.join(', ')}`)
  if (rule.scope.uf_dest?.length) entries.push(`UF destino: ${rule.scope.uf_dest.join(', ')}`)
  return entries.length > 0 ? entries : ['Regra geral']
}

function TaxRuleDialog({
  tenantUuid,
  rule,
  onClose,
  onSaved,
}: {
  tenantUuid: string
  rule: TaxRule | null
  onClose: () => void
  onSaved: (rule: TaxRule) => void
}) {
  const isEditMode = Boolean(rule)
  const [taxType, setTaxType] = useState<TaxType>(rule?.tax_type ?? 'icms')
  const [ratePercent, setRatePercent] = useState(rule ? String(rule.rate_percent) : '')
  const [validFrom, setValidFrom] = useState(rule?.valid_from ? rule.valid_from.slice(0, 10) : '')
  const [validTo, setValidTo] = useState(rule?.valid_to ? rule.valid_to.slice(0, 10) : '')
  const [cfop, setCfop] = useState(rule?.scope?.cfop?.join(', ') ?? '')
  const [ncm, setNcm] = useState(rule?.scope?.ncm?.join(', ') ?? '')
  const [ufOrigin, setUfOrigin] = useState(rule?.scope?.uf_origin?.join(', ') ?? '')
  const [ufDest, setUfDest] = useState(rule?.scope?.uf_dest?.join(', ') ?? '')
  const [isActive, setIsActive] = useState(rule?.is_active ?? true)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

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

  async function handleSave() {
    setFieldErrors({})
    setFormError(null)
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
      const saved = rule
        ? await accountingTaxRuleService.updateAccountingTaxRule(tenantUuid, rule.uuid, payload)
        : await accountingTaxRuleService.createAccountingTaxRule(tenantUuid, payload)
      onSaved(saved)
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar a regra tributária agora.'))
      if (err instanceof ApiRequestError) setFieldErrors(err.errors)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Dialog open onClose={onClose} fullWidth maxWidth="md">
      <DialogTitle sx={{ fontSize: 17, fontWeight: 700 }}>{isEditMode ? 'Editar regra tributária' : 'Nova regra tributária'}</DialogTitle>
      <DialogContent>
        {formError && <Alert severity="error" variant="outlined" sx={{ mb: 2 }}>{formError}</Alert>}
        <Box sx={{ ...FORM_GRID_2_SX, mb: 2 }}>
          <TextField select label="Tributo" value={taxType} onChange={(event) => setTaxType(event.target.value as TaxType)} error={Boolean(fieldErrors.tax_type)} helperText={fieldErrors.tax_type?.[0]}>
            {TAX_TYPE_OPTIONS.map((option) => <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>)}
          </TextField>
          <TextField label="Alíquota (%)" type="number" value={ratePercent} onChange={(event) => setRatePercent(event.target.value)} error={Boolean(fieldErrors.rate_percent)} helperText={fieldErrors.rate_percent?.[0]} slotProps={{ htmlInput: { min: 0, max: 100, step: '0.0001' } }} />
          <TextField label="Válida a partir de" type="date" value={validFrom} onChange={(event) => setValidFrom(event.target.value)} error={Boolean(fieldErrors.valid_from)} helperText={fieldErrors.valid_from?.[0] ?? 'Deixe em branco para valer imediatamente.'} slotProps={{ inputLabel: { shrink: true } }} />
          <TextField label="Válida até" type="date" value={validTo} onChange={(event) => setValidTo(event.target.value)} error={Boolean(fieldErrors.valid_to)} helperText={fieldErrors.valid_to?.[0] ?? 'Deixe em branco para não definir data final.'} slotProps={{ inputLabel: { shrink: true } }} />
        </Box>
        <Typography sx={{ fontWeight: 700, fontSize: 15, mb: 1 }}>Escopo da regra</Typography>
        <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)', mb: 2 }}>Informe listas separadas por vírgula. Se deixar tudo em branco, a regra vale como regra geral.</Typography>
        <Box sx={{ ...FORM_GRID_2_SX, mb: 2 }}>
          <TextField label="CFOPs" value={cfop} onChange={(event) => setCfop(event.target.value)} error={Boolean(fieldErrors['scope.cfop'])} helperText={fieldErrors['scope.cfop']?.[0] ?? 'Ex.: 5102, 6102'} />
          <TextField label="NCMs" value={ncm} onChange={(event) => setNcm(event.target.value)} error={Boolean(fieldErrors['scope.ncm'])} helperText={fieldErrors['scope.ncm']?.[0] ?? 'Ex.: 22030000, 21069090'} />
          <TextField label="UFs de origem" value={ufOrigin} onChange={(event) => setUfOrigin(event.target.value.toUpperCase())} error={Boolean(fieldErrors['scope.uf_origin'])} helperText={fieldErrors['scope.uf_origin']?.[0] ?? 'Ex.: SP, RJ'} />
          <TextField label="UFs de destino" value={ufDest} onChange={(event) => setUfDest(event.target.value.toUpperCase())} error={Boolean(fieldErrors['scope.uf_dest'])} helperText={fieldErrors['scope.uf_dest']?.[0] ?? 'Ex.: MG, ES'} />
        </Box>
        <Stack direction="row"><Switch checked={isActive} onChange={(event) => setIsActive(event.target.checked)} /> <Typography sx={{ alignSelf: 'center' }}>Regra ativa</Typography></Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2 }}>
        <Button onClick={onClose} disabled={isSubmitting}>Cancelar</Button>
        <Button variant="contained" onClick={() => void handleSave()} disabled={isSubmitting}>{isSubmitting ? 'Salvando…' : 'Salvar'}</Button>
      </DialogActions>
    </Dialog>
  )
}

export function AccountingCompanyTaxRulesPage() {
  const { tenantUuid } = useParams<{ tenantUuid: string }>()
  const [link, setLink] = useState<AccountingOfficeTenantLink | null>(null)
  const [rules, setRules] = useState<TaxRule[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [editingRule, setEditingRule] = useState<TaxRule | null>(null)
  const [creating, setCreating] = useState(false)
  const [deletingUuid, setDeletingUuid] = useState<string | null>(null)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  const canWrite = Boolean(link?.scopes.includes('fiscal.write'))

  const load = useCallback(async () => {
    if (!tenantUuid) return
    setIsLoading(true)
    setError(null)
    try {
      const [links, items] = await Promise.all([
        accountingService.listMyLinks(),
        accountingTaxRuleService.listAccountingTaxRules(tenantUuid),
      ])
      setLink(links.find((item) => item.tenant?.uuid === tenantUuid && item.status === 'approved') ?? null)
      setRules(items)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível carregar as regras tributárias agora.'))
    } finally {
      setIsLoading(false)
    }
  }, [tenantUuid])

  useEffect(() => {
    void load()
  }, [load])

  const orderedRules = useMemo(() => [...rules].sort((a, b) => {
    if (a.is_active !== b.is_active) return a.is_active ? -1 : 1
    return (b.created_at ?? '').localeCompare(a.created_at ?? '')
  }), [rules])

  async function handleDelete(rule: TaxRule) {
    if (!tenantUuid) return
    setDeletingUuid(rule.uuid)
    setDeleteError(null)
    try {
      await accountingTaxRuleService.deleteAccountingTaxRule(tenantUuid, rule.uuid)
      setRules((current) => current.filter((item) => item.uuid !== rule.uuid))
    } catch (err) {
      setDeleteError(getApiErrorMessage(err, 'Não foi possível excluir esta regra tributária agora.'))
    } finally {
      setDeletingUuid(null)
    }
  }

  return (
    <Box>
      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} sx={{ justifyContent: 'space-between', mb: 2 }}>
        <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)' }}>
          O contador pode consultar e, quando liberado, manter as regras tributárias da empresa.
        </Typography>
        {canWrite && (
          <Button variant="contained" startIcon={<AddIcon />} onClick={() => setCreating(true)}>
            Nova regra
          </Button>
        )}
      </Stack>

      {deleteError && <Alert severity="error" variant="outlined" sx={{ mb: 2 }}>{deleteError}</Alert>}
      {isLoading && <Stack spacing={1.5}><Skeleton variant="rounded" height={92} /><Skeleton variant="rounded" height={92} /></Stack>}
      {!isLoading && error && <Alert severity="error" variant="outlined" action={<Button size="small" onClick={() => void load()}>Tentar de novo</Button>}>{error}</Alert>}
      {!isLoading && !error && orderedRules.length === 0 && (
        <Paper variant="outlined" sx={{ ...ELEVATED_SURFACE_SX, p: 4, textAlign: 'center' }}>
          <ReceiptLongOutlinedIcon sx={{ fontSize: 36, color: 'var(--mk-muted)', mb: 1 }} />
          <Typography sx={{ fontSize: 15, fontWeight: 600, mb: 0.5 }}>Nenhuma regra tributária cadastrada ainda</Typography>
          <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>Cadastre regras por tributo e vigência para organizar a base fiscal da empresa.</Typography>
        </Paper>
      )}
      {!isLoading && !error && orderedRules.length > 0 && (
        <Stack spacing={1.5}>
          {orderedRules.map((rule) => (
            <Paper key={rule.uuid} variant="outlined" sx={{ ...ELEVATED_SURFACE_SX, p: 2 }}>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} sx={{ justifyContent: 'space-between' }}>
                <Box sx={{ minWidth: 0, flex: 1 }}>
                  <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap', mb: 0.75 }}>
                    <Typography sx={{ fontSize: 15, fontWeight: 700 }}>{TAX_TYPE_LABELS[rule.tax_type]}</Typography>
                    <Chip label={rule.is_active ? 'Ativa' : 'Inativa'} size="small" color={rule.is_active ? 'success' : 'default'} variant="outlined" />
                    <Chip label={`${rule.rate_percent}%`} size="small" variant="outlined" />
                  </Stack>
                  <Stack spacing={0.5}>
                    {renderScope(rule).map((line) => <Typography key={line} sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>{line}</Typography>)}
                    <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>
                      Vigência: {rule.valid_from ? new Date(rule.valid_from).toLocaleDateString('pt-BR') : 'imediata'} até {rule.valid_to ? new Date(rule.valid_to).toLocaleDateString('pt-BR') : 'sem data final'}
                    </Typography>
                  </Stack>
                </Box>
                {canWrite && (
                  <Stack direction="row" spacing={0.5} sx={{ alignSelf: { xs: 'flex-end', md: 'flex-start' } }}>
                    <Tooltip title="Editar regra" arrow><IconButton onClick={() => setEditingRule(rule)} sx={{ minWidth: UI_SIZE.iconButton, minHeight: UI_SIZE.iconButton }}><EditOutlinedIcon fontSize="small" /></IconButton></Tooltip>
                    <Tooltip title="Excluir regra" arrow><IconButton onClick={() => void handleDelete(rule)} disabled={deletingUuid === rule.uuid} sx={{ minWidth: UI_SIZE.iconButton, minHeight: UI_SIZE.iconButton }}><DeleteOutlineIcon fontSize="small" /></IconButton></Tooltip>
                  </Stack>
                )}
              </Stack>
            </Paper>
          ))}
        </Stack>
      )}

      {tenantUuid && creating && (
        <TaxRuleDialog
          tenantUuid={tenantUuid}
          rule={null}
          onClose={() => setCreating(false)}
          onSaved={(rule) => {
            setRules((current) => [rule, ...current])
            setCreating(false)
          }}
        />
      )}
      {tenantUuid && editingRule && (
        <TaxRuleDialog
          tenantUuid={tenantUuid}
          rule={editingRule}
          onClose={() => setEditingRule(null)}
          onSaved={(rule) => {
            setRules((current) => current.map((item) => (item.uuid === rule.uuid ? rule : item)))
            setEditingRule(null)
          }}
        />
      )}
    </Box>
  )
}
