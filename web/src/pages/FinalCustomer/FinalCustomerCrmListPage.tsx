import GroupOutlinedIcon from '@mui/icons-material/GroupOutlined'
import { Box, Chip, MenuItem, Paper, Stack, TextField, Typography } from '@mui/material'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { CrudListPage } from '../../components/crud/CrudListPage'
import * as finalCustomerCrmService from '../../services/finalCustomerCrmService'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type { FinalCustomerCrmEntry } from '../../types/finalCustomerCrm'
import type { PaginationMeta } from '../../types/pagination'
import { formatCurrency, formatDateTimeBR } from '../../utils/format'

type SegmentFilter = 'all' | 'min_spent' | 'inactive_days' | 'at_least_one_purchase'

const PER_PAGE = 20

/**
 * CRM básico do comprador (roadmap Fase 6) — lista compradores do tenant
 * com total gasto/quantidade de compras/última compra já agregados pelo
 * backend, com segmentação simples client-driven (filtros enviados como
 * query params, sem motor de regras salvas em banco — deliberadamente fora
 * de escopo nesta rodada).
 */
export function FinalCustomerCrmListPage() {
  const [entries, setEntries] = useState<FinalCustomerCrmEntry[]>([])
  const [pagination, setPagination] = useState<PaginationMeta | null>(null)
  const [page, setPage] = useState(1)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  const [search, setSearch] = useState('')
  const [segment, setSegment] = useState<SegmentFilter>('all')
  const [minSpentInput, setMinSpentInput] = useState('100')
  const [inactiveDaysInput, setInactiveDaysInput] = useState('60')

  const filters = useMemo(() => {
    const base = { search: search.trim() || undefined, page, per_page: PER_PAGE }

    if (segment === 'min_spent') {
      return { ...base, min_spent: Number(minSpentInput) || undefined }
    }

    if (segment === 'inactive_days') {
      return { ...base, inactive_days: Number(inactiveDaysInput) || undefined }
    }

    if (segment === 'at_least_one_purchase') {
      return { ...base, min_purchases: 1 }
    }

    return base
  }, [search, segment, minSpentInput, inactiveDaysInput, page])

  const load = useCallback(() => {
    setIsLoading(true)
    setLoadError(null)
    finalCustomerCrmService
      .listFinalCustomersCrm(filters)
      .then((result) => {
        setEntries(result.items)
        setPagination(result.pagination)
      })
      .catch((error: unknown) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os clientes agora.')))
      .finally(() => setIsLoading(false))
  }, [filters])

  useEffect(load, [load])

  useEffect(() => {
    setPage(1)
  }, [search, segment, minSpentInput, inactiveDaysInput])

  function displayName(entry: FinalCustomerCrmEntry): string {
    const full = [entry.name, entry.last_name].filter(Boolean).join(' ').trim()
    return full || entry.email
  }

  return (
    <CrudListPage
      title="Clientes"
      subtitle="Compradores que já passaram pela sua loja — total gasto, quantidade de compras e última compra, tudo agregado das suas vendas."
      isLoading={isLoading}
      error={loadError}
      onRetry={load}
      isEmpty={entries.length === 0}
      emptyIcon={<GroupOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />}
      emptyTitle="Nenhum cliente encontrado"
      emptyDescription="Ajuste a busca ou o filtro de segmentação, ou aguarde a primeira venda da loja."
      canCreate={false}
      pagination={pagination}
      onPageChange={setPage}
      toolbar={
        <Stack spacing={1.5} sx={{ width: '100%' }}>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
            <TextField
              label="Buscar por nome, e-mail ou telefone"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              size="small"
              fullWidth
            />
            <TextField
              select
              label="Segmentação"
              value={segment}
              onChange={(event) => setSegment(event.target.value as SegmentFilter)}
              size="small"
              sx={{ minWidth: { xs: '100%', sm: 260 } }}
            >
              <MenuItem value="all">Todos os clientes</MenuItem>
              <MenuItem value="min_spent">Gastou acima de um valor</MenuItem>
              <MenuItem value="inactive_days">Não compra há mais de N dias</MenuItem>
              <MenuItem value="at_least_one_purchase">Comprou pelo menos 1 vez</MenuItem>
            </TextField>
          </Stack>

          {segment === 'min_spent' && (
            <TextField
              label="Valor mínimo gasto (R$)"
              type="number"
              value={minSpentInput}
              onChange={(event) => setMinSpentInput(event.target.value)}
              size="small"
              sx={{ maxWidth: 220 }}
              slotProps={{ htmlInput: { min: 0, step: 0.01 } }}
            />
          )}

          {segment === 'inactive_days' && (
            <TextField
              label="Dias sem comprar"
              type="number"
              value={inactiveDaysInput}
              onChange={(event) => setInactiveDaysInput(event.target.value)}
              size="small"
              sx={{ maxWidth: 220 }}
              slotProps={{ htmlInput: { min: 1 } }}
            />
          )}
        </Stack>
      }
    >
      <Stack spacing={1.5}>
        {entries.map((entry) => (
          <Paper key={entry.uuid} elevation={0} sx={{ p: 2, ...SOFT_PANEL_SX }}>
            <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 1 }}>
              <Box sx={{ minWidth: 0 }}>
                <Typography sx={{ fontWeight: 700 }}>{displayName(entry)}</Typography>
                <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
                  {entry.email}
                  {entry.phone_primary ? ` · ${entry.phone_primary}` : ''}
                </Typography>
                <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', mt: 0.25 }}>
                  {entry.last_purchase_at
                    ? `Última compra em ${formatDateTimeBR(entry.last_purchase_at)}`
                    : 'Ainda não comprou'}
                </Typography>
              </Box>
              <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                <Chip
                  label={`${entry.purchase_count} compra${entry.purchase_count === 1 ? '' : 's'}`}
                  size="small"
                  variant="outlined"
                />
                <Typography sx={{ fontWeight: 700 }}>{formatCurrency(entry.total_spent)}</Typography>
              </Stack>
            </Stack>
          </Paper>
        ))}
      </Stack>
    </CrudListPage>
  )
}
