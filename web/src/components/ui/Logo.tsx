interface LogoProps {
  variant?: 'full' | 'mark'
  size?: number
  className?: string
  /** Sobre superfícies coloridas (ex.: painel de marca do login), aplica um fundo claro atrás do símbolo pra garantir contraste. */
  tone?: 'brand' | 'light'
  /** Tamanho do texto "PegaTicket" (variant="full") — sem valor, usa `size * 0.68`. */
  textSize?: number
}

export function Logo({ variant = 'full', size = 28, className, tone = 'brand', textSize }: LogoProps) {
  const textColor = tone === 'light' ? '#FFFFFF' : 'var(--pt-text)'

  const mark = (
    <span
      style={
        tone === 'light'
          ? {
              display: 'inline-flex',
              alignItems: 'center',
              justifyContent: 'center',
              padding: size * 0.14,
              borderRadius: size * 0.28,
              background: 'rgba(255, 255, 255, 0.94)',
            }
          : { display: 'inline-flex' }
      }
    >
      <img
        src="/logo.png"
        alt="PegaTicket"
        width={size}
        height={size}
        style={{ display: 'block', width: size, height: size, objectFit: 'contain' }}
      />
    </span>
  )

  if (variant === 'mark') {
    return <span className={className}>{mark}</span>
  }

  return (
    <span
      className={['pt-logo', className].filter(Boolean).join(' ')}
      style={{ display: 'inline-flex', alignItems: 'center', gap: '0.6rem' }}
    >
      {mark}
      <span className="pt-wordmark" style={{ fontSize: textSize ?? size * 0.68, color: textColor, fontWeight: 600 }}>
        PegaTicket
      </span>
    </span>
  )
}
