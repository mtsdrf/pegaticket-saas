import BlockOutlinedIcon from '@mui/icons-material/BlockOutlined'
import GppMaybeOutlinedIcon from '@mui/icons-material/GppMaybeOutlined'
import { Box, Button, Paper, Stack, Typography } from '@mui/material'
import { useEffect, useState, type ReactNode } from 'react'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'

type GateStatus = 'checking' | 'pending' | 'blocked' | 'verified'

function storageKey(slug: string) {
  return `mk_age_verified_${slug}`
}

/**
 * Confirmação de maioridade (18+) exigida antes de abrir qualquer tela da
 * loja pública — texto padrão conforme legislação (ECA, Lei nº 8.069/1990, e
 * normas do CONAR para publicidade/venda de produtos restritos a maiores de
 * idade). Decisão persiste por loja (`slug`) em localStorage; "não" bloqueia
 * o acesso sem permitir nova tentativa na mesma tela.
 */
export function AgeVerificationGate({ slug, children }: { slug: string; children: ReactNode }) {
  const [status, setStatus] = useState<GateStatus>('checking')

  useEffect(() => {
    const verified = window.localStorage.getItem(storageKey(slug)) === 'true'
    setStatus(verified ? 'verified' : 'pending')
  }, [slug])

  if (status === 'checking') return null

  if (status === 'verified') return <>{children}</>

  const handleConfirm = () => {
    window.localStorage.setItem(storageKey(slug), 'true')
    setStatus('verified')
  }

  return (
    <Box
      sx={{
        minHeight: '100dvh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        px: 2,
        py: 4,
        background:
          'var(--pt-page-background)',
      }}
    >
      <Paper
        elevation={0}
        role={status === 'blocked' ? 'alert' : 'dialog'}
        aria-modal="true"
        aria-labelledby="age-gate-title"
        sx={{
          p: { xs: 3, sm: 4 },
          ...ELEVATED_SURFACE_SX,
          maxWidth: 440,
          width: '100%',
          textAlign: 'center',
          boxShadow: 'var(--pt-shadow-md)',
        }}
      >
        {status === 'blocked' ? (
          <>
            <BlockOutlinedIcon sx={{ fontSize: 42, color: 'var(--pt-danger)', mb: 1.5 }} />
            <Typography id="age-gate-title" sx={{ fontWeight: 700, fontSize: 19, mb: 1 }}>
              Acesso não permitido
            </Typography>
            <Typography sx={{ fontSize: 14, color: 'var(--pt-muted)', lineHeight: 1.6 }}>
              Este site é destinado exclusivamente a maiores de 18 (dezoito) anos, conforme o Estatuto da Criança e
              do Adolescente (Lei nº 8.069/1990) e as normas do CONAR aplicáveis à publicidade e venda de produtos
              restritos a maiores de idade. Não é possível continuar a navegação.
            </Typography>
            <Button
              variant="outlined"
              onClick={() => window.history.back()}
              sx={{ mt: 3, minHeight: 44, borderColor: 'var(--pt-border)', color: 'var(--pt-muted)' }}
            >
              Voltar
            </Button>
          </>
        ) : (
          <>
            <GppMaybeOutlinedIcon sx={{ fontSize: 42, color: 'var(--pt-primary)', mb: 1.5 }} />
            <Typography id="age-gate-title" sx={{ fontWeight: 700, fontSize: 19, mb: 1 }}>
              Confirmação de idade
            </Typography>
            <Typography sx={{ fontSize: 14, color: 'var(--pt-muted)', lineHeight: 1.6, mb: 3 }}>
              Este site pode conter produtos e conteúdos destinados exclusivamente a maiores de 18 (dezoito) anos, em
              conformidade com o Estatuto da Criança e do Adolescente (Lei nº 8.069/1990) e as normas do CONAR para
              publicidade e venda de produtos restritos a maiores de idade. Ao confirmar, você declara, sob as penas
              da lei, ter 18 anos completos ou mais.
            </Typography>
            <Stack spacing={1.25}>
              <Button
                variant="contained"
                onClick={handleConfirm}
                sx={{ minHeight: 44, fontWeight: 600, bgcolor: 'var(--pt-primary)', '&:hover': { bgcolor: 'var(--pt-primary-hover)' } }}
              >
                Sim, tenho 18 anos ou mais
              </Button>
              <Button variant="text" onClick={() => setStatus('blocked')} sx={{ minHeight: 44, color: 'var(--pt-muted)' }}>
                Não tenho 18 anos
              </Button>
            </Stack>
          </>
        )}
      </Paper>
    </Box>
  )
}
