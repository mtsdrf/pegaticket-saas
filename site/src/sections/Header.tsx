import MenuRoundedIcon from '@mui/icons-material/MenuRounded'
import { AppBar, Box, Button, Drawer, IconButton, List, ListItemButton, Stack, Toolbar } from '@mui/material'
import { useState } from 'react'
import { Logo } from '../components/Logo'
import { APP_URL } from '../constants/app'
import { trackEvent } from '../utils/analytics'

/**
 * Site multi-página (ver vite.config.ts): "Módulos"/"Segmentos" só existem
 * na home, por isso usam caminho absoluto com hash (`/#modulos`) — funciona
 * tanto na home (scroll direto) quanto em /precos (navega e rola). "Planos"
 * agora é uma página própria; "Perguntas frequentes" existe nas duas
 * páginas, por isso fica como âncora relativa.
 */
const NAV_LINKS = [
  { label: 'Módulos', href: '/#modulos' },
  { label: 'Segmentos', href: '/#segmentos' },
  { label: 'Planos', href: '/precos' },
  { label: 'Perguntas frequentes', href: '#faq' },
]

export function Header() {
  const [menuOpen, setMenuOpen] = useState(false)

  return (
    <AppBar
      position="sticky"
      elevation={0}
      component="header"
      sx={{ backgroundColor: 'var(--mk-surface)', backdropFilter: 'saturate(180%) blur(6px)' }}
    >
      <Toolbar sx={{ minHeight: { xs: 60, sm: 68 }, gap: 2 }}>
        <Box component="a" href="/" sx={{ display: 'flex', alignItems: 'center', textDecoration: 'none' }}>
          <Logo size={30} />
        </Box>

        <Stack
          component="nav"
          direction="row"
          spacing={3}
          sx={{ display: { xs: 'none', md: 'flex' }, ml: 4, flexGrow: 1 }}
          aria-label="Navegação principal"
        >
          {NAV_LINKS.map((link) => (
            <Box
              key={link.href}
              component="a"
              href={link.href}
              sx={{
                color: 'var(--mk-text)',
                textDecoration: 'none',
                fontWeight: 500,
                fontSize: 14.5,
                '&:hover': { color: 'var(--mk-primary)' },
              }}
            >
              {link.label}
            </Box>
          ))}
        </Stack>

        <Box sx={{ flexGrow: { xs: 1, md: 0 } }} />

        <Button
          variant="contained"
          color="primary"
          href={APP_URL}
          onClick={() => trackEvent({ name: 'hero_cta_click', target: 'primary' })}
          sx={{ display: { xs: 'none', sm: 'inline-flex' } }}
        >
          Entrar no painel
        </Button>

        <IconButton
          aria-label="Abrir menu"
          onClick={() => setMenuOpen(true)}
          sx={{ display: { xs: 'inline-flex', md: 'none' } }}
        >
          <MenuRoundedIcon />
        </IconButton>
      </Toolbar>

      <Drawer anchor="right" open={menuOpen} onClose={() => setMenuOpen(false)}>
        <List sx={{ width: 260, pt: 2 }} aria-label="Navegação principal (mobile)">
          {NAV_LINKS.map((link) => (
            <ListItemButton key={link.href} component="a" href={link.href} onClick={() => setMenuOpen(false)}>
              {link.label}
            </ListItemButton>
          ))}
          <Box sx={{ px: 2, pt: 2 }}>
            <Button
              fullWidth
              variant="contained"
              color="primary"
              href={APP_URL}
              onClick={() => trackEvent({ name: 'hero_cta_click', target: 'primary' })}
            >
              Entrar no painel
            </Button>
          </Box>
        </List>
      </Drawer>
    </AppBar>
  )
}
