import DownloadOutlinedIcon from '@mui/icons-material/DownloadOutlined'
import IosShareOutlinedIcon from '@mui/icons-material/IosShareOutlined'
import { Alert, Button, FormControlLabel, Stack, Switch } from '@mui/material'
import { useRef, useState } from 'react'
import { StoryCanvas } from '../../../components/socialMedia/StoryCanvas'
import { StoryPreviewStage } from '../../../components/socialMedia/StoryPreviewStage'
import { StoryRankingBody } from '../../../components/socialMedia/StoryRankingBody'
import { StorySingleBody } from '../../../components/socialMedia/StorySingleBody'
import { useStoryExport } from '../../../hooks/useStoryExport'
import { UI_SIZE } from '../../../styles/layoutStandards'
import type { StoryContent, StoryTemplateVariant } from '../../../types/socialMedia'
import { canShareStoryFile, downloadStoryBlob, shareStoryBlob } from '../../../utils/storyExportActions'

interface PreviewStepProps {
  variant: StoryTemplateVariant
  content: StoryContent
  includeLogo: boolean
  onIncludeLogoChange: (value: boolean) => void
  logoDataUrl: string | null
  /** Esconde o toggle inteiro quando a empresa não tem logo cadastrada (não mostrar desabilitado sem explicação). */
  showLogoToggle: boolean
}

/**
 * Etapa 4/5 do wizard: preview em tempo real (o próprio `StoryCanvas` real,
 * escalado por `StoryPreviewStage`) + ações finais. A captura em si
 * (`useStoryExport`) só acontece quando o usuário clica em Baixar/Compartilhar,
 * sempre sobre o nó real (`canvasRef`), nunca sobre o wrapper escalado.
 */
export function PreviewStep({
  variant,
  content,
  includeLogo,
  onIncludeLogoChange,
  logoDataUrl,
  showLogoToggle,
}: PreviewStepProps) {
  const canvasRef = useRef<HTMLDivElement>(null)
  const { isExporting, error, captureBlob } = useStoryExport()
  const [actionError, setActionError] = useState<string | null>(null)
  const canShare = canShareStoryFile()

  async function handleDownload() {
    setActionError(null)
    if (!canvasRef.current) return
    const blob = await captureBlob(canvasRef.current)
    if (blob) downloadStoryBlob(blob)
  }

  async function handleShare() {
    setActionError(null)
    if (!canvasRef.current) return
    const blob = await captureBlob(canvasRef.current)
    if (!blob) return

    try {
      await shareStoryBlob(blob)
    } catch (shareError) {
      // Cancelamento do usuário no share sheet nativo (`AbortError`) não é erro de aplicação.
      if (shareError instanceof DOMException && shareError.name === 'AbortError') return
      setActionError('Não foi possível compartilhar a imagem agora.')
    }
  }

  return (
    <Stack spacing={2.5} sx={{ alignItems: 'center' }}>
      {showLogoToggle && (
        <FormControlLabel
          control={<Switch checked={includeLogo} onChange={(event) => onIncludeLogoChange(event.target.checked)} />}
          label="Incluir logo da empresa"
          sx={{ alignSelf: { xs: 'center', sm: 'flex-start' } }}
        />
      )}

      <StoryPreviewStage>
        <StoryCanvas ref={canvasRef} variant={variant} tenantLogoDataUrl={includeLogo ? logoDataUrl : null}>
          {content.kind === 'single' ? <StorySingleBody content={content} /> : <StoryRankingBody content={content} />}
        </StoryCanvas>
      </StoryPreviewStage>

      {(error || actionError) && (
        <Alert severity="error" variant="outlined" sx={{ width: '100%', maxWidth: 420 }}>
          {error ?? actionError}
        </Alert>
      )}

      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} sx={{ width: '100%', maxWidth: 420 }}>
        <Button
          variant="outlined"
          startIcon={<DownloadOutlinedIcon />}
          onClick={handleDownload}
          disabled={isExporting}
          fullWidth
          sx={{ minHeight: UI_SIZE.control }}
        >
          {isExporting ? 'Gerando…' : 'Baixar imagem'}
        </Button>

        {canShare && (
          <Button
            variant="contained"
            startIcon={<IosShareOutlinedIcon />}
            onClick={handleShare}
            disabled={isExporting}
            fullWidth
            sx={{ minHeight: 44 }}
          >
            Compartilhar
          </Button>
        )}
      </Stack>
    </Stack>
  )
}
