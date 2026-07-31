import ApartmentOutlinedIcon from '@mui/icons-material/ApartmentOutlined'
import CheckCircleRoundedIcon from '@mui/icons-material/CheckCircleRounded'
import {
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  List,
  ListItemButton,
  Stack,
  Typography,
} from '@mui/material'
import { useEffect, useState } from 'react'
import { STORAGE_KEYS } from '../../constants/storage'
import { useAuth } from '../../hooks/useAuth'
import { SOFT_PANEL_SX } from '../../styles/surfaces'

/**
 * Passo obrigatório de "qual empresa você vai acessar", exibido uma vez por
 * sessão de navegador antes do usuário ver qualquer tela (Dashboard incluso)
 * — mesmo quando só existe uma empresa, que já entra pré-selecionada. Troca
 * de empresa durante o uso normal continua pelo `TenantMenu` (sidebar), que
 * não passa por aqui de novo.
 */
export function TenantSelectModal() {
  const { tenants, activeTenantUuid, selectTenant } = useAuth()
  const [open, setOpen] = useState(false)
  const [selectedUuid, setSelectedUuid] = useState<string | null>(null)
  const [isConfirming, setIsConfirming] = useState(false)

  useEffect(() => {
    if (!tenants || tenants.length === 0) return
    if (sessionStorage.getItem(STORAGE_KEYS.tenantSelectionConfirmed)) return

    setSelectedUuid(activeTenantUuid ?? tenants[0].tenant_uuid)
    setOpen(true)
    // Decide só quando a lista de empresas chega pela primeira vez na sessão —
    // não deve reabrir se o usuário trocar de empresa depois pelo TenantMenu.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tenants])

  async function handleConfirm() {
    if (!selectedUuid) return

    setIsConfirming(true)
    try {
      if (selectedUuid !== activeTenantUuid) {
        await selectTenant(selectedUuid)
      }
      sessionStorage.setItem(STORAGE_KEYS.tenantSelectionConfirmed, '1')
      setOpen(false)
    } finally {
      setIsConfirming(false)
    }
  }

  if (!tenants || tenants.length === 0) return null

  return (
    // Sem `onClose`: Esc/clique no backdrop não fecham (mesmo efeito do antigo
    // `disableEscapeKeyDown`, removido nesta versão do MUI) — confirmação é obrigatória.
    <Dialog open={open} maxWidth="xs" fullWidth>
      <DialogTitle sx={{ fontWeight: 700 }}>Selecione a empresa</DialogTitle>
      <DialogContent>
        <Typography variant="body2" sx={{ color: 'var(--mk-muted)', mb: 2 }}>
          Escolha qual empresa você vai acessar agora.
        </Typography>

        <List sx={{ display: 'flex', flexDirection: 'column', gap: 1, p: 0 }}>
          {tenants.map((tenant) => {
            const isSelected = tenant.tenant_uuid === selectedUuid
            return (
              <ListItemButton
                key={tenant.tenant_uuid}
                selected={isSelected}
                onClick={() => setSelectedUuid(tenant.tenant_uuid)}
                sx={{
                  minHeight: 44,
                  ...SOFT_PANEL_SX,
                  '&.Mui-selected': {
                    borderColor: 'var(--mk-primary)',
                    background: 'var(--mk-surface-soft)',
                  },
                }}
              >
                <Stack direction="row" spacing={1.25} sx={{ alignItems: 'center', width: '100%', minWidth: 0 }}>
                  <ApartmentOutlinedIcon sx={{ fontSize: 20, color: 'var(--mk-primary)', flexShrink: 0 }} />
                  <Stack sx={{ minWidth: 0, flex: 1 }}>
                    <Typography variant="body2" sx={{ fontWeight: 700, color: 'var(--mk-text)' }} noWrap>
                      {tenant.tenant_name}
                    </Typography>
                    <Typography variant="caption" sx={{ color: 'var(--mk-muted)' }} noWrap>
                      {tenant.role}
                    </Typography>
                  </Stack>
                  {isSelected && <CheckCircleRoundedIcon sx={{ fontSize: 18, color: 'var(--mk-success)', flexShrink: 0 }} />}
                </Stack>
              </ListItemButton>
            )
          })}
        </List>
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2.5 }}>
        <Button
          variant="contained"
          fullWidth
          disabled={!selectedUuid || isConfirming}
          onClick={() => void handleConfirm()}
          sx={{ minHeight: 44 }}
        >
          {isConfirming ? 'Entrando...' : 'Entrar'}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
