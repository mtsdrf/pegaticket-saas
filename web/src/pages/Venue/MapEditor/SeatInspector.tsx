import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import LayersOutlinedIcon from '@mui/icons-material/LayersOutlined'
import SearchIcon from '@mui/icons-material/Search'
import {
  Box,
  FormControlLabel,
  IconButton,
  InputAdornment,
  List,
  ListItemButton,
  ListItemText,
  Stack,
  Switch,
  TextField,
  Tooltip,
  Typography,
} from '@mui/material'
import { SEAT_KIND_OPTIONS, SEAT_STATUS_OPTIONS, type Seat, type SeatKind, type SeatPayload, type SeatStatus } from '../../../types/venue'
import { LocalAutocomplete } from '../../../components/crud/LocalAutocomplete'
import { KIND_LABEL, STATUS_LABEL } from './mapVisuals'

interface SeatInspectorProps {
  seats: Seat[]
  selectedUuids: Set<string>
  searchTerm: string
  onSearchTermChange: (value: string) => void
  onSelectSingle: (uuid: string) => void
  onCommit: (uuid: string, payload: Partial<SeatPayload>) => void
  onDelete: (seat: Seat) => void
  onOpenBatchEdit: () => void
}

export function SeatInspector({
  seats,
  selectedUuids,
  searchTerm,
  onSearchTermChange,
  onSelectSingle,
  onCommit,
  onDelete,
  onOpenBatchEdit,
}: SeatInspectorProps) {
  const filtered = searchTerm.trim()
    ? seats.filter((seat) => {
        const term = searchTerm.trim().toLowerCase()
        return seat.label.toLowerCase().includes(term) || (seat.sector_name ?? '').toLowerCase().includes(term)
      })
    : seats

  const selectedList = seats.filter((seat) => selectedUuids.has(seat.uuid))
  const single = selectedList.length === 1 ? selectedList[0] : null

  return (
    <Stack sx={{ height: '100%', minHeight: 0 }}>
      <Box sx={{ p: 1.5, borderBottom: '1px solid var(--pt-border)' }}>
        <TextField
          fullWidth
          size="small"
          placeholder="Buscar por código ou setor"
          value={searchTerm}
          onChange={(event) => onSearchTermChange(event.target.value)}
          slotProps={{ input: { startAdornment: <InputAdornment position="start"><SearchIcon fontSize="small" sx={{ color: 'var(--pt-muted)' }} /></InputAdornment> } }}
        />
      </Box>

      <List dense sx={{ flex: '0 1 220px', overflowY: 'auto', minHeight: 0 }}>
        {filtered.length === 0 ? (
          <Typography sx={{ px: 2, py: 2, fontSize: 13, color: 'var(--pt-muted)' }}>Nenhum assento encontrado.</Typography>
        ) : (
          filtered.map((seat) => (
            <ListItemButton
              key={seat.uuid}
              selected={selectedUuids.has(seat.uuid)}
              onClick={() => onSelectSingle(seat.uuid)}
              sx={{
                minHeight: 44,
                '&.Mui-selected': { background: 'var(--pt-surface-soft)', borderLeft: '3px solid var(--pt-primary)' },
              }}
            >
              <ListItemText
                primary={seat.label}
                secondary={`${KIND_LABEL[seat.kind]}${seat.sector_name ? ` · ${seat.sector_name}` : ''}`}
                slotProps={{
                  primary: { sx: { fontSize: 13.5, fontWeight: 500 } },
                  secondary: { sx: { fontSize: 12 } },
                }}
              />
            </ListItemButton>
          ))
        )}
      </List>

      <Box sx={{ p: 1.5, borderTop: '1px solid var(--pt-border)', flex: 1, overflowY: 'auto', minHeight: 0 }}>
        {selectedList.length === 0 ? (
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
            Selecione um assento no mapa ou na lista para editar. Use shift+arraste no mapa para selecionar vários.
          </Typography>
        ) : selectedList.length > 1 ? (
          <Stack spacing={1.5} sx={{ alignItems: 'flex-start' }}>
            <Typography sx={{ fontSize: 13.5, fontWeight: 600 }}>{selectedList.length} assentos selecionados</Typography>
            <Tooltip title="Aplicar setor, tipo, capacidade ou status a todos os selecionados" arrow>
              <span>
                <IconButton
                  onClick={onOpenBatchEdit}
                  sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-primary)', border: '1px solid var(--pt-border)', borderRadius: 'var(--pt-radius-sm)' }}
                  aria-label="Editar seleção em lote"
                >
                  <LayersOutlinedIcon fontSize="small" />
                </IconButton>
              </span>
            </Tooltip>
          </Stack>
        ) : single ? (
          <SingleSeatForm key={single.uuid} seat={single} onCommit={onCommit} onDelete={onDelete} />
        ) : null}
      </Box>
    </Stack>
  )
}

function SingleSeatForm({
  seat,
  onCommit,
  onDelete,
}: {
  seat: Seat
  onCommit: (uuid: string, payload: Partial<SeatPayload>) => void
  onDelete: (seat: Seat) => void
}) {
  return (
    <Stack spacing={1.5}>
      <Stack direction="row" sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
        <Typography sx={{ fontSize: 13.5, fontWeight: 600 }}>Editar assento</Typography>
        <Tooltip title="Excluir assento" arrow>
          <IconButton
            size="small"
            aria-label={`Excluir ${seat.label}`}
            onClick={() => onDelete(seat)}
            sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-danger)' } }}
          >
            <DeleteOutlineIcon fontSize="small" />
          </IconButton>
        </Tooltip>
      </Stack>

      <TextField
        label="Código"
        size="small"
        defaultValue={seat.label}
        onBlur={(event) => {
          const value = event.target.value.trim()
          if (value && value !== seat.label) onCommit(seat.uuid, { label: value })
        }}
      />

      <TextField
        label="Setor"
        size="small"
        defaultValue={seat.sector_name ?? ''}
        onBlur={(event) => {
          const value = event.target.value.trim()
          if (value !== (seat.sector_name ?? '')) onCommit(seat.uuid, { sector_name: value || null })
        }}
      />

      <LocalAutocomplete
        label="Tipo"
        size="small"
        options={SEAT_KIND_OPTIONS}
        value={SEAT_KIND_OPTIONS.find((option) => option.value === seat.kind) ?? null}
        onChange={(option) => option && onCommit(seat.uuid, { kind: option.value as SeatKind })}
        getOptionLabel={(option) => option.label}
        getOptionKey={(option) => option.value}
      />

      <TextField
        label="Capacidade"
        size="small"
        type="number"
        defaultValue={seat.capacity}
        slotProps={{ htmlInput: { min: 1, step: '1' } }}
        onBlur={(event) => {
          const value = Number(event.target.value)
          if (value && value !== seat.capacity) onCommit(seat.uuid, { capacity: value })
        }}
      />

      <Stack direction="row" spacing={1.5}>
        <TextField
          label="Posição X"
          size="small"
          type="number"
          value={seat.pos_x}
          slotProps={{ htmlInput: { step: '0.01' } }}
          onChange={(event) => {
            const value = Number(event.target.value)
            if (!Number.isNaN(value)) onCommit(seat.uuid, { pos_x: value })
          }}
        />
        <TextField
          label="Posição Y"
          size="small"
          type="number"
          value={seat.pos_y}
          slotProps={{ htmlInput: { step: '0.01' } }}
          onChange={(event) => {
            const value = Number(event.target.value)
            if (!Number.isNaN(value)) onCommit(seat.uuid, { pos_y: value })
          }}
        />
      </Stack>

      <Stack direction="row" spacing={1.5}>
        <TextField
          label="Largura"
          size="small"
          type="number"
          value={seat.width ?? ''}
          slotProps={{ htmlInput: { min: 0.01, step: '0.01' } }}
          onChange={(event) => {
            const raw = event.target.value
            if (raw === '') {
              onCommit(seat.uuid, { width: null })
              return
            }

            const value = Number(raw)
            if (!Number.isNaN(value) && value > 0) onCommit(seat.uuid, { width: value })
          }}
        />
        <TextField
          label="Altura"
          size="small"
          type="number"
          value={seat.height ?? ''}
          slotProps={{ htmlInput: { min: 0.01, step: '0.01' } }}
          onChange={(event) => {
            const raw = event.target.value
            if (raw === '') {
              onCommit(seat.uuid, { height: null })
              return
            }

            const value = Number(raw)
            if (!Number.isNaN(value) && value > 0) onCommit(seat.uuid, { height: value })
          }}
        />
      </Stack>

      <LocalAutocomplete
        label="Status"
        size="small"
        options={SEAT_STATUS_OPTIONS}
        value={SEAT_STATUS_OPTIONS.find((option) => option.value === seat.status) ?? null}
        onChange={(option) => option && onCommit(seat.uuid, { status: option.value as SeatStatus })}
        getOptionLabel={(option) => option.label}
        getOptionKey={(option) => option.value}
      />

      <FormControlLabel
        control={<Switch checked={seat.is_accessible} onChange={(event) => onCommit(seat.uuid, { is_accessible: event.target.checked })} />}
        label="Acessível"
      />

      <Typography sx={{ fontSize: 11.5, color: 'var(--pt-muted)' }}>
        Dica: com o assento selecionado, use as setas do teclado para mover 1 unidade (Shift+seta move 10).
      </Typography>
    </Stack>
  )
}

export const SEAT_STATUS_LABELS = STATUS_LABEL
