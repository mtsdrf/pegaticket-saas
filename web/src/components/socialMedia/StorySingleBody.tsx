import type { StorySingleContent } from '../../types/socialMedia'
import { formatCurrency } from '../../utils/format'

/**
 * Corpo "item único" (produto, cliente do mês, comunicado) — encaixado
 * dentro de `StoryCanvas`. `content.pricing` presente é o sinal de "isto é
 * um produto" (só `ProductDataForm` preenche esse campo) — só nesse caso
 * entra o tratamento de banner mais chamativo (fundo decorativo atrás da
 * foto, faixa "OFERTA" diagonal, selo de desconto). Cliente do
 * mês/comunicado (sem `pricing`) mantêm o visual mais sóbrio original,
 * via `content.value`.
 */
export function StorySingleBody({ content }: { content: StorySingleContent }) {
  const pricing = content.pricing
  const hasOffer = pricing?.offerPrice !== undefined
  const discountPercent =
    hasOffer && pricing!.regularPrice > 0 && pricing!.offerPrice! < pricing!.regularPrice
      ? Math.round((1 - pricing!.offerPrice! / pricing!.regularPrice) * 100)
      : null

  return (
    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', textAlign: 'center', gap: 28, width: '100%' }}>
      {content.imageDataUrl && (
        <div style={{ position: 'relative', width: 640, height: 640, flexShrink: 0, marginBottom: pricing ? 12 : 0 }}>
          {pricing && (
            <div
              style={{
                position: 'absolute',
                inset: -22,
                borderRadius: 76,
                background: 'linear-gradient(135deg, var(--mk-accent), var(--mk-secondary))',
                transform: 'rotate(-5deg)',
              }}
            />
          )}

          <div
            style={{
              position: 'relative',
              width: '100%',
              height: '100%',
              borderRadius: 60,
              overflow: 'hidden',
              border: pricing ? '8px solid var(--mk-surface)' : '1px solid var(--mk-border)',
              boxShadow: pricing ? 'var(--mk-shadow-lg)' : 'var(--mk-shadow-md)',
              background: 'var(--mk-surface-soft)',
            }}
          >
            <img src={content.imageDataUrl} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover', display: 'block' }} />
          </div>

          {hasOffer && (
            <div
              style={{
                position: 'absolute',
                top: 6,
                left: -34,
                transform: 'rotate(-38deg)',
                transformOrigin: 'center',
                background: 'linear-gradient(135deg, var(--mk-danger), #B91C1C)',
                color: '#FFFFFF',
                fontWeight: 800,
                fontSize: 28,
                letterSpacing: 2,
                padding: '10px 56px',
                boxShadow: '0 8px 18px rgba(220, 38, 38, 0.45)',
              }}
            >
              OFERTA
            </div>
          )}

          {discountPercent !== null && (
            <div
              style={{
                position: 'absolute',
                bottom: -26,
                right: -26,
                width: 148,
                height: 148,
                borderRadius: '50%',
                background: 'linear-gradient(135deg, var(--mk-danger), #B91C1C)',
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                border: '6px solid var(--mk-surface)',
                boxShadow: '0 12px 26px rgba(220, 38, 38, 0.5)',
              }}
            >
              <span style={{ color: '#FFFFFF', fontSize: 44, fontWeight: 800, lineHeight: 1 }}>-{discountPercent}%</span>
              <span style={{ color: '#FFFFFF', fontSize: 16, fontWeight: 700, letterSpacing: 1.5 }}>OFF</span>
            </div>
          )}
        </div>
      )}

      {content.eyebrow && (
        <span
          style={{
            display: 'inline-block',
            padding: '8px 22px',
            borderRadius: 999,
            background: 'color-mix(in srgb, var(--mk-accent) 16%, transparent)',
            color: 'var(--mk-primary)',
            fontWeight: 600,
            fontSize: 22,
            letterSpacing: 0.4,
          }}
        >
          {content.eyebrow.toUpperCase()}
        </span>
      )}

      <h2
        style={{
          margin: 0,
          fontSize: pricing ? 64 : 56,
          fontWeight: 800,
          color: 'var(--mk-text)',
          lineHeight: 1.1,
          maxWidth: 860,
          letterSpacing: -0.5,
        }}
      >
        {content.title || 'Sem título'}
      </h2>

      {content.description && (
        <p style={{ margin: 0, fontSize: 28, color: 'var(--mk-muted)', maxWidth: 760, lineHeight: 1.5, whiteSpace: 'pre-wrap' }}>
          {content.description}
        </p>
      )}

      {pricing ? (
        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 14, marginTop: 4 }}>
          {hasOffer && (
            <span style={{ fontSize: 32, color: 'var(--mk-muted)', textDecoration: 'line-through', fontWeight: 600 }}>
              De {formatCurrency(pricing.regularPrice)}
            </span>
          )}

          <div
            style={{
              display: 'flex',
              alignItems: 'baseline',
              gap: 16,
              padding: '28px 60px',
              borderRadius: 32,
              background: hasOffer
                ? 'linear-gradient(135deg, var(--mk-danger), #B91C1C)'
                : 'linear-gradient(135deg, var(--mk-primary), var(--mk-primary-hover))',
              color: '#FFFFFF',
              boxShadow: hasOffer ? '0 16px 34px rgba(220, 38, 38, 0.35)' : '0 16px 34px rgba(15, 61, 94, 0.3)',
            }}
          >
            {hasOffer && <span style={{ fontSize: 26, fontWeight: 700, opacity: 0.85 }}>Por</span>}
            <span style={{ fontSize: 62, fontWeight: 800, letterSpacing: -1.5 }}>
              {formatCurrency(hasOffer ? pricing.offerPrice! : pricing.regularPrice)}
            </span>
          </div>

          {pricing.wholesalePrice !== undefined && (
            <div
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: 14,
                padding: '14px 32px',
                borderRadius: 999,
                background: 'var(--mk-primary)',
                color: '#FFFFFF',
                fontWeight: 700,
                fontSize: 26,
              }}
            >
              <span style={{ background: 'rgba(255,255,255,0.22)', padding: '5px 16px', borderRadius: 999, fontSize: 18, letterSpacing: 1.5 }}>
                ATACADO
              </span>
              <span>
                {pricing.wholesaleMinQuantity ? `a partir de ${pricing.wholesaleMinQuantity} un · ` : ''}
                {formatCurrency(pricing.wholesalePrice)}
              </span>
            </div>
          )}
        </div>
      ) : (
        content.value && (
          <div
            style={{
              marginTop: 8,
              padding: '20px 40px',
              borderRadius: 24,
              background: 'linear-gradient(135deg, var(--mk-primary), var(--mk-primary-hover))',
              color: '#FFFFFF',
              fontWeight: 700,
              fontSize: 44,
            }}
          >
            {content.value}
          </div>
        )
      )}
    </div>
  )
}
