import { useState } from 'react'
import { Alert, Button, Dialog, DialogActions, DialogContent, DialogContentText, DialogTitle, Stack, TextField } from '@mui/material'
import { LocalAutocomplete } from '../../../components/crud/LocalAutocomplete'
import { SEAT_KIND_OPTIONS, SEAT_STATUS_OPTIONS, type SeatKind, type SeatPayload, type SeatStatus } from '../../../types/venue'

const KEEP_VALUE = '__keep__'

const ACCESSIBLE_OPTIONS = [
  { value: KEEP_VALUE, label: 'Manter como está' },
  { value: 'true', label: 'Sim' },
  { value: 'false', label: 'Não' },
]

interface BatchEditDialogProps {
  open: boolean
  count: number
  isSaving: boolean
  error: string | null
  onCancel: () => void
  onApply: (payload: Partial<SeatPayload>) => void
}

/** Diálogo de edição em lote — só envia campos preenchidos/alterados; campos "Manter" ficam de fora do payload aplicado a cada assento selecionado. */
export function BatchEditDialog({ open, count, isSaving, error, onCancel, onApply }: BatchEditDialogProps) {
  const [sectorName, setSectorName] = useState('')
  const [kind, setKind] = useState<{ value: SeatKind; label: string } | null>(null)
  const [capacity, setCapacity] = useState('')
  const [status, setStatus] = useState<{ value: SeatStatus; label: string } | null>(null)
  const [accessible, setAccessible] = useState(ACCESSIBLE_OPTIONS[0])

  function handleApply() {
    const payload: Partial<SeatPayload> = {}
    if (sectorName.trim()) payload.sector_name = sectorName.trim()
    if (kind) payload.kind = kind.value
    if (capacity.trim()) payload.capacity = Number(capacity)
    if (status) payload.status = status.value
    if (accessible.value !== KEEP_VALUE) payload.is_accessible = accessible.value === 'true'
    onApply(payload)
  }

  return (
    <Dialog open={open} onClose={isSaving ? undefined : onCancel} maxWidth="xs" fullWidth>
      <DialogTitle sx={{ fontWeight: 600 }}>Editar {count} assentos selecionados</DialogTitle>
      <DialogContent>
        <DialogContentText sx={{ mb: 2, color: 'var(--pt-muted)', fontSize: 13.5 }}>
          Só os campos preenchidos abaixo serão aplicados a todos os selecionados. Deixe em branco/"Manter" o que não deve mudar.
        </DialogContentText>
        <Stack spacing={2}>
          <TextField label="Setor (opcional)" size="small" value={sectorName} onChange={(event) => setSectorName(event.target.value)} placeholder="Manter como está" />
          <LocalAutocomplete
            label="Tipo (opcional)"
            size="small"
            options={SEAT_KIND_OPTIONS}
            value={kind}
            onChange={setKind}
            getOptionLabel={(option) => option.label}
            getOptionKey={(option) => option.value}
            placeholder="Manter como está"
          />
          <TextField
            label="Capacidade (opcional)"
            size="small"
            type="number"
            value={capacity}
            onChange={(event) => setCapacity(event.target.value)}
            placeholder="Manter como está"
            slotProps={{ htmlInput: { min: 1, step: '1' } }}
          />
          <LocalAutocomplete
            label="Status (opcional)"
            size="small"
            options={SEAT_STATUS_OPTIONS}
            value={status}
            onChange={setStatus}
            getOptionLabel={(option) => option.label}
            getOptionKey={(option) => option.value}
            placeholder="Manter como está"
          />
          <LocalAutocomplete
            label="Acessível"
            size="small"
            options={ACCESSIBLE_OPTIONS}
            value={accessible}
            onChange={(option) => setAccessible(option ?? ACCESSIBLE_OPTIONS[0])}
            getOptionLabel={(option) => option.label}
            getOptionKey={(option) => option.value}
          />
        </Stack>
        {error ? <Alert severity="error" sx={{ mt: 2 }}>{error}</Alert> : null}
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2, gap: 1 }}>
        <Button onClick={onCancel} disabled={isSaving} color="inherit">
          Cancelar
        </Button>
        <Button onClick={handleApply} disabled={isSaving} variant="contained">
          {isSaving ? 'Aplicando…' : 'Aplicar a todos'}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
