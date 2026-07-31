import { Box, Paper } from '@mui/material'
import type { ReactNode } from 'react'
import { PageHeader } from '../../components/layout/PageHeader'
import { FORM_FIELD_SURFACE_SX } from '../../styles/formFieldStyles'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { CONTENT_CONTAINER_SX, SECTION_CARD_PADDING_SX } from '../../styles/layoutStandards'

const SECTION_SX = {
  ...SECTION_CARD_PADDING_SX,
  // Preenche o espaço restante até o fim da tela no layout mestre-detalhe
  // do desktop (SettingsHubLayout já passa a altura disponível pro Outlet).
  flex: 1,
  ...ELEVATED_SURFACE_SX,
  ...FORM_FIELD_SURFACE_SX,
} as const

interface SettingsBlockPageProps {
  title: string
  subtitle: string
  children: ReactNode
}

/**
 * Casca comum de todo bloco do hub de Configurações — título/descrição +
 * link "Voltar" pro índice (`/configuracoes`), igual em mobile (navegação
 * de página cheia) e desktop (painel dentro do mestre-detalhe de
 * `SettingsHubLayout`). Evita repetir o mesmo `PageHeader`/`Paper` em cada
 * um dos 7 blocos.
 */
export function SettingsBlockPage({ title, subtitle, children }: SettingsBlockPageProps) {
  return (
    <Box sx={{ ...CONTENT_CONTAINER_SX, flex: 1 }}>
      <PageHeader title={title} subtitle={subtitle} backLink={{ label: 'Voltar para Configurações', to: '/configuracoes' }} />
      <Paper variant="outlined" sx={SECTION_SX}>
        {children}
      </Paper>
    </Box>
  )
}
