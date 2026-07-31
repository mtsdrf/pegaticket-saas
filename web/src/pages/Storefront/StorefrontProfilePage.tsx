import CreditCardOutlinedIcon from '@mui/icons-material/CreditCardOutlined'
import CreditScoreOutlinedIcon from '@mui/icons-material/CreditScoreOutlined'
import EmailOutlinedIcon from '@mui/icons-material/EmailOutlined'
import FacebookOutlinedIcon from '@mui/icons-material/FacebookOutlined'
import InstagramIcon from '@mui/icons-material/Instagram'
import LocationOnOutlinedIcon from '@mui/icons-material/LocationOnOutlined'
import PaymentsOutlinedIcon from '@mui/icons-material/PaymentsOutlined'
import PixIcon from '@mui/icons-material/Pix'
import PaymentOutlinedIcon from '@mui/icons-material/PaymentOutlined'
import PhoneOutlinedIcon from '@mui/icons-material/PhoneOutlined'
import ScheduleOutlinedIcon from '@mui/icons-material/ScheduleOutlined'
import SearchOffOutlinedIcon from '@mui/icons-material/SearchOffOutlined'
import StarIcon from '@mui/icons-material/Star'
import StorefrontOutlinedIcon from '@mui/icons-material/StorefrontOutlined'
import WhatsAppIcon from '@mui/icons-material/WhatsApp'
import { Avatar, Box, Button, Chip, Paper, Skeleton, Stack, Typography } from '@mui/material'
import type { ReactElement } from 'react'
import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { EmptyState } from '../../components/layout/EmptyState'
import { StoreLocationMap } from '../../components/storefront/StoreLocationMap'
import * as storefrontService from '../../services/storefrontService'
import { PAGE_CONTAINER_SX } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type { StorefrontTenant } from '../../types/storefront'
import { WEEKDAY_LABELS, type DayOfWeek } from '../../types/storeBusinessHours'
import { PAYMENT_METHODS, PAYMENT_METHOD_LABELS, type PaymentMethod } from '../../constants/paymentMethods'
import { formatBusinessHoursLine, isStoreOpenNow } from '../../utils/businessHours'

const WEEKDAY_ORDER: DayOfWeek[] = [0, 1, 2, 3, 4, 5, 6]

const PAYMENT_METHOD_ICONS: Record<PaymentMethod, ReactElement> = {
  cash: <PaymentsOutlinedIcon fontSize="small" />,
  pix: <PixIcon fontSize="small" />,
  credit_card: <CreditCardOutlinedIcon fontSize="small" />,
  debit_card: <CreditScoreOutlinedIcon fontSize="small" />,
}

const SECTION_SX = {
  ...ELEVATED_SURFACE_SX,
  p: { xs: 2, sm: 2.5 },
} as const

function SectionTitle({ icon, children }: { icon: ReactElement; children: string }) {
  return (
    <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: 1.5 }}>
      <Box sx={{ color: 'var(--mk-primary)', display: 'flex' }}>{icon}</Box>
      <Typography sx={{ fontSize: 16, fontWeight: 700 }}>{children}</Typography>
    </Stack>
  )
}

function ProfileSkeleton() {
  return (
    <Stack spacing={2}>
      <Skeleton variant="rounded" height={72} sx={{ borderRadius: 'var(--mk-radius-lg)' }} />
      <Skeleton variant="rounded" height={340} sx={{ borderRadius: 'var(--mk-radius-lg)' }} />
      <Skeleton variant="rounded" height={220} sx={{ borderRadius: 'var(--mk-radius-lg)' }} />
    </Stack>
  )
}

function StoreHeader({ tenant }: { tenant: StorefrontTenant }) {
  const isOpen = isStoreOpenNow(tenant.business_hours)

  return (
    <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
      <Avatar
        src={tenant.logo_url ?? undefined}
        variant="rounded"
        sx={{ ...SOFT_PANEL_SX, width: 52, height: 52 }}
      >
        <StorefrontOutlinedIcon sx={{ color: 'var(--mk-primary)' }} />
      </Avatar>
      <Box sx={{ minWidth: 0 }}>
        <Typography sx={{ fontSize: { xs: 19, sm: 22 }, fontWeight: 700, wordBreak: 'break-word', lineHeight: 1.2 }}>
          {tenant.name}
        </Typography>
        <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap', rowGap: 0.5, mt: 0.5 }}>
          <Chip
            size="small"
            label={isOpen ? 'Aberto agora' : 'Fechado no momento'}
            sx={{
              fontWeight: 700,
              fontSize: 12,
              color: isOpen ? 'var(--mk-success, #1b7a3d)' : 'var(--mk-warning, #a15c00)',
              bgcolor: isOpen
                ? 'color-mix(in srgb, #1b7a3d 14%, transparent)'
                : 'color-mix(in srgb, #a15c00 14%, transparent)',
            }}
          />
          {tenant.ratings_count > 0 && tenant.average_rating !== null && (
            <Stack direction="row" spacing={0.25} sx={{ alignItems: 'center' }}>
              <StarIcon sx={{ fontSize: 15, color: 'var(--mk-warning, #a15c00)' }} />
              <Typography sx={{ fontSize: 12.5, fontWeight: 700 }}>
                {tenant.average_rating.toFixed(1).replace('.', ',')}
              </Typography>
              <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>({tenant.ratings_count})</Typography>
            </Stack>
          )}
        </Stack>
      </Box>
    </Stack>
  )
}

function LocationSection({ tenant }: { tenant: StorefrontTenant }) {
  const hasCoords = tenant.address_lat !== null && tenant.address_lng !== null

  return (
    <Paper elevation={0} sx={SECTION_SX}>
      <SectionTitle icon={<LocationOnOutlinedIcon fontSize="small" />}>Endereço e localização</SectionTitle>

      {hasCoords && (
        <Box sx={{ mb: tenant.address ? 1.5 : 0 }}>
          <StoreLocationMap lat={tenant.address_lat!} lng={tenant.address_lng!} label={tenant.name} />
        </Box>
      )}

      {tenant.address ? (
        <Typography sx={{ fontSize: 14, color: 'var(--mk-text)', wordBreak: 'break-word' }}>
          {tenant.address}
        </Typography>
      ) : (
        <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>
          Esta loja ainda não informou o endereço.
        </Typography>
      )}
    </Paper>
  )
}

function BusinessHoursSection({ tenant }: { tenant: StorefrontTenant }) {
  const todayIndex = new Date().getDay()

  return (
    <Paper elevation={0} sx={SECTION_SX}>
      <SectionTitle icon={<ScheduleOutlinedIcon fontSize="small" />}>Horário de funcionamento</SectionTitle>
      <Stack divider={<Box sx={{ borderBottom: '1px solid var(--mk-border)' }} />}>
        {WEEKDAY_ORDER.map((day) => {
          const shifts = tenant.business_hours.filter((entry) => entry.day_of_week === day)
          const line = formatBusinessHoursLine(shifts)
          const isToday = day === todayIndex
          return (
            <Stack
              key={day}
              direction="row"
              sx={{ justifyContent: 'space-between', alignItems: 'center', gap: 1.5, py: 1 }}
            >
              <Typography sx={{ fontSize: 14, fontWeight: isToday ? 700 : 500, color: 'var(--mk-text)' }}>
                {WEEKDAY_LABELS[day]}
                {isToday && (
                  <Box component="span" sx={{ fontSize: 11.5, color: 'var(--mk-primary)', ml: 0.75, fontWeight: 700 }}>
                    hoje
                  </Box>
                )}
              </Typography>
              <Typography
                sx={{
                  fontSize: 13.5,
                  fontWeight: isToday ? 600 : 500,
                  color: line === 'Fechado' ? 'var(--mk-muted)' : 'var(--mk-text)',
                  textAlign: 'right',
                }}
              >
                {line}
              </Typography>
            </Stack>
          )
        })}
      </Stack>
    </Paper>
  )
}

function PaymentMethodsSection({ tenant }: { tenant: StorefrontTenant }) {
  const methods = PAYMENT_METHODS.filter((method) => tenant.accepted_payment_methods.includes(method))

  return (
    <Paper elevation={0} sx={SECTION_SX}>
      <SectionTitle icon={<PaymentOutlinedIcon fontSize="small" />}>Formas de pagamento</SectionTitle>
      {methods.length > 0 ? (
        <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', rowGap: 1 }}>
          {methods.map((method) => (
            <Chip
              key={method}
              icon={PAYMENT_METHOD_ICONS[method]}
              label={PAYMENT_METHOD_LABELS[method]}
              sx={{
                fontWeight: 600,
                fontSize: 13,
                ...SOFT_PANEL_SX,
                '& .MuiChip-icon': { color: 'var(--mk-primary)' },
              }}
            />
          ))}
        </Stack>
      ) : (
        <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>
          Esta loja ainda não informou as formas de pagamento aceitas.
        </Typography>
      )}
    </Paper>
  )
}

function digitsOnly(value: string) {
  return value.replace(/\D+/g, '')
}

function ContactSection({ tenant }: { tenant: StorefrontTenant }) {
  const contacts = [
    tenant.phone ? { key: 'phone', label: tenant.phone, href: `tel:${digitsOnly(tenant.phone)}`, icon: <PhoneOutlinedIcon fontSize="small" /> } : null,
    tenant.mobile_phone ? { key: 'mobile', label: tenant.mobile_phone, href: `tel:${digitsOnly(tenant.mobile_phone)}`, icon: <PhoneOutlinedIcon fontSize="small" /> } : null,
    tenant.whatsapp ? { key: 'whatsapp', label: tenant.whatsapp, href: `https://wa.me/55${digitsOnly(tenant.whatsapp)}`, icon: <WhatsAppIcon fontSize="small" /> } : null,
    tenant.email ? { key: 'email', label: tenant.email, href: `mailto:${tenant.email}`, icon: <EmailOutlinedIcon fontSize="small" /> } : null,
    tenant.instagram ? { key: 'instagram', label: tenant.instagram, href: `https://instagram.com/${tenant.instagram.replace(/^@/, '')}`, icon: <InstagramIcon fontSize="small" /> } : null,
    tenant.facebook ? { key: 'facebook', label: tenant.facebook, href: tenant.facebook.startsWith('http') ? tenant.facebook : `https://facebook.com/${tenant.facebook.replace(/^\//, '')}`, icon: <FacebookOutlinedIcon fontSize="small" /> } : null,
  ].filter(Boolean) as Array<{ key: string; label: string; href: string; icon: ReactElement }>

  return (
    <Paper elevation={0} sx={SECTION_SX}>
      <SectionTitle icon={<PhoneOutlinedIcon fontSize="small" />}>Contato</SectionTitle>
      {contacts.length > 0 ? (
        <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', rowGap: 1 }}>
          {contacts.map((contact) => (
            <Button key={contact.key} variant="outlined" startIcon={contact.icon} href={contact.href} target={contact.href.startsWith('http') ? '_blank' : undefined} rel="noreferrer">
              {contact.label}
            </Button>
          ))}
        </Stack>
      ) : (
        <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>
          Esta empresa ainda não informou canais de contato públicos.
        </Typography>
      )}
    </Paper>
  )
}

/** "Perfil da loja" (rota pública `/loja/:slug/perfil`) — endereço + mapa, horários e formas de pagamento. */
export function StorefrontProfilePage() {
  const { slug } = useParams<{ slug: string }>()
  const [tenant, setTenant] = useState<StorefrontTenant | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!slug) return
    let cancelled = false
    setIsLoading(true)
    setError(null)
    storefrontService
      .getStorefront(slug)
      .then((result) => {
        if (!cancelled) setTenant(result)
      })
      .catch((err: unknown) => {
        if (!cancelled) setError(getApiErrorMessage(err, 'Não foi possível carregar as informações desta loja agora.'))
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [slug])

  return (
    <Box
      component="main"
      sx={{
        minHeight: '100dvh',
        background:
          'var(--mk-page-background)',
        pb: 4,
      }}
    >
      <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 960, px: { xs: 2, sm: 3 }, py: { xs: 3, sm: 4 } }}>
        {isLoading && <ProfileSkeleton />}

        {!isLoading && error && (
          <EmptyState
            icon={<SearchOffOutlinedIcon sx={{ fontSize: 40, color: 'var(--mk-muted)' }} />}
            title="Loja indisponível"
            description={error}
          />
        )}

        {!isLoading && !error && tenant && (
          <Stack spacing={2}>
            <StoreHeader tenant={tenant} />
            <ContactSection tenant={tenant} />
            <LocationSection tenant={tenant} />
            <BusinessHoursSection tenant={tenant} />
            {tenant.storefront_enabled ? <PaymentMethodsSection tenant={tenant} /> : null}
          </Stack>
        )}
      </Box>
    </Box>
  )
}
