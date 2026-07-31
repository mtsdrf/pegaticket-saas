import ArrowBackIcon from '@mui/icons-material/ArrowBack'
import { Alert, Box, Button, CircularProgress, Paper, Stack, Typography } from '@mui/material'
import { useEffect, useState } from 'react'
import { Link as RouterLink } from 'react-router-dom'
import { Logo } from '../../components/ui/Logo'
import { ThemeFab } from '../../components/ThemeFab'
import * as legalDocumentService from '../../services/legalDocumentService'
import { PAGE_CONTAINER_SX } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type { LegalDocument, LegalDocumentType } from '../../types/legal'

const TITLES: Record<LegalDocumentType, string> = {
  terms: 'Termos de Uso',
  privacy: 'Política de Privacidade',
}

interface LegalDocumentPageProps {
  type: LegalDocumentType
}

/**
 * Página pública (sem autenticação) que exibe a versão vigente de um
 * documento legal. `content` vem como texto simples/markdown do backend e é
 * renderizado pré-formatado (sem lib de markdown — não há uma no projeto).
 * Mobile-first: base para telas pequenas, enriquecida em `sm+`.
 */
export function LegalDocumentPage({ type }: LegalDocumentPageProps) {
  const [document, setDocument] = useState<LegalDocument | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  const title = TITLES[type]

  function load() {
    setIsLoading(true)
    setLoadError(null)
    legalDocumentService
      .getLegalDocument(type)
      .then(setDocument)
      .catch((error: unknown) => {
        setLoadError(getApiErrorMessage(error, 'Não foi possível carregar este documento agora.'))
      })
      .finally(() => setIsLoading(false))
  }

  useEffect(() => {
    load()
    // Recarrega ao trocar de tipo (caso a mesma instância seja reutilizada).
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [type])

  return (
    <Box
      component="main"
      sx={{
        minHeight: '100dvh',
        bgcolor: 'var(--mk-bg)',
        px: { xs: 2, sm: 3 },
        py: { xs: 3, sm: 5 },
      }}
    >
      <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 760 }}>
        <Stack
          direction="row"
          sx={{ alignItems: 'center', justifyContent: 'space-between', mb: { xs: 2.5, sm: 3.5 } }}
        >
          <Box component={RouterLink} to="/" sx={{ display: 'inline-flex', textDecoration: 'none' }} aria-label="Ir para o início">
            <Logo size={40} />
          </Box>
          <Button component={RouterLink} to="/cadastro" startIcon={<ArrowBackIcon />} color="inherit" size="small">
            Voltar ao cadastro
          </Button>
        </Stack>

        <Paper
          elevation={0}
          sx={{
            p: { xs: 2.5, sm: 4 },
            ...ELEVATED_SURFACE_SX,
          }}
        >
          <Typography component="h1" sx={{ fontSize: { xs: 22, sm: 26 }, fontWeight: 700, mb: 0.5 }}>
            {title}
          </Typography>

          {document && (
            <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)', mb: 3 }}>
              Versão {document.version}
              {document.published_at
                ? ` · publicada em ${new Date(document.published_at).toLocaleDateString('pt-BR')}`
                : ''}
            </Typography>
          )}

          {isLoading && (
            <Box sx={{ display: 'flex', justifyContent: 'center', py: 6 }}>
              <CircularProgress size={28} />
            </Box>
          )}

          {!isLoading && loadError && (
            <Alert
              severity="error"
              action={
                <Button color="inherit" size="small" onClick={load}>
                  Tentar novamente
                </Button>
              }
            >
              {loadError}
            </Alert>
          )}

          {!isLoading && !loadError && document && (
            <Typography
              component="div"
              sx={{
                fontSize: 14.5,
                lineHeight: 1.7,
                color: 'var(--mk-text)',
                whiteSpace: 'pre-wrap',
                wordBreak: 'break-word',
              }}
            >
              {document.content}
            </Typography>
          )}
        </Paper>
      </Box>
      <ThemeFab />
    </Box>
  )
}
