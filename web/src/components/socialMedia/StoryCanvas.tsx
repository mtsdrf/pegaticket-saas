import { forwardRef, type CSSProperties, type ReactNode } from 'react'
import { Logo } from '../ui/Logo'
import { STORY_HEIGHT, STORY_WIDTH } from './storyDimensions'
import type { StoryTemplateVariant } from '../../types/socialMedia'

/**
 * Paleta oficial light da Maskats, fixada localmente (nunca `prefers-color-scheme`
 * nem `data-theme` herdado) — um story exportado pra WhatsApp/Instagram precisa
 * de aparência de marca consistente independente do tema (claro/escuro) que o
 * usuário estiver usando no app no momento da geração. As variáveis `--mk-*`
 * continuam sendo usadas em todo componente filho (conforme design-system.md),
 * só o VALOR é sobrescrito neste nó raiz, sombreando a cascata herdada.
 */
const LIGHT_TOKENS: CSSProperties = {
  '--mk-bg': '#F8F8FA',
  '--mk-surface': '#FFFFFF',
  '--mk-surface-soft': '#EEF3F8',
  '--mk-primary': '#005BDA',
  '--mk-primary-hover': '#004EC0',
  '--mk-secondary': '#0A6BFF',
  '--mk-accent': '#003F9A',
  '--mk-text': '#10213E',
  '--mk-muted': '#64748B',
  '--mk-border': '#D8E0EA',
  '--mk-success': '#16A34A',
  '--mk-warning': '#F59E0B',
  '--mk-danger': '#DC2626',
  '--mk-info': '#0284C7',
  '--mk-shadow-md': '0 10px 24px rgba(0, 63, 154, 0.10)',
  '--mk-shadow-lg': '0 22px 54px rgba(0, 42, 120, 0.14)',
} as CSSProperties

/**
 * Com logo da empresa: cabeçalho vira só a logo da empresa (maior, sem
 * nome — pedido explícito do usuário), no lugar onde a marca Maskats
 * ficava. Sem logo da empresa (toggle desligado ou tenant sem logo
 * cadastrada): comportamento original, marca Maskats por variante.
 */
function CanvasHeader({ variant, tenantLogoDataUrl }: { variant: StoryTemplateVariant; tenantLogoDataUrl?: string | null }) {
  if (tenantLogoDataUrl) {
    return (
      <div style={{ display: 'flex', justifyContent: 'center', marginBottom: 12 }}>
        <img
          src={tenantLogoDataUrl}
          alt=""
          style={{
            width: 140,
            height: 140,
            borderRadius: 28,
            objectFit: 'cover',
            display: 'block',
            boxShadow: 'var(--mk-shadow-md)',
            border: '1px solid var(--mk-border)',
          }}
        />
      </div>
    )
  }

  if (variant === 'wordmark') {
    return (
      <div style={{ display: 'flex', justifyContent: 'center', marginBottom: 12 }}>
        <Logo variant="full" size={104} textSize={58} />
      </div>
    )
  }

  if (variant === 'border') {
    return (
      <div style={{ display: 'flex', justifyContent: 'center', marginBottom: 12 }}>
        <Logo variant="mark" size={84} />
      </div>
    )
  }

  // logo_only: mark discreta num canto, sem wordmark (ver brand-guidelines.md).
  return (
    <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
      <div style={{ opacity: 0.92 }}>
        <Logo variant="mark" size={56} />
      </div>
    </div>
  )
}

/**
 * Marca Maskats, deslocada pro rodapé (canto inferior direito) só quando a
 * logo da empresa assume o topo — mantém exatamente o mesmo tamanho que
 * tinha lá em cada variante, só muda de posição.
 */
function CanvasFooter({ variant, tenantLogoDataUrl }: { variant: StoryTemplateVariant; tenantLogoDataUrl?: string | null }) {
  if (!tenantLogoDataUrl) return null

  return (
    <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 20 }}>
      <div style={{ opacity: variant === 'logo_only' ? 0.92 : 1 }}>
        {variant === 'wordmark' && <Logo variant="full" size={104} textSize={58} />}
        {variant === 'border' && <Logo variant="mark" size={84} />}
        {variant === 'logo_only' && <Logo variant="mark" size={56} />}
      </div>
    </div>
  )
}

interface StoryCanvasProps {
  variant: StoryTemplateVariant
  children: ReactNode
  /** `null`/ausente mantém a marca Maskats no topo (toggle "Incluir logo da empresa" desligado ou sem logo cadastrada). Presente: logo da empresa assume o topo, marca Maskats vai pro rodapé. */
  tenantLogoDataUrl?: string | null
}

/**
 * Template do story em tamanho final (1080×1920) — a moldura/marca Maskats
 * (3 variantes fixas) fica aqui; o conteúdo em si é outro componente
 * encaixado (`StorySingleBody`/`StoryRankingBody`), passado como `children`.
 * Sempre renderizado em tamanho real, nunca escalado por CSS — quem escala
 * pra caber na tela é `StoryPreviewStage`, que envolve este nó num wrapper
 * com `transform: scale(...)`; a captura (`useStoryExport`) aponta pra este
 * nó diretamente, nunca pro wrapper escalado.
 */
export const StoryCanvas = forwardRef<HTMLDivElement, StoryCanvasProps>(function StoryCanvas(
  { variant, children, tenantLogoDataUrl },
  ref,
) {
  const isBorder = variant === 'border'

  return (
    <div
      ref={ref}
      style={{
        ...LIGHT_TOKENS,
        width: STORY_WIDTH,
        height: STORY_HEIGHT,
        position: 'relative',
        boxSizing: 'border-box',
        fontFamily: "'Inter', system-ui, sans-serif",
        background: isBorder
          ? 'linear-gradient(155deg, var(--mk-primary) 0%, var(--mk-accent) 100%)'
          : 'linear-gradient(165deg, var(--mk-bg) 0%, var(--mk-surface-soft) 100%)',
      }}
    >
      <div
        style={{
          position: 'absolute',
          inset: isBorder ? 28 : 0,
          background: 'var(--mk-surface-soft)',
          borderRadius: isBorder ? 48 : 0,
          boxShadow: isBorder ? 'var(--mk-shadow-lg)' : 'none',
          display: 'flex',
          flexDirection: 'column',
          boxSizing: 'border-box',
          padding: '72px 64px',
        }}
      >
        <CanvasHeader variant={variant} tenantLogoDataUrl={tenantLogoDataUrl} />

        <div
          style={{
            flex: 1,
            minHeight: 0,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '32px 0',
          }}
        >
          {children}
        </div>

        <CanvasFooter variant={variant} tenantLogoDataUrl={tenantLogoDataUrl} />
      </div>
    </div>
  )
})
