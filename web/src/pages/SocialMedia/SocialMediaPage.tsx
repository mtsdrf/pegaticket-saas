import ArrowBackIcon from '@mui/icons-material/ArrowBack'
import { Box, Button, Step, StepLabel, Stepper } from '@mui/material'
import { useState } from 'react'
import { PageHeader } from '../../components/layout/PageHeader'
import { useAuth } from '../../hooks/useAuth'
import { useRemoteImageDataUrl } from '../../hooks/useRemoteImageDataUrl'
import { PAGE_CONTAINER_SX, UI_SIZE } from '../../styles/layoutStandards'
import type { SocialContentTypeKey, StoryContent, StoryTemplateVariant } from '../../types/socialMedia'
import { AnnouncementDataForm } from './dataForms/AnnouncementDataForm'
import { ProductDataForm } from './dataForms/ProductDataForm'
import { TopClientDataForm } from './dataForms/TopClientDataForm'
import { TopNeighborhoodsDataForm } from './dataForms/TopNeighborhoodsDataForm'
import { TopProductsDataForm } from './dataForms/TopProductsDataForm'
import { ContentTypeStep } from './steps/ContentTypeStep'
import { PreviewStep } from './steps/PreviewStep'
import { TemplateStep } from './steps/TemplateStep'

type WizardStep = 'type' | 'template' | 'data' | 'preview'

const STEP_ORDER: WizardStep[] = ['type', 'template', 'data', 'preview']
const STEP_LABELS: Record<WizardStep, string> = {
  type: 'Tipo',
  template: 'Modelo',
  data: 'Dados',
  preview: 'Pré-visualização',
}

function isContentValid(content: StoryContent | null): boolean {
  if (!content) return false
  return content.kind === 'single' ? content.title.trim() !== '' : content.items.length > 0
}

/**
 * Tela "Redes Sociais" — gera imagens de story (1080×1920) com a identidade
 * visual Maskats a partir de dados já existentes (produto, análises) ou
 * texto livre, renderizadas 100% client-side (`html-to-image`, ver
 * `hooks/useStoryExport.ts`). Fluxo em etapas dentro da mesma página: tipo de
 * conteúdo → template → dados → preview/ações finais.
 */
export function SocialMediaPage() {
  const { activeTenant } = useAuth()
  const [step, setStep] = useState<WizardStep>('type')
  const [contentType, setContentType] = useState<SocialContentTypeKey | null>(null)
  const [template, setTemplate] = useState<StoryTemplateVariant | null>(null)
  const [includeLogo, setIncludeLogo] = useState(true)
  const [resolvedContent, setResolvedContent] = useState<StoryContent | null>(null)

  const { dataUrl: tenantLogoDataUrl } = useRemoteImageDataUrl(activeTenant?.logo_url)
  const hasTenantLogo = Boolean(activeTenant?.logo_url)

  const activeStepIndex = STEP_ORDER.indexOf(step)

  function handleSelectContentType(key: SocialContentTypeKey) {
    setContentType(key)
    setResolvedContent(null)
    setStep('template')
  }

  function handleSelectTemplate(variant: StoryTemplateVariant) {
    setTemplate(variant)
    setStep('data')
  }

  function goBack() {
    const currentIndex = STEP_ORDER.indexOf(step)
    if (currentIndex > 0) setStep(STEP_ORDER[currentIndex - 1])
  }

  return (
    <Box sx={{ ...PAGE_CONTAINER_SX }}>
      <PageHeader
        title="Redes sociais"
        subtitle="Gere imagens de story prontas pra compartilhar no WhatsApp e Instagram."
      />

      <Stepper activeStep={activeStepIndex} alternativeLabel sx={{ mb: 3.5 }}>
        {STEP_ORDER.map((key) => (
          <Step key={key}>
            <StepLabel>{STEP_LABELS[key]}</StepLabel>
          </Step>
        ))}
      </Stepper>

      {step !== 'type' && (
        <Button startIcon={<ArrowBackIcon />} onClick={goBack} color="inherit" sx={{ mb: 2, ml: -1, minHeight: UI_SIZE.control }}>
          Voltar
        </Button>
      )}

      {step === 'type' && <ContentTypeStep onSelect={handleSelectContentType} />}

      {step === 'template' && <TemplateStep onSelect={handleSelectTemplate} />}

      {step === 'data' && contentType && (
        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2.5 }}>
          {contentType === 'product' && <ProductDataForm onContentChange={setResolvedContent} />}
          {contentType === 'top_client' && <TopClientDataForm onContentChange={setResolvedContent} />}
          {contentType === 'top_products' && <TopProductsDataForm onContentChange={setResolvedContent} />}
          {contentType === 'top_neighborhoods' && <TopNeighborhoodsDataForm onContentChange={setResolvedContent} />}
          {contentType === 'announcement' && <AnnouncementDataForm onContentChange={setResolvedContent} />}

          <Button
            variant="contained"
            disabled={!isContentValid(resolvedContent)}
            onClick={() => setStep('preview')}
            sx={{ alignSelf: 'flex-start', minHeight: UI_SIZE.control }}
          >
            Ver pré-visualização
          </Button>
        </Box>
      )}

      {step === 'preview' && template && resolvedContent && (
        <PreviewStep
          variant={template}
          content={resolvedContent}
          includeLogo={includeLogo}
          onIncludeLogoChange={setIncludeLogo}
          logoDataUrl={tenantLogoDataUrl}
          showLogoToggle={hasTenantLogo}
        />
      )}
    </Box>
  )
}
