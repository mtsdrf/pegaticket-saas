import ExpandMoreRoundedIcon from '@mui/icons-material/ExpandMoreRounded'
import { Accordion, AccordionDetails, AccordionSummary, Box, Typography } from '@mui/material'
import { useState } from 'react'
import { RevealSection } from '../components/RevealSection'
import { FAQ_ITEMS } from '../data/faq'
import { trackEvent } from '../utils/analytics'

export function Faq() {
  const [expanded, setExpanded] = useState<string | false>(false)

  function handleChange(question: string) {
    return (_: React.SyntheticEvent, isExpanded: boolean) => {
      setExpanded(isExpanded ? question : false)
      if (isExpanded) {
        trackEvent({ name: 'faq_open', question })
      }
    }
  }

  return (
    <Box component="section" id="faq" sx={{ py: { xs: 6, md: 10 }, backgroundColor: 'var(--pt-surface-soft)' }}>
      <Box sx={{ maxWidth: 800, mx: 'auto', px: { xs: 2.5, sm: 4 } }}>
        <RevealSection>
          <Typography component="h2" sx={{ fontSize: { xs: 26, md: 32 }, fontWeight: 700, color: 'var(--pt-text)', mb: 3.5 }}>
            Perguntas frequentes
          </Typography>
        </RevealSection>

        {FAQ_ITEMS.map((item) => (
          <Accordion
            key={item.question}
            expanded={expanded === item.question}
            onChange={handleChange(item.question)}
            disableGutters
            sx={{
              backgroundColor: 'var(--pt-surface)',
              border: '1px solid var(--pt-border)',
              borderRadius: 'var(--pt-radius-md) !important',
              mb: 1.25,
              boxShadow: 'none',
              '&::before': { display: 'none' },
            }}
          >
            <AccordionSummary
              expandIcon={<ExpandMoreRoundedIcon />}
              aria-controls={`faq-panel-${item.question}`}
              id={`faq-header-${item.question}`}
            >
              <Typography sx={{ fontSize: 15, fontWeight: 600, color: 'var(--pt-text)' }}>{item.question}</Typography>
            </AccordionSummary>
            <AccordionDetails>
              <Typography sx={{ fontSize: 14, color: 'var(--pt-muted)', lineHeight: 1.6 }}>{item.answer}</Typography>
            </AccordionDetails>
          </Accordion>
        ))}
      </Box>
    </Box>
  )
}
