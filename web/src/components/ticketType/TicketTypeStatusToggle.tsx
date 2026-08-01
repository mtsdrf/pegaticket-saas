import { Alert, Box, Button, CircularProgress, Popover, Stack, Switch, Typography } from '@mui/material'
import { useState, type ChangeEvent } from 'react'
import * as ticketTypeService from '../../services/ticketTypeService'
import { getApiErrorMessage } from '../../types/api'
import type { TicketType } from '../../types/ticketType'

interface TicketTypeStatusToggleProps {
  ticketType: TicketType
  /** Chamado depois de um toggle bem-sucedido, com o registro já atualizado — quem usa decide como refletir na grid (ex.: `refreshInfiniteCache`). */
  onToggled: (updated: TicketType) => void
}

/**
 * Toggle rápido `ativo`/`pausado` (mesmo espírito de `ProductAvailabilityToggle`,
 * adaptado pro `status` enum de `TicketType` em vez de um booleano) — 1 clique +
 * confirmação leve via `Popover` só ao PAUSAR (efeito visível: some da venda).
 * Não usado para os demais status (rascunho/esgotado/encerrado) — esses são
 * definidos no formulário completo, não no toggle rápido da listagem.
 */
export function TicketTypeStatusToggle({ ticketType, onToggled }: TicketTypeStatusToggleProps) {
  const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null)
  const [isSaving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const isActive = ticketType.status === 'ativo'

  async function apply(nextStatus: 'ativo' | 'pausado') {
    setSaving(true)
    setError(null)
    try {
      const updated = await ticketTypeService.toggleTicketTypeStatus(ticketType.uuid, nextStatus)
      onToggled(updated)
      setAnchorEl(null)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível atualizar o status agora.'))
    } finally {
      setSaving(false)
    }
  }

  function handleChange(event: ChangeEvent<HTMLInputElement>) {
    const nextActive = event.target.checked
    if (nextActive) {
      void apply('ativo')
      return
    }
    setError(null)
    setAnchorEl(event.currentTarget)
  }

  return (
    <>
      <Switch
        checked={isActive}
        onChange={handleChange}
        disabled={isSaving || (!isActive && ticketType.status !== 'pausado')}
        size="small"
        slotProps={{ input: { 'aria-label': `Status de ${ticketType.name}` } }}
      />

      <Popover
        open={Boolean(anchorEl)}
        anchorEl={anchorEl}
        onClose={() => (isSaving ? undefined : setAnchorEl(null))}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'center' }}
        transformOrigin={{ vertical: 'top', horizontal: 'center' }}
      >
        <Box sx={{ p: 2, maxWidth: 260 }}>
          <Typography sx={{ fontWeight: 600, fontSize: 14, mb: 0.5 }}>Pausar vendas?</Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', mb: 1.5 }}>
            "{ticketType.name}" deixa de aparecer na venda e na bilheteria online até ser reativado.
          </Typography>
          {error ? (
            <Alert severity="error" sx={{ mb: 1.5 }}>
              {error}
            </Alert>
          ) : null}
          <Stack direction="row" spacing={1} sx={{ justifyContent: 'flex-end' }}>
            <Button size="small" onClick={() => setAnchorEl(null)} disabled={isSaving}>
              Cancelar
            </Button>
            <Button
              size="small"
              variant="contained"
              color="error"
              onClick={() => void apply('pausado')}
              disabled={isSaving}
              startIcon={isSaving ? <CircularProgress size={14} color="inherit" /> : undefined}
            >
              Pausar
            </Button>
          </Stack>
        </Box>
      </Popover>
    </>
  )
}
