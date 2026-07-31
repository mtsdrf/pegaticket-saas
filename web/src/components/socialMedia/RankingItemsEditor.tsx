import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import { Box, IconButton, Stack, TextField } from '@mui/material'
import type { StoryRankingItem } from '../../types/socialMedia'

interface RankingItemsEditorProps {
  items: StoryRankingItem[]
  onChange: (items: StoryRankingItem[]) => void
}

/**
 * Edição inline de cada linha de um ranking pré-preenchido por análises
 * (produtos mais vendidos, bairros com mais pedidos) — os valores vêm
 * prontos da API, mas continuam editáveis (sem persistir a alteração em
 * lugar nenhum, é só pro story) e removíveis, reaproveitado pelos dois
 * tipos de conteúdo de ranking.
 */
export function RankingItemsEditor({ items, onChange }: RankingItemsEditorProps) {
  function updateItem(index: number, patch: Partial<StoryRankingItem>) {
    onChange(items.map((item, i) => (i === index ? { ...item, ...patch } : item)))
  }

  function removeItem(index: number) {
    onChange(items.filter((_, i) => i !== index))
  }

  return (
    <Stack spacing={1.5}>
      {items.map((item, index) => (
        <Box
          key={index}
          sx={{
            display: 'grid',
            gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr) 44px' },
            gap: 1,
            alignItems: 'flex-start',
          }}
        >
          <TextField
            label={`${index + 1}º lugar`}
            value={item.label}
            onChange={(event) => updateItem(index, { label: event.target.value })}
            size="small"
          />
          <TextField
            label="Valor"
            value={item.primaryValue}
            onChange={(event) => updateItem(index, { primaryValue: event.target.value })}
            size="small"
          />
          <TextField
            label="Detalhe"
            value={item.secondaryValue ?? ''}
            onChange={(event) => updateItem(index, { secondaryValue: event.target.value })}
            size="small"
          />
          <IconButton
            aria-label="Remover item do ranking"
            onClick={() => removeItem(index)}
            sx={{ minWidth: 44, minHeight: 44 }}
          >
            <DeleteOutlineIcon />
          </IconButton>
        </Box>
      ))}
    </Stack>
  )
}
