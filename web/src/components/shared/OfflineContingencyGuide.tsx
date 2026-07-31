import ContentCopyOutlinedIcon from '@mui/icons-material/ContentCopyOutlined'
import PrintOutlinedIcon from '@mui/icons-material/PrintOutlined'
import WarningAmberOutlinedIcon from '@mui/icons-material/WarningAmberOutlined'
import {
  Alert,
  Box,
  Button,
  Chip,
  Collapse,
  Paper,
  Stack,
  Typography,
} from '@mui/material'
import { useMemo, useState } from 'react'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'

type GuideContext = 'pdv-no-snapshot' | 'balcao-no-snapshot' | 'balcao-conflict'

interface OfflineContingencyGuideProps {
  context: GuideContext
  title?: string
}

interface GuideContent {
  chip: string
  alert: string
  title: string
  body: string
  steps: string[]
  footer: string
}

const CONTENT: Record<GuideContext, GuideContent> = {
  'pdv-no-snapshot': {
    chip: 'Contingência do caixa',
    alert: 'Este caixa não possui base offline válida para continuar vendendo com segurança neste dispositivo.',
    title: 'O que fazer agora',
    body:
      'Quando não houver snapshot válido do PDV, o caminho seguro é parar a operação eletrônica neste aparelho e registrar a contingência manualmente até a conexão voltar.',
    steps: [
      'Pare novas vendas neste dispositivo até a internet voltar ou até um caixa com base offline válida assumir a operação.',
      'Se precisar continuar atendendo, registre os itens e valores em contingência manual da loja.',
      'Não prometa Pix, cartão ou fechamento fiscal offline neste cenário.',
      'Assim que a conexão voltar, atualize a base offline e relance as vendas no PDV antes de reconciliar o caixa.',
    ],
    footer:
      'O objetivo aqui é evitar venda eletrônica ambígua, diferença de caixa ou promessa falsa de pagamento concluído sem internet.',
  },
  'balcao-no-snapshot': {
    chip: 'Contingência do salão',
    alert: 'Este dispositivo não tem base offline suficiente para abrir ou continuar comandas com segurança.',
    title: 'Como seguir sem travar a operação',
    body:
      'Se o salão inteiro perder internet e este aparelho não tiver snapshot local, a operação deve degradar para contingência assistida, sem tentar simular sincronização.',
    steps: [
      'Use comanda manual ou registro físico temporário para não perder o pedido.',
      'Concentre os lançamentos digitais em outro dispositivo que ainda tenha base offline válida, se existir.',
      'Avise a equipe que a cozinha só receberá os itens quando a conexão voltar e os lançamentos forem sincronizados.',
      'Assim que normalizar, atualize a base e relance ou reconcilie os pedidos do período manualmente.',
    ],
    footer:
      'No MVP atual, offline no garçom protege contra perder dados locais, mas não substitui contingência operacional quando o salão inteiro fica sem internet.',
  },
  'balcao-conflict': {
    chip: 'Revisão obrigatória',
    alert: 'Esta comanda entrou em conflito com outro dispositivo e precisa de revisão manual antes de novos lançamentos sensíveis.',
    title: 'Como resolver o conflito',
    body:
      'O conflito normalmente significa que outro aparelho já abriu, alterou ou fechou a mesma mesa/comanda enquanto este dispositivo estava offline.',
    steps: [
      'Volte para o mapa de mesas e atualize a base offline antes de continuar.',
      'Confira qual comanda ficou canônica no servidor e compare com os itens pendentes deste dispositivo.',
      'Lance manualmente apenas o que realmente ainda não entrou na comanda correta.',
      'Só retome preparo, entrega ou fechamento depois que a revisão terminar.',
    ],
    footer:
      'A regra do sistema é priorizar integridade operacional: melhor exigir revisão do que duplicar item, fechar conta errada ou mascarar divergência.',
  },
}

export function OfflineContingencyGuide({ context, title }: OfflineContingencyGuideProps) {
  const [expanded, setExpanded] = useState(false)
  const content = CONTENT[context]

  const plainText = useMemo(
    () =>
      [content.title, content.body, ...content.steps.map((step, index) => `${index + 1}. ${step}`), content.footer].join(
        '\n',
      ),
    [content],
  )

  async function handleCopy() {
    try {
      await navigator.clipboard.writeText(plainText)
    } catch {
      // Sem bloquear a operação por falha de clipboard.
    }
  }

  return (
    <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 2.25 }}>
      <Stack spacing={1.5}>
        <Stack
          direction={{ xs: 'column', sm: 'row' }}
          spacing={1}
          sx={{ alignItems: { xs: 'flex-start', sm: 'center' }, justifyContent: 'space-between' }}
        >
          <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
            <Box
              sx={{
                width: 38,
                height: 38,
                borderRadius: '12px',
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
                bgcolor: 'rgba(255, 186, 66, 0.18)',
                color: '#8A5A00',
                flexShrink: 0,
              }}
            >
              <WarningAmberOutlinedIcon fontSize="small" />
            </Box>
            <Box>
              <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
                {title ?? content.title}
              </Typography>
              <Chip size="small" color="warning" variant="outlined" label={content.chip} sx={{ mt: 0.5 }} />
            </Box>
          </Stack>

          <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap' }}>
            <Button size="small" variant="outlined" startIcon={<ContentCopyOutlinedIcon fontSize="small" />} onClick={() => void handleCopy()}>
              Copiar orientação
            </Button>
            <Button size="small" variant="outlined" startIcon={<PrintOutlinedIcon fontSize="small" />} onClick={() => window.print()}>
              Imprimir
            </Button>
          </Stack>
        </Stack>

        <Alert severity="warning" variant="outlined">
          {content.alert}
        </Alert>

        <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>{content.body}</Typography>

        <Button variant="text" onClick={() => setExpanded((value) => !value)} sx={{ alignSelf: 'flex-start', px: 0 }}>
          {expanded ? 'Ocultar passo a passo' : 'Mostrar passo a passo'}
        </Button>

        <Collapse in={expanded}>
          <Stack spacing={1.1}>
            {content.steps.map((step, index) => (
              <Stack key={step} direction="row" spacing={1.25} sx={{ alignItems: 'flex-start' }}>
                <Box
                  sx={{
                    width: 22,
                    height: 22,
                    borderRadius: '999px',
                    display: 'inline-flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    fontSize: 12,
                    fontWeight: 800,
                    bgcolor: 'color-mix(in srgb, var(--mk-primary) 16%, white)',
                    color: 'var(--mk-primary-strong)',
                    flexShrink: 0,
                    mt: '1px',
                  }}
                >
                  {index + 1}
                </Box>
                <Typography sx={{ fontSize: 13.5 }}>{step}</Typography>
              </Stack>
            ))}
          </Stack>
        </Collapse>

        <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>{content.footer}</Typography>
      </Stack>
    </Paper>
  )
}
