import CampaignOutlinedIcon from '@mui/icons-material/CampaignOutlined'
import {
  Badge,
  Box,
  Chip,
  Divider,
  IconButton,
  Menu,
  Skeleton,
  Stack,
  Tooltip,
  Typography,
} from '@mui/material'
import { useEffect, useState } from 'react'
import { STORAGE_KEYS } from '../constants/storage'
import * as releaseNoteService from '../services/releaseNoteService'
import { SOFT_PANEL_SX } from '../styles/surfaces'
import type { ReleaseNote } from '../types/releaseNote'
import { formatDateBR } from '../utils/format'

const MENU_WIDTH = 360

function getLastSeenAt(): string | null {
  return localStorage.getItem(STORAGE_KEYS.releaseNotesLastSeenAt)
}

function countUnread(notes: ReleaseNote[], lastSeenAt: string | null): number {
  if (!lastSeenAt) return notes.length
  return notes.filter((note) => note.published_at && note.published_at > lastSeenAt).length
}

/**
 * "Novidades" (roadmap A1.6) — sino com badge de não-lidas, acessível de
 * qualquer tela do app principal (`AppLayout`). Leitura via `GET
 * /release-notes` (qualquer usuário autenticado, sem perm dedicada); "lida"
 * é só client-side (`localStorage`, sem endpoint de leitura no backend) —
 * abrir o menu marca a mais recente publicada como vista.
 */
export function ReleaseNotesMenu() {
  const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null)
  const [notes, setNotes] = useState<ReleaseNote[] | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [unreadCount, setUnreadCount] = useState(0)
  const open = Boolean(anchorEl)

  useEffect(() => {
    releaseNoteService
      .listReleaseNotes()
      .then((data) => {
        setNotes(data)
        setUnreadCount(countUnread(data, getLastSeenAt()))
      })
      .catch(() => {
        // Silencioso de propósito: "novidades" é informativo, nunca deve
        // bloquear ou poluir a tela principal com erro de rede.
        setLoadError('Não foi possível carregar as novidades agora.')
      })
      .finally(() => setIsLoading(false))
  }, [])

  function handleOpen(event: React.MouseEvent<HTMLElement>) {
    setAnchorEl(event.currentTarget)
    if (notes && notes.length > 0) {
      const mostRecent = notes.reduce<string | null>((latest, note) => {
        if (!note.published_at) return latest
        return !latest || note.published_at > latest ? note.published_at : latest
      }, null)
      if (mostRecent) {
        localStorage.setItem(STORAGE_KEYS.releaseNotesLastSeenAt, mostRecent)
        setUnreadCount(0)
      }
    }
  }

  return (
    <>
      <Tooltip title="Novidades">
        <IconButton aria-label="Ver novidades da plataforma" onClick={handleOpen}>
          <Badge badgeContent={unreadCount} color="error" max={9}>
            <CampaignOutlinedIcon />
          </Badge>
        </IconButton>
      </Tooltip>

      <Menu
        anchorEl={anchorEl}
        open={open}
        onClose={() => setAnchorEl(null)}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
        transformOrigin={{ vertical: 'top', horizontal: 'right' }}
        slotProps={{ paper: { sx: { width: MENU_WIDTH, maxWidth: '92vw', maxHeight: '70vh' } } }}
      >
        <Typography sx={{ fontWeight: 700, fontSize: 15, px: 2, py: 1.5 }}>Novidades</Typography>
        <Divider />

        <Box sx={{ px: 2, py: 1.5 }}>
          {isLoading && (
            <Stack spacing={1.5}>
              <Skeleton variant="text" width="80%" />
              <Skeleton variant="text" width="60%" />
            </Stack>
          )}

          {!isLoading && loadError && (
            <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>{loadError}</Typography>
          )}

          {!isLoading && !loadError && notes && notes.length === 0 && (
            <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>
              Nenhuma novidade publicada até o momento.
            </Typography>
          )}

          {!isLoading && !loadError && notes && notes.length > 0 && (
            <Stack spacing={2} divider={<Divider />}>
              {notes.map((note) => (
                <Box key={note.uuid}>
                  <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: 0.5, flexWrap: 'wrap' }}>
                    <Typography sx={{ fontWeight: 600, fontSize: 14, color: 'var(--mk-text)' }}>
                      {note.title}
                    </Typography>
                    {note.version && (
                      <Chip
                        label={note.version}
                        size="small"
                        sx={{ height: 20, fontSize: 11, ...SOFT_PANEL_SX }}
                      />
                    )}
                  </Stack>
                  <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)', whiteSpace: 'pre-line' }}>
                    {note.body}
                  </Typography>
                  {note.published_at && (
                    <Typography sx={{ fontSize: 11.5, color: 'var(--mk-muted)', mt: 0.5 }}>
                      {formatDateBR(note.published_at)}
                    </Typography>
                  )}
                </Box>
              ))}
            </Stack>
          )}
        </Box>
      </Menu>
    </>
  )
}
