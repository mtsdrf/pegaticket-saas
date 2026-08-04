import { Alert, Box, Button, Chip, Paper, Stack, TextField, Typography } from '@mui/material'
import PersonAddAltOutlinedIcon from '@mui/icons-material/PersonAddAltOutlined'
import { useCallback, useEffect, useState } from 'react'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { useAuth } from '../../hooks/useAuth'
import { ACCESS } from '../../access/requirements'
import * as affiliateService from '../../services/affiliateService'
import type { Affiliate } from '../../types/affiliate'
import { getApiErrorMessage } from '../../types/api'
import { formatCurrency } from '../../utils/format'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'

interface AffiliateFormState {
  uuid: string | null
  name: string
  email: string
  trackingCode: string
  commissionPercentage: string
  status: 'active' | 'inactive'
}

const EMPTY_FORM: AffiliateFormState = {
  uuid: null,
  name: '',
  email: '',
  trackingCode: '',
  commissionPercentage: '',
  status: 'active',
}

/**
 * Afiliados/promotores com link rastreável e comissão sobre vendas
 * atribuídas (roadmap Fase 6, fatia 1). Percentual de comissão é opcional
 * por afiliado — quando ausente, o backend usa o padrão configurado nas
 * Configurações da empresa (affiliate_default_commission_percentage);
 * quando nenhum dos dois existe, nenhuma comissão é calculada.
 */
export function AffiliateListPage() {
  const { hasPermission } = useAuth()
  const canCreate = hasPermission(ACCESS.affiliatesCreate)
  const canUpdate = hasPermission(ACCESS.affiliatesUpdate)

  const [affiliates, setAffiliates] = useState<Affiliate[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState<AffiliateFormState>(EMPTY_FORM)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  const load = useCallback(() => {
    setIsLoading(true)
    setLoadError(null)
    affiliateService
      .listAffiliates()
      .then((result) => setAffiliates(result.items))
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os afiliados agora.')))
      .finally(() => setIsLoading(false))
  }, [])

  useEffect(load, [load])

  function openCreateForm() {
    setForm(EMPTY_FORM)
    setFormError(null)
    setShowForm(true)
  }

  function openEditForm(affiliate: Affiliate) {
    setForm({
      uuid: affiliate.uuid,
      name: affiliate.name,
      email: affiliate.email ?? '',
      trackingCode: affiliate.tracking_code,
      commissionPercentage: affiliate.commission_percentage !== null ? String(affiliate.commission_percentage) : '',
      status: affiliate.status,
    })
    setFormError(null)
    setShowForm(true)
  }

  async function handleSubmit() {
    if (!form.name.trim() || !form.trackingCode.trim()) return
    setFormError(null)
    setIsSubmitting(true)
    try {
      const payload = {
        name: form.name.trim(),
        email: form.email.trim() || undefined,
        tracking_code: form.trackingCode.trim(),
        commission_percentage: form.commissionPercentage.trim() ? Number(form.commissionPercentage) : undefined,
        status: form.status,
      }

      if (form.uuid) {
        await affiliateService.updateAffiliate(form.uuid, payload)
      } else {
        await affiliateService.createAffiliate(payload)
      }

      setShowForm(false)
      setForm(EMPTY_FORM)
      load()
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível salvar o afiliado agora.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudListPage
      title="Afiliados"
      subtitle="Promotores com link rastreável — acompanhe o código de cada um e a comissão acumulada sobre as vendas atribuídas."
      isLoading={isLoading}
      error={loadError}
      onRetry={load}
      isEmpty={affiliates.length === 0 && !showForm}
      emptyIcon={<PersonAddAltOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />}
      emptyTitle="Nenhum afiliado cadastrado"
      emptyDescription="Cadastre um afiliado para gerar o código de rastreio do link dele."
      canCreate={canCreate}
      createLabel="Novo afiliado"
      onCreate={openCreateForm}
    >
      {showForm && (
        <Paper elevation={0} sx={{ p: 2.5, mb: 2.5, ...ELEVATED_SURFACE_SX }}>
          <Typography sx={{ fontWeight: 700, mb: 1.5 }}>{form.uuid ? 'Editar afiliado' : 'Novo afiliado'}</Typography>
          {formError && (
            <Alert severity="error" sx={{ mb: 1.5 }}>
              {formError}
            </Alert>
          )}
          <Stack spacing={2} sx={{ maxWidth: 480 }}>
            <TextField label="Nome" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} required />
            <TextField
              label="E-mail"
              type="email"
              value={form.email}
              onChange={(event) => setForm({ ...form, email: event.target.value })}
            />
            <TextField
              label="Código de rastreio"
              value={form.trackingCode}
              onChange={(event) => setForm({ ...form, trackingCode: event.target.value.toUpperCase() })}
              helperText="Usado no link público da loja (ex.: ?ref=CODIGO) — só letras, números, hífen e underline."
              required
            />
            <TextField
              label="Comissão (%)"
              type="number"
              value={form.commissionPercentage}
              onChange={(event) => setForm({ ...form, commissionPercentage: event.target.value })}
              helperText="Deixe em branco para usar o padrão configurado nas Configurações da empresa."
              slotProps={{ htmlInput: { min: 0, max: 100, step: 0.01 } }}
            />
            <TextField
              select
              label="Status"
              value={form.status}
              onChange={(event) => setForm({ ...form, status: event.target.value as 'active' | 'inactive' })}
              slotProps={{ select: { native: true } }}
            >
              <option value="active">Ativo</option>
              <option value="inactive">Inativo</option>
            </TextField>
            <Stack direction="row" spacing={1.5}>
              <Button
                variant="contained"
                disabled={isSubmitting || !form.name.trim() || !form.trackingCode.trim()}
                onClick={() => void handleSubmit()}
                sx={{ minHeight: 44 }}
              >
                {isSubmitting ? 'Salvando…' : 'Salvar'}
              </Button>
              <Button color="inherit" disabled={isSubmitting} onClick={() => setShowForm(false)} sx={{ minHeight: 44 }}>
                Cancelar
              </Button>
            </Stack>
          </Stack>
        </Paper>
      )}

      <Stack spacing={1.5}>
        {affiliates.map((affiliate) => (
          <Paper
            key={affiliate.uuid}
            elevation={0}
            sx={{ p: 2, ...SOFT_PANEL_SX, cursor: canUpdate ? 'pointer' : 'default' }}
            onClick={canUpdate ? () => openEditForm(affiliate) : undefined}
          >
            <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 1 }}>
              <Box>
                <Typography sx={{ fontWeight: 700 }}>{affiliate.name}</Typography>
                <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
                  Código {affiliate.tracking_code}
                  {affiliate.commission_percentage !== null ? ` · ${affiliate.commission_percentage}% de comissão` : ' · comissão padrão da empresa'}
                </Typography>
              </Box>
              <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                <Typography sx={{ fontWeight: 700 }}>{formatCurrency(affiliate.commissions_total_amount)}</Typography>
                <Chip
                  label={affiliate.status === 'active' ? 'Ativo' : 'Inativo'}
                  size="small"
                  color={affiliate.status === 'active' ? 'success' : 'default'}
                />
              </Stack>
            </Stack>
          </Paper>
        ))}
      </Stack>
    </CrudListPage>
  )
}
