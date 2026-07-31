import type { StoryRankingContent } from '../../types/socialMedia'

/** Corpo "lista top-N" (produtos mais vendidos, bairros com mais pedidos) — ranking numerado, encaixado dentro de `StoryCanvas`. */
export function StoryRankingBody({ content }: { content: StoryRankingContent }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', width: '100%', gap: 24 }}>
      {content.eyebrow && (
        <span style={{ textAlign: 'center', fontWeight: 600, fontSize: 22, letterSpacing: 0.4, color: 'var(--mk-primary)' }}>
          {content.eyebrow.toUpperCase()}
        </span>
      )}

      <h2 style={{ margin: '0 0 8px', textAlign: 'center', fontSize: 48, fontWeight: 700, color: 'var(--mk-text)' }}>
        {content.title}
      </h2>

      <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
        {content.items.map((item, index) => (
          <div
            key={`${item.label}-${index}`}
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: 22,
              padding: '22px 28px',
              borderRadius: 28,
              background: 'var(--mk-surface)',
              border: '1px solid var(--mk-border)',
            }}
          >
            <div
              style={{
                width: 56,
                height: 56,
                borderRadius: '50%',
                flexShrink: 0,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                background: 'var(--mk-primary)',
                color: '#FFFFFF',
                fontWeight: 700,
                fontSize: 26,
              }}
            >
              {index + 1}
            </div>

            <div style={{ flex: 1, minWidth: 0 }}>
              <div
                style={{
                  fontSize: 30,
                  fontWeight: 600,
                  color: 'var(--mk-text)',
                  overflow: 'hidden',
                  textOverflow: 'ellipsis',
                  whiteSpace: 'nowrap',
                }}
              >
                {item.label}
              </div>
              {item.secondaryValue && <div style={{ fontSize: 22, color: 'var(--mk-muted)' }}>{item.secondaryValue}</div>}
            </div>

            <div style={{ fontSize: 30, fontWeight: 700, color: 'var(--mk-text)', flexShrink: 0 }}>{item.primaryValue}</div>
          </div>
        ))}
      </div>
    </div>
  )
}
