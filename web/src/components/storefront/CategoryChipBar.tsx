import { Chip, Stack } from '@mui/material'
import type { StorefrontCategory } from '../../types/storefront'

interface CategoryChipBarProps {
  categories: StorefrontCategory[]
  selectedUuid: string | null
  onSelect: (uuid: string | null) => void
}

/** Barra de categorias com scroll horizontal (vitrine estilo iFood) — "Todos" fixo + 1 chip por categoria com produto disponível. Só faz sentido com mais de 1 categoria (ver `StorefrontCatalogPage`, que decide quando renderizar). */
export function CategoryChipBar({ categories, selectedUuid, onSelect }: CategoryChipBarProps) {
  return (
    <Stack
      direction="row"
      spacing={1}
      sx={{
        overflowX: 'auto',
        pb: 0.5,
        mb: 2.5,
        scrollbarWidth: 'none',
        '&::-webkit-scrollbar': { display: 'none' },
      }}
    >
      <Chip
        label="Todos"
        clickable
        onClick={() => onSelect(null)}
        color={selectedUuid === null ? 'primary' : 'default'}
        variant={selectedUuid === null ? 'filled' : 'outlined'}
        sx={{ fontWeight: 600, flexShrink: 0 }}
      />
      {categories.map((category) => (
        <Chip
          key={category.uuid}
          label={category.name}
          clickable
          onClick={() => onSelect(category.uuid)}
          color={selectedUuid === category.uuid ? 'primary' : 'default'}
          variant={selectedUuid === category.uuid ? 'filled' : 'outlined'}
          sx={{ fontWeight: 600, flexShrink: 0 }}
        />
      ))}
    </Stack>
  )
}
