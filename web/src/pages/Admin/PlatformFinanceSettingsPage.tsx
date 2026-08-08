import ExpandMoreIcon from '@mui/icons-material/ExpandMore'
import {
  Accordion,
  AccordionDetails,
  AccordionSummary,
  Alert,
  Box,
  Checkbox,
  FormControlLabel,
  InputAdornment,
  Snackbar,
  TextField,
  Typography,
} from '@mui/material'
import { useEffect, useState, type FormEvent } from 'react'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import { LocalAutocomplete } from '../../components/crud/LocalAutocomplete'
import { FormSection } from '../../components/form/FormSection'
import * as platformFinanceSettingsService from '../../services/platformFinanceSettingsService'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { FORM_GRID_2_SX, FORM_GRID_3_SX } from '../../styles/layoutStandards'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { SETTLEMENT_REFERENCE_OPTIONS, type SettlementReference } from '../../types/platformFinanceSettings'

const INSTALLMENTS = Array.from({ length: 12 }, (_, index) => index + 1)

interface FormState {
  service_fee_percentage: string
  service_fee_minimum_amount: string
  estimated_pix_processing_percentage: string
  estimated_card_processing_percentage_by_installment: Record<string, string>
  platform_fee_fixed_amount: string
  default_settlement_offset_days: string
  settlement_reference: SettlementReference
  split_custody_enabled: boolean
  extra_reserve_enabled: boolean
  extra_reserve_percentage: string
  extra_reserve_release_offset_days: string
  pagbank_primary_account_id: string
}

const EMPTY_FORM: FormState = {
  service_fee_percentage: '',
  service_fee_minimum_amount: '',
  estimated_pix_processing_percentage: '',
  estimated_card_processing_percentage_by_installment: {},
  platform_fee_fixed_amount: '',
  default_settlement_offset_days: '',
  settlement_reference: 'event_end',
  split_custody_enabled: true,
  extra_reserve_enabled: false,
  extra_reserve_percentage: '',
  extra_reserve_release_offset_days: '',
  pagbank_primary_account_id: '',
}

function toOptionalNumber(value: string): number | null {
  const trimmed = value.trim()
  return trimmed === '' ? null : Number(trimmed)
}

export function PlatformFinanceSettingsPage() {
  const [form, setForm] = useState<FormState>(EMPTY_FORM)
  const [ruleVersion, setRuleVersion] = useState<number | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [feedback, setFeedback] = useState<{ severity: 'success' | 'error'; message: string } | null>(null)

  function loadSettings() {
    setIsLoading(true)
    setLoadError(null)
    platformFinanceSettingsService
      .getPlatformFinanceSettings()
      .then((settings) => {
        setForm({
          service_fee_percentage: String(settings.service_fee_percentage),
          service_fee_minimum_amount: String(settings.service_fee_minimum_amount),
          estimated_pix_processing_percentage:
            settings.estimated_pix_processing_percentage == null
              ? ''
              : String(settings.estimated_pix_processing_percentage),
          estimated_card_processing_percentage_by_installment: Object.fromEntries(
            INSTALLMENTS.map((installment) => {
              const value = settings.estimated_card_processing_percentage_by_installment?.[String(installment)]
              return [String(installment), value == null ? '' : String(value)]
            }),
          ),
          platform_fee_fixed_amount: String(settings.platform_fee_fixed_amount),
          default_settlement_offset_days: String(settings.default_settlement_offset_days),
          settlement_reference: settings.settlement_reference,
          split_custody_enabled: settings.split_custody_enabled,
          extra_reserve_enabled: settings.extra_reserve_enabled,
          extra_reserve_percentage: String(settings.extra_reserve_percentage),
          extra_reserve_release_offset_days: String(settings.extra_reserve_release_offset_days),
          pagbank_primary_account_id: settings.pagbank_primary_account_id ?? '',
        })
        setRuleVersion(settings.service_fee_rule_version)
      })
      .catch((error: unknown) =>
        setLoadError(getApiErrorMessage(error, 'Não foi possível carregar as configurações financeiras agora.')),
      )
      .finally(() => setIsLoading(false))
  }

  useEffect(() => {
    loadSettings()
  }, [])

  function updateField<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((current) => ({ ...current, [key]: value }))
  }

  function updateInstallment(installment: number, value: string) {
    setForm((current) => ({
      ...current,
      estimated_card_processing_percentage_by_installment: {
        ...current.estimated_card_processing_percentage_by_installment,
        [String(installment)]: value,
      },
    }))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    const cardPercentages = Object.fromEntries(
      INSTALLMENTS.map((installment) => [
        String(installment),
        toOptionalNumber(form.estimated_card_processing_percentage_by_installment[String(installment)] ?? ''),
      ]).filter(([, value]) => value !== null),
    ) as Record<string, number>

    const payload = {
      service_fee_percentage: Number(form.service_fee_percentage),
      service_fee_minimum_amount: Number(form.service_fee_minimum_amount),
      estimated_pix_processing_percentage: toOptionalNumber(form.estimated_pix_processing_percentage),
      estimated_card_processing_percentage_by_installment:
        Object.keys(cardPercentages).length > 0 ? cardPercentages : null,
      platform_fee_fixed_amount: Number(form.platform_fee_fixed_amount),
      default_settlement_offset_days: Number(form.default_settlement_offset_days),
      settlement_reference: form.settlement_reference,
      split_custody_enabled: form.split_custody_enabled,
      extra_reserve_enabled: form.extra_reserve_enabled,
      extra_reserve_percentage: Number(form.extra_reserve_percentage),
      extra_reserve_release_offset_days: Number(form.extra_reserve_release_offset_days),
      pagbank_primary_account_id: form.pagbank_primary_account_id.trim() || null,
    }

    try {
      const updated = await platformFinanceSettingsService.updatePlatformFinanceSettings(payload)
      setRuleVersion(updated.service_fee_rule_version)
      setFeedback({ severity: 'success', message: 'Configurações financeiras atualizadas com sucesso.' })
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível salvar as configurações financeiras agora.'))
      if (error instanceof ApiRequestError) {
        setFieldErrors(error.errors)
      }
      setFeedback({ severity: 'error', message: 'Não foi possível salvar as configurações financeiras.' })
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <>
      <CrudFormShell
        backLabel="Financeiro admin"
        backTo="/admin/financeiro"
        title="Configurações financeiras"
        subtitle="Taxa de serviço PegaTicket, custos de processamento estimados e regras de split/custódia — configuração global da plataforma."
        loadError={loadError}
        isLoadingRecord={isLoading}
        formError={formError}
        isSubmitting={isSubmitting}
        onSubmit={(event) => void handleSubmit(event)}
        onCancel={loadSettings}
        submitLabel="Salvar"
        submittingLabel="Salvando…"
      >
        <FormSection
          title="Taxa de serviço PegaTicket"
          description={`Percentual e valor mínimo cobrados por ingresso vendido.${ruleVersion !== null ? ` Versão vigente da regra: ${ruleVersion}.` : ''}`}
        >
          <Box sx={FORM_GRID_2_SX}>
            <TextField
              label="Percentual da taxa"
              type="number"
              value={form.service_fee_percentage}
              onChange={(event) => updateField('service_fee_percentage', event.target.value)}
              error={Boolean(fieldErrors.service_fee_percentage)}
              helperText={fieldErrors.service_fee_percentage?.[0]}
              required
              slotProps={{
                input: { endAdornment: <InputAdornment position="end">%</InputAdornment> },
                htmlInput: { min: 0, max: 100, step: '0.01' },
              }}
            />
            <TextField
              label="Valor mínimo da taxa"
              type="number"
              value={form.service_fee_minimum_amount}
              onChange={(event) => updateField('service_fee_minimum_amount', event.target.value)}
              error={Boolean(fieldErrors.service_fee_minimum_amount)}
              helperText={fieldErrors.service_fee_minimum_amount?.[0]}
              required
              slotProps={{
                input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
                htmlInput: { min: 0, step: '0.01' },
              }}
            />
          </Box>
        </FormSection>

        <FormSection
          title="Custos estimados de processamento"
          description="Percentuais estimados de processamento por meio de pagamento — usados só como referência na simulação de recebimento, não afetam a taxa cobrada."
        >
          <TextField
            label="Pix"
            type="number"
            value={form.estimated_pix_processing_percentage}
            onChange={(event) => updateField('estimated_pix_processing_percentage', event.target.value)}
            error={Boolean(fieldErrors.estimated_pix_processing_percentage)}
            helperText={fieldErrors.estimated_pix_processing_percentage?.[0] ?? 'Deixe em branco se não configurado.'}
            placeholder="Não configurado"
            sx={{ maxWidth: { sm: 260 } }}
            slotProps={{
              input: { endAdornment: <InputAdornment position="end">%</InputAdornment> },
              htmlInput: { min: 0, max: 100, step: '0.01' },
            }}
          />

          <Box>
            <Typography sx={{ fontSize: 13.5, fontWeight: 600, color: 'var(--pt-text)', mb: 1 }}>
              Cartão, por número de parcelas
            </Typography>
            <Box
              sx={{
                display: 'grid',
                gridTemplateColumns: { xs: 'repeat(2, minmax(0, 1fr))', sm: 'repeat(3, minmax(0, 1fr))', md: 'repeat(4, minmax(0, 1fr))' },
                gap: 1.5,
              }}
            >
              {INSTALLMENTS.map((installment) => (
                <TextField
                  key={installment}
                  label={`${installment}x`}
                  type="number"
                  size="small"
                  value={form.estimated_card_processing_percentage_by_installment[String(installment)] ?? ''}
                  onChange={(event) => updateInstallment(installment, event.target.value)}
                  placeholder="—"
                  slotProps={{
                    input: { endAdornment: <InputAdornment position="end">%</InputAdornment> },
                    htmlInput: { min: 0, max: 100, step: '0.01' },
                  }}
                />
              ))}
            </Box>
          </Box>
        </FormSection>

        <Accordion variant="outlined" sx={{ ...SOFT_PANEL_SX, '&::before': { display: 'none' } }}>
          <AccordionSummary expandIcon={<ExpandMoreIcon />}>
            <Typography sx={{ fontSize: 14.5, fontWeight: 600 }}>Configuração de split/custódia</Typography>
          </AccordionSummary>
          <AccordionDetails>
            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2.5 }}>
              <Box sx={FORM_GRID_3_SX}>
                <TextField
                  label="Taxa fixa por transação"
                  type="number"
                  value={form.platform_fee_fixed_amount}
                  onChange={(event) => updateField('platform_fee_fixed_amount', event.target.value)}
                  error={Boolean(fieldErrors.platform_fee_fixed_amount)}
                  helperText={fieldErrors.platform_fee_fixed_amount?.[0]}
                  required
                  slotProps={{
                    input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
                    htmlInput: { min: 0, step: '0.01' },
                  }}
                />
                <TextField
                  label="Prazo padrão de repasse (dias)"
                  type="number"
                  value={form.default_settlement_offset_days}
                  onChange={(event) => updateField('default_settlement_offset_days', event.target.value)}
                  error={Boolean(fieldErrors.default_settlement_offset_days)}
                  helperText={fieldErrors.default_settlement_offset_days?.[0]}
                  required
                  slotProps={{ htmlInput: { min: 0, step: '1' } }}
                />
                <LocalAutocomplete
                  label="Referência do prazo"
                  options={SETTLEMENT_REFERENCE_OPTIONS}
                  value={SETTLEMENT_REFERENCE_OPTIONS.find((option) => option.value === form.settlement_reference) ?? null}
                  onChange={(option) => updateField('settlement_reference', option?.value ?? 'event_end')}
                  getOptionLabel={(option) => option.label}
                  getOptionKey={(option) => option.value}
                  error={Boolean(fieldErrors.settlement_reference)}
                  helperText={fieldErrors.settlement_reference?.[0]}
                />
              </Box>

              <TextField
                label="Conta PagBank primária"
                value={form.pagbank_primary_account_id}
                onChange={(event) => updateField('pagbank_primary_account_id', event.target.value)}
                error={Boolean(fieldErrors.pagbank_primary_account_id)}
                helperText={fieldErrors.pagbank_primary_account_id?.[0]}
                placeholder="Opcional"
                fullWidth
              />

              <FormControlLabel
                control={
                  <Checkbox
                    checked={form.split_custody_enabled}
                    onChange={(event) => updateField('split_custody_enabled', event.target.checked)}
                  />
                }
                label="Custódia via split habilitada"
              />

              <FormControlLabel
                control={
                  <Checkbox
                    checked={form.extra_reserve_enabled}
                    onChange={(event) => updateField('extra_reserve_enabled', event.target.checked)}
                  />
                }
                label="Reserva extra habilitada"
              />

              {form.extra_reserve_enabled && (
                <Box sx={FORM_GRID_2_SX}>
                  <TextField
                    label="Percentual da reserva extra"
                    type="number"
                    value={form.extra_reserve_percentage}
                    onChange={(event) => updateField('extra_reserve_percentage', event.target.value)}
                    error={Boolean(fieldErrors.extra_reserve_percentage)}
                    helperText={fieldErrors.extra_reserve_percentage?.[0]}
                    slotProps={{
                      input: { endAdornment: <InputAdornment position="end">%</InputAdornment> },
                      htmlInput: { min: 0, max: 100, step: '0.01' },
                    }}
                  />
                  <TextField
                    label="Prazo de liberação da reserva (dias)"
                    type="number"
                    value={form.extra_reserve_release_offset_days}
                    onChange={(event) => updateField('extra_reserve_release_offset_days', event.target.value)}
                    error={Boolean(fieldErrors.extra_reserve_release_offset_days)}
                    helperText={fieldErrors.extra_reserve_release_offset_days?.[0]}
                    slotProps={{ htmlInput: { min: 0, step: '1' } }}
                  />
                </Box>
              )}
            </Box>
          </AccordionDetails>
        </Accordion>
      </CrudFormShell>

      <Snackbar
        open={feedback !== null}
        autoHideDuration={5000}
        onClose={() => setFeedback(null)}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
      >
        {feedback ? (
          <Alert severity={feedback.severity} onClose={() => setFeedback(null)} sx={{ width: '100%' }}>
            {feedback.message}
          </Alert>
        ) : undefined}
      </Snackbar>
    </>
  )
}
