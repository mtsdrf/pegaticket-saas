import SearchOutlinedIcon from '@mui/icons-material/SearchOutlined'
import { Box, Dialog, InputBase, List, ListItemButton, ListItemText, Typography } from '@mui/material'
import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { fuzzyMatchScore } from '../../utils/fuzzyMatch'

export interface CommandPaletteItem {
  to: string
  label: string
  group?: string
}

interface RankedItem extends CommandPaletteItem {
  score: number
}

function rankItems(items: CommandPaletteItem[], query: string): RankedItem[] {
  if (!query.trim()) {
    return items.map((item) => ({ ...item, score: 0 }))
  }

  const ranked: RankedItem[] = []
  for (const item of items) {
    const score = fuzzyMatchScore(query, item.label)
    if (score !== null) ranked.push({ ...item, score })
  }
  return ranked.sort((a, b) => a.score - b.score)
}

/**
 * Busca universal (`Ctrl+K`/`Cmd+K`) — navega entre as telas já existentes
 * no menu (`AppLayout`/`NAV_ITEMS`), sem buscar dado de negócio (cliente,
 * pedido, produto específico). Fuzzy simples por subsequência, sem libs
 * externas (`utils/fuzzyMatch.ts`).
 */
export function CommandPalette({
  items,
  open,
  onOpenChange,
}: {
  items: CommandPaletteItem[]
  open: boolean
  onOpenChange: (open: boolean) => void
}) {
  const [query, setQuery] = useState('')
  const [highlightedIndex, setHighlightedIndex] = useState(0)
  const inputRef = useRef<HTMLInputElement>(null)
  const navigate = useNavigate()

  const results = useMemo(() => rankItems(items, query), [items, query])

  useEffect(() => {
    setHighlightedIndex(0)
  }, [query, open])

  useEffect(() => {
    function handleGlobalKeyDown(event: KeyboardEvent) {
      const isShortcut = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k'
      if (isShortcut) {
        event.preventDefault()
        onOpenChange(!open)
      }
    }

    document.addEventListener('keydown', handleGlobalKeyDown)
    return () => document.removeEventListener('keydown', handleGlobalKeyDown)
  }, [open, onOpenChange])

  function handleClose() {
    onOpenChange(false)
    setQuery('')
  }

  function handleSelect(to: string) {
    navigate(to)
    handleClose()
  }

  function handleKeyDown(event: React.KeyboardEvent<HTMLInputElement>) {
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      setHighlightedIndex((current) => Math.min(current + 1, results.length - 1))
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      setHighlightedIndex((current) => Math.max(current - 1, 0))
    } else if (event.key === 'Enter') {
      event.preventDefault()
      const target = results[highlightedIndex]
      if (target) handleSelect(target.to)
    }
  }

  return (
    <Dialog
      open={open}
      onClose={handleClose}
      fullWidth
      maxWidth="sm"
      slotProps={{
        transition: {
          onEntered: () => inputRef.current?.focus(),
        },
        paper: {
          sx: {
            ...ELEVATED_SURFACE_SX,
            position: 'fixed',
            top: { xs: 16, sm: '12vh' },
            m: { xs: 1, sm: 0 },
            width: { xs: 'calc(100% - 16px)', sm: '100%' },
          },
        },
      }}
    >
      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, px: 2.25, py: 1.75, borderBottom: '1px solid var(--pt-border)' }}>
        <SearchOutlinedIcon sx={{ color: 'var(--pt-muted)' }} fontSize="small" />
        <InputBase
          inputRef={inputRef}
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          onKeyDown={handleKeyDown}
          placeholder="Buscar telas e páginas..."
          fullWidth
          sx={{ fontSize: 15 }}
          inputProps={{ 'aria-label': 'Buscar telas e páginas' }}
        />
      </Box>

      <List sx={{ maxHeight: '55vh', overflowY: 'auto', py: 0.5 }}>
        {results.length === 0 && (
          <Box sx={{ px: 2, py: 3, textAlign: 'center' }}>
            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>Nenhuma tela encontrada.</Typography>
          </Box>
        )}

        {results.map((item, index) => (
          <ListItemButton
            key={item.to}
            selected={index === highlightedIndex}
            onMouseEnter={() => setHighlightedIndex(index)}
            onClick={() => handleSelect(item.to)}
            sx={{ minHeight: 46, mx: 0.75, mb: 0.5, ...SOFT_PANEL_SX }}
          >
            <ListItemText
              primary={item.label}
              secondary={item.group}
              slotProps={{
                primary: { sx: { fontSize: 14, fontWeight: 600 } },
                secondary: { sx: { fontSize: 12, color: 'var(--pt-muted)' } },
              }}
            />
          </ListItemButton>
        ))}
      </List>
    </Dialog>
  )
}
