import { Box } from '@mui/material'
import { useEffect } from 'react'
import { Faq } from '../sections/Faq'
import { FinalCta } from '../sections/FinalCta'
import { Footer } from '../sections/Footer'
import { Header } from '../sections/Header'
import { Hero } from '../sections/Hero'
import { Modules } from '../sections/Modules'
import { Plans } from '../sections/Plans'
import { Segments } from '../sections/Segments'
import { trackEvent } from '../utils/analytics'

export function LandingPage() {
  useEffect(() => {
    trackEvent({ name: 'landing_view' })
  }, [])

  return (
    <Box component="main" id="conteudo">
      <Header />
      <Hero />
      <Modules />
      <Segments />
      <Plans />
      <Faq />
      <FinalCta />
      <Footer />
    </Box>
  )
}
