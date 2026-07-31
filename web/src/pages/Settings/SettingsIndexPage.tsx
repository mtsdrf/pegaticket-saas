import ChevronRightIcon from '@mui/icons-material/ChevronRight'
import TuneOutlinedIcon from '@mui/icons-material/TuneOutlined'
import { Box, List, ListItemButton, ListItemIcon, ListItemText, Typography } from '@mui/material'
import { Link as RouterLink } from 'react-router-dom'
import { EmptyState } from '../../components/layout/EmptyState'
import { PageHeader } from '../../components/layout/PageHeader'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { useSettingsDesktopLayout } from './blocks/useSettingsDesktopLayout'
import { useVisibleSettingsEntries } from './blocks/useVisibleSettingsEntries'

/**
 * Rota índice `/configuracoes` — em mobile é a lista tocável de todos os
 * blocos/entradas (navegação de página cheia ao tocar); em desktop o rail
 * de `SettingsHubLayout` já mostra a mesma lista, então aqui só um convite
 * a escolher um item (evita duplicar a lista dentro do próprio painel).
 */
export function SettingsIndexPage() {
  const isDesktop = useSettingsDesktopLayout()
  const { blocks, links } = useVisibleSettingsEntries()

  if (isDesktop) {
    return (
      <Box
        sx={{
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
          minHeight: 320,
          textAlign: 'center',
          color: 'var(--pt-muted)',
        }}
      >
        <TuneOutlinedIcon sx={{ fontSize: 40, mb: 1.5, opacity: 0.6 }} />
        <Typography sx={{ fontSize: 15 }}>Selecione um item ao lado para editar.</Typography>
      </Box>
    )
  }

  return (
    <Box sx={{ maxWidth: 900 }}>
      <PageHeader title="Configurações" subtitle="Ajustes gerais da sua empresa no PegaTicket." />

      {blocks.length === 0 && links.length === 0 ? (
        <EmptyState
          icon={<TuneOutlinedIcon sx={{ fontSize: 36, color: 'var(--pt-muted)' }} />}
          title="Nenhum ajuste disponível"
          description="Seu usuário não tem permissão para ver nenhum item de configuração ainda."
        />
      ) : (
      <List sx={{ p: 0 }}>
        {blocks.map((block) => (
          <ListItemButton
            key={block.key}
            component={RouterLink}
            to={`/configuracoes/${block.path}`}
            sx={{
              minHeight: 64,
              mb: 1,
              ...ELEVATED_SURFACE_SX,
            }}
          >
            <ListItemIcon sx={{ color: 'var(--pt-primary)', minWidth: 44 }}>
              <block.icon />
            </ListItemIcon>
            <ListItemText
              primary={block.label}
              secondary={block.description}
              slotProps={{
                primary: { sx: { fontSize: 15, fontWeight: 600 } },
                secondary: { sx: { fontSize: 13, color: 'var(--pt-muted)' } },
              }}
            />
            <ChevronRightIcon sx={{ color: 'var(--pt-muted)' }} />
          </ListItemButton>
        ))}

        {links.map((link) => (
          <ListItemButton
            key={link.key}
            component={RouterLink}
            to={link.to}
            sx={{
              minHeight: 64,
              mb: 1,
              ...ELEVATED_SURFACE_SX,
            }}
          >
            <ListItemIcon sx={{ color: 'var(--pt-primary)', minWidth: 44 }}>
              <link.icon />
            </ListItemIcon>
            <ListItemText
              primary={link.label}
              secondary={link.description}
              slotProps={{
                primary: { sx: { fontSize: 15, fontWeight: 600 } },
                secondary: { sx: { fontSize: 13, color: 'var(--pt-muted)' } },
              }}
            />
            <ChevronRightIcon sx={{ color: 'var(--pt-muted)' }} />
          </ListItemButton>
        ))}
      </List>
      )}
    </Box>
  )
}
