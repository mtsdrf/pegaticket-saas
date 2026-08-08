import CheckCircleOutlineRoundedIcon from '@mui/icons-material/CheckCircleOutlineRounded'
import { Button, Stack, Typography } from '@mui/material'

interface ReceivingSuccessStateProps {
  variant: 'connected' | 'account_created'
  onDone: () => void
}

const COPY: Record<ReceivingSuccessStateProps['variant'], { title: string; description: string }> = {
  connected: {
    title: 'Conta conectada com sucesso',
    description: 'Sua conta PagBank foi conectada. Assim que a verificação for concluída, você poderá vender ingressos pagos.',
  },
  account_created: {
    title: 'Dados enviados com sucesso',
    description:
      'Recebemos seus dados e sua conta está em análise. Assim que ela for aprovada, você poderá vender ingressos pagos. Não é possível garantir um prazo exato para essa análise.',
  },
}

/** Tela final do wizard — nunca promete prazo de análise não garantido pelo PagBank (roadmap seção 9.6). */
export function ReceivingSuccessState({ variant, onDone }: ReceivingSuccessStateProps) {
  const copy = COPY[variant]

  return (
    <Stack spacing={2} sx={{ alignItems: 'center', textAlign: 'center', py: { xs: 3, sm: 4 } }}>
      <CheckCircleOutlineRoundedIcon sx={{ fontSize: 56, color: 'var(--pt-success)' }} />
      <Typography sx={{ fontWeight: 700, fontSize: 19 }}>{copy.title}</Typography>
      <Typography sx={{ fontSize: 14.5, color: 'var(--pt-muted)', maxWidth: 480 }}>{copy.description}</Typography>
      <Button variant="contained" onClick={onDone}>
        Concluir
      </Button>
    </Stack>
  )
}
