import KeyboardArrowDownIcon from '@mui/icons-material/KeyboardArrowDown'
import ApartmentOutlinedIcon from '@mui/icons-material/ApartmentOutlined'
import CheckCircleRoundedIcon from '@mui/icons-material/CheckCircleRounded'
import {
  Box,
  Button,
  Chip,
  CircularProgress,
  Menu,
  MenuItem,
  Stack,
  Typography,
} from '@mui/material'
import { useState } from 'react'
import { useAuth } from '../hooks/useAuth'
import { useTenants } from '../hooks/useTenants'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../styles/surfaces'

interface TenantMenuProps {
  /** `header`: botão compacto no AppBar (não usado hoje, mantido pra reuso futuro). `menu`: embutido no dropdown de `UserMenu`. */
  variant?: 'header' | 'menu'
  onSelectComplete?: () => void
}

function PlanChip({ planSlug, planName }: { planSlug?: string | null; planName?: string | null }) {
  if (!planName) return null

  const normalized = planSlug?.toLowerCase()
  const palette =
    normalized === 'diamante'
      ? {
          // Rubi/ametista — trocado do dourado original (2026-07-14, ver design-system.md) pra
          // diferenciar melhor do plano Ouro logo abaixo. Fundo sólido (sem gradiente) desde 2026-07-30.
          color: '#7A1152',
          border: 'color-mix(in srgb, #9F1D6B 45%, white)',
          background: 'color-mix(in srgb, #F6D5E6 55%, white)',
        }
      : normalized === 'ouro'
        ? {
            color: '#92400E',
            border: 'color-mix(in srgb, #FBBF24 42%, white)',
            background: 'color-mix(in srgb, #FEF3C7 68%, white)',
          }
        : {
            color: '#334155',
            border: 'color-mix(in srgb, #94A3B8 42%, white)',
            background: 'color-mix(in srgb, #E2E8F0 72%, white)',
          }

  return (
    <Chip
      label={planName}
      size="small"
      sx={{
        height: 24,
        fontWeight: 800,
        fontSize: 11,
        letterSpacing: 0.3,
        textTransform: 'uppercase',
        color: palette.color,
        border: '1px solid',
        borderColor: palette.border,
        background: palette.background,
        '& .MuiChip-label': { px: 1.1 },
      }}
    />
  )
}

export function TenantMenu({ variant = 'header', onSelectComplete }: TenantMenuProps) {
  const { activeTenantUuid, selectTenant } = useAuth()
  const { tenants, error } = useTenants()
  const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null)
  const [switchingUuid, setSwitchingUuid] = useState<string | null>(null)

  async function handleSelect(tenantUuid: string) {
    setAnchorEl(null)
    setSwitchingUuid(tenantUuid)
    try {
      await selectTenant(tenantUuid)
      onSelectComplete?.()
    } finally {
      setSwitchingUuid(null)
    }
  }

  const isMenu = variant === 'menu'

  if (error) {
    return (
      <Typography variant="body2" color="error" sx={{ px: isMenu ? 2 : 1 }}>
        Empresas indisponíveis
      </Typography>
    )
  }

  if (tenants === null) {
    return isMenu ? (
      <Stack direction="row" spacing={1} sx={{ alignItems: 'center', px: 2, py: 1 }}>
        <CircularProgress size={16} />
        <Typography variant="body2" color="text.secondary">
          Carregando empresas...
        </Typography>
      </Stack>
    ) : (
      <CircularProgress size={18} sx={{ mx: 1.5 }} />
    )
  }

  if (tenants.length === 0) {
    return (
      <Typography variant="body2" color="text.secondary" sx={{ px: isMenu ? 2 : 1 }}>
        Sem empresas
      </Typography>
    )
  }

  const activeTenant = tenants.find((tenant) => tenant.tenant_uuid === activeTenantUuid)

  const trigger = (
    <>
      <Typography
        variant="caption"
        sx={{ display: 'block', mb: 0.75, color: 'var(--pt-muted)', fontWeight: 700, letterSpacing: 0.3 }}
      >
        Empresa ativa
      </Typography>

      <Button
        onClick={(event) => setAnchorEl(event.currentTarget)}
        endIcon={<KeyboardArrowDownIcon />}
        color="inherit"
        fullWidth
        sx={{
          justifyContent: 'space-between',
          alignItems: 'flex-start',
          px: 1.4,
          py: 1.2,
          minHeight: 48,
          ...SOFT_PANEL_SX,
          borderRadius: '16px',
          textTransform: 'none',
          background: 'var(--pt-surface-soft)',
        }}
      >
        <Stack direction="row" spacing={1.1} sx={{ minWidth: 0, alignItems: 'flex-start', flex: 1 }}>
          <ApartmentOutlinedIcon sx={{ fontSize: 18, color: 'var(--pt-primary)', mt: 0.15 }} />
          <Box sx={{ minWidth: 0, textAlign: 'left' }}>
            <Stack direction="row" spacing={0.75} sx={{ alignItems: 'center', minWidth: 0, mb: 0.35, flexWrap: 'wrap' }}>
              <Typography
                variant="body2"
                sx={{ fontWeight: 700, color: 'var(--pt-text)', overflow: 'hidden', textOverflow: 'ellipsis' }}
              >
                {activeTenant ? activeTenant.tenant_name : 'Selecionar empresa'}
              </Typography>
              {activeTenant ? <PlanChip planSlug={activeTenant.plan_slug} planName={activeTenant.plan_name} /> : null}
            </Stack>
            <Typography
              variant="caption"
              sx={{
                display: 'block',
                color: 'var(--pt-muted)',
                overflow: 'hidden',
                textOverflow: 'ellipsis',
                whiteSpace: 'nowrap',
              }}
            >
              {activeTenant ? activeTenant.role : 'Escolha uma empresa para continuar'}
            </Typography>
          </Box>
        </Stack>
      </Button>
    </>
  )

  return (
    <>
      {isMenu ? (
        <Box sx={{ px: 2 }}>{trigger}</Box>
      ) : (
        <Button
          onClick={(event) => setAnchorEl(event.currentTarget)}
          endIcon={<KeyboardArrowDownIcon />}
          color="inherit"
          sx={{ textTransform: 'none', maxWidth: { xs: 120, sm: 220 } }}
        >
          <Box component="span" sx={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
            {activeTenant ? activeTenant.tenant_name : 'Selecionar empresa'}
          </Box>
        </Button>
      )}

      <Menu
        anchorEl={anchorEl}
        open={Boolean(anchorEl)}
        onClose={() => setAnchorEl(null)}
        slotProps={{
          paper: {
            sx: {
              mt: 1,
              ...ELEVATED_SURFACE_SX,
              borderRadius: '18px',
              background: 'var(--pt-surface)',
            },
          },
        }}
      >
        {tenants.map((tenant) => (
          <MenuItem
            key={tenant.tenant_uuid}
            selected={tenant.tenant_uuid === activeTenantUuid}
            disabled={switchingUuid === tenant.tenant_uuid}
            onClick={() => void handleSelect(tenant.tenant_uuid)}
            sx={{ minHeight: 48 }}
          >
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center', minWidth: 0 }}>
              {tenant.tenant_uuid === activeTenantUuid ? (
                <CheckCircleRoundedIcon sx={{ fontSize: 16, color: 'var(--pt-success)' }} />
              ) : (
                <Box sx={{ width: 16 }} />
              )}
              <Box sx={{ minWidth: 0 }}>
                <Stack direction="row" spacing={0.75} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
                  <Typography variant="body2" sx={{ fontWeight: 600 }}>
                    {tenant.tenant_name}
                  </Typography>
                  <PlanChip planSlug={tenant.plan_slug} planName={tenant.plan_name} />
                </Stack>
                <Typography variant="caption" color="text.secondary">
                  {tenant.role}
                </Typography>
              </Box>
            </Stack>
          </MenuItem>
        ))}
      </Menu>
    </>
  )
}
