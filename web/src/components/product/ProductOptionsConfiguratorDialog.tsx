import AddIcon from '@mui/icons-material/Add'
import RemoveCircleOutlineIcon from '@mui/icons-material/RemoveCircleOutlineOutlined'
import RemoveIcon from '@mui/icons-material/Remove'
import {
  Box,
  Button,
  Checkbox,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
  Stack,
  Typography,
} from '@mui/material'
import { useEffect, useMemo, useState } from 'react'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { formatCurrency } from '../../utils/format'

export interface ProductOptionsDialogOption {
  uuid: string
  name: string
  description: string | null
  price: number
}

export interface ProductOptionsDialogGroup {
  uuid: string
  name: string
  description: string | null
  /** `addon` (default) soma preço ao escolher; `ingredient_removal` renderiza como checkbox "Remover X" — visual distinto, sem contador de quantidade. */
  kind?: 'addon' | 'ingredient_removal'
  min_select: number
  max_select: number
  options: ProductOptionsDialogOption[]
}

export interface ProductOptionSelection {
  product_option_uuid: string
  group_name: string
  name: string
  quantity: number
  unit_price: number
}

interface ProductOptionsConfiguratorDialogProps {
  open: boolean
  title: string
  groups: ProductOptionsDialogGroup[]
  initialSelections?: ProductOptionSelection[]
  onClose: () => void
  onConfirm: (selections: ProductOptionSelection[]) => void
}

export function ProductOptionsConfiguratorDialog({
  open,
  title,
  groups,
  initialSelections = [],
  onClose,
  onConfirm,
}: ProductOptionsConfiguratorDialogProps) {
  const [quantities, setQuantities] = useState<Record<string, number>>({})

  useEffect(() => {
    if (!open) return
    const next: Record<string, number> = {}
    for (const selection of initialSelections) {
      next[selection.product_option_uuid] = selection.quantity
    }
    setQuantities(next)
  }, [initialSelections, open])

  const selectionCountByGroup = useMemo(() => {
    const counts: Record<string, number> = {}
    for (const group of groups) {
      counts[group.uuid] = group.options.reduce((sum, option) => sum + (quantities[option.uuid] ?? 0), 0)
    }
    return counts
  }, [groups, quantities])

  const validationMessage = useMemo(() => {
    for (const group of groups) {
      const count = selectionCountByGroup[group.uuid] ?? 0
      if (count < group.min_select) {
        return `Selecione pelo menos ${group.min_select} opção(ões) em "${group.name}".`
      }
      if (count > group.max_select) {
        return `Selecione no máximo ${group.max_select} opção(ões) em "${group.name}".`
      }
    }
    return null
  }, [groups, selectionCountByGroup])

  function changeQuantity(group: ProductOptionsDialogGroup, option: ProductOptionsDialogOption, delta: number) {
    setQuantities((current) => {
      const currentQuantity = current[option.uuid] ?? 0
      const nextQuantity = Math.max(0, currentQuantity + delta)
      const currentGroupCount = selectionCountByGroup[group.uuid] ?? 0
      const projectedGroupCount = currentGroupCount - currentQuantity + nextQuantity

      if (delta > 0 && projectedGroupCount > group.max_select) {
        return current
      }

      return {
        ...current,
        [option.uuid]: nextQuantity,
      }
    })
  }

  function handleConfirm() {
    const selections: ProductOptionSelection[] = []

    for (const group of groups) {
      for (const option of group.options) {
        const quantity = quantities[option.uuid] ?? 0
        if (quantity <= 0) continue

        selections.push({
          product_option_uuid: option.uuid,
          group_name: group.name,
          name: option.name,
          quantity,
          unit_price: option.price,
        })
      }
    }

    onConfirm(selections)
  }

  return (
    <Dialog open={open} onClose={onClose} fullWidth maxWidth="sm">
      <DialogTitle sx={{ fontWeight: 700 }}>{title}</DialogTitle>
      <DialogContent dividers>
        <Stack spacing={2}>
          {groups.map((group) => {
            const selectedCount = selectionCountByGroup[group.uuid] ?? 0
            const isRemovalGroup = group.kind === 'ingredient_removal'
            return (
              <Box
                key={group.uuid}
                sx={{ p: 1.5, ...SOFT_PANEL_SX }}
              >
                <Stack direction="row" spacing={0.75} sx={{ alignItems: 'center', mb: 1.25 }}>
                  {isRemovalGroup && <RemoveCircleOutlineIcon sx={{ fontSize: 18, color: 'var(--pt-muted)' }} />}
                  <Stack spacing={0.5}>
                    <Typography sx={{ fontSize: 15, fontWeight: 700 }}>{group.name}</Typography>
                    {group.description && (
                      <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>{group.description}</Typography>
                    )}
                    {!isRemovalGroup && (
                      <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>
                        Escolha de {group.min_select} até {group.max_select}. Selecionados: {selectedCount}
                      </Typography>
                    )}
                  </Stack>
                </Stack>

                <Stack spacing={isRemovalGroup ? 0 : 1}>
                  {group.options.map((option) => {
                    const quantity = quantities[option.uuid] ?? 0

                    if (isRemovalGroup) {
                      const isChecked = quantity > 0
                      return (
                        <Stack
                          key={option.uuid}
                          direction="row"
                          spacing={0.5}
                          component="label"
                          sx={{ alignItems: 'center', cursor: 'pointer', minHeight: 44 }}
                        >
                          <Checkbox
                            checked={isChecked}
                            onChange={() => changeQuantity(group, option, isChecked ? -1 : 1)}
                            size="small"
                          />
                          <Box sx={{ minWidth: 0 }}>
                            <Typography sx={{ fontSize: 14, fontWeight: 600 }}>Remover {option.name}</Typography>
                            {option.description && (
                              <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>{option.description}</Typography>
                            )}
                          </Box>
                        </Stack>
                      )
                    }

                    return (
                      <Stack
                        key={option.uuid}
                        direction="row"
                        spacing={1}
                        sx={{ alignItems: 'center', justifyContent: 'space-between', gap: 1 }}
                      >
                        <Box sx={{ minWidth: 0, flex: 1 }}>
                          <Typography sx={{ fontSize: 14, fontWeight: 600 }}>{option.name}</Typography>
                          {option.description && (
                            <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>{option.description}</Typography>
                          )}
                          <Typography sx={{ fontSize: 12.5, color: 'var(--pt-primary)', fontWeight: 700 }}>
                            + {formatCurrency(option.price)}
                          </Typography>
                        </Box>

                        <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
                          <IconButton
                            size="small"
                            onClick={() => changeQuantity(group, option, -1)}
                            sx={{ ...SOFT_PANEL_SX }}
                          >
                            <RemoveIcon fontSize="small" />
                          </IconButton>
                          <Typography sx={{ minWidth: 20, textAlign: 'center', fontWeight: 700 }}>{quantity}</Typography>
                          <IconButton
                            size="small"
                            onClick={() => changeQuantity(group, option, 1)}
                            sx={{ ...SOFT_PANEL_SX }}
                          >
                            <AddIcon fontSize="small" />
                          </IconButton>
                        </Stack>
                      </Stack>
                    )
                  })}
                </Stack>
              </Box>
            )
          })}

          {validationMessage && (
            <Typography sx={{ fontSize: 12.5, color: 'var(--pt-danger)' }}>{validationMessage}</Typography>
          )}
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose}>Cancelar</Button>
        <Button variant="contained" onClick={handleConfirm} disabled={Boolean(validationMessage)}>
          Confirmar opcionais
        </Button>
      </DialogActions>
    </Dialog>
  )
}
