import LocationOnOutlinedIcon from '@mui/icons-material/LocationOnOutlined'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Paper,
  Skeleton,
  Stack,
  Typography,
} from '@mui/material'
import { useEffect, useState } from 'react'
import { ClientAddressFields, type ClientAddressValues } from '../../components/client/ClientAddressFields'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import * as portalAddressService from '../../services/portalAddressService'
import * as storefrontLocationService from '../../services/storefrontLocationService'
import { getApiErrorMessage } from '../../types/api'
import type { Bairro, Cidade, Estado } from '../../types/location'
import type { PortalAddress } from '../../types/portal'
import { PortalShell } from './PortalShell'

function LoadingSkeleton() {
  return (
    <Stack spacing={1.5}>
      {[0, 1].map((index) => (
        <Skeleton key={index} variant="rounded" height={132} sx={{ borderRadius: 'var(--mk-radius-xl)' }} />
      ))}
    </Stack>
  )
}

function EmptyState() {
  return (
    <Paper
      elevation={0}
      sx={{ p: 4, textAlign: 'center', ...ELEVATED_SURFACE_SX }}
    >
      <LocationOnOutlinedIcon sx={{ fontSize: 40, color: 'var(--mk-muted)', mb: 1.5 }} />
      <Typography sx={{ fontWeight: 600, fontSize: 16, mb: 0.75 }}>Nenhum endereço por aqui ainda</Typography>
      <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>
        Ao fazer um pedido em uma loja, o endereço informado aparece aqui para você editar quando precisar.
      </Typography>
    </Paper>
  )
}

function formatAddressLine(address: PortalAddress): string {
  const e = address.endereco
  if (!e) return 'Endereço ainda não informado.'
  const parts = [
    [e.logradouro, e.numero].filter(Boolean).join(', '),
    e.complemento,
    e.bairro_name,
    [e.cidade_name, e.estado_name].filter(Boolean).join(' - '),
    e.cep,
  ].filter((part) => part && part.trim())
  return parts.join(' • ')
}

const EMPTY_ADDRESS: ClientAddressValues = {
  estado_uuid: '',
  cidade_uuid: '',
  bairro_uuid: '',
  logradouro: '',
  numero: '',
  complemento: '',
  cep: '',
}

function EditAddressDialog({
  address,
  onClose,
  onSaved,
}: {
  address: PortalAddress
  onClose: () => void
  onSaved: (updated: PortalAddress) => void
}) {
  const [values, setValues] = useState<ClientAddressValues>(EMPTY_ADDRESS)
  const [estados, setEstados] = useState<Estado[]>([])
  const [cidades, setCidades] = useState<Cidade[]>([])
  const [bairros, setBairros] = useState<Bairro[]>([])
  const [isLoadingForm, setIsLoadingForm] = useState(true)
  const [isLoadingCidades, setIsLoadingCidades] = useState(false)
  const [isLoadingBairros, setIsLoadingBairros] = useState(false)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  // Pré-carrega a cascata Estado→Cidade→Bairro já posicionada nos valores
  // atuais do endereço, para os autocompletes exibirem a seleção corrente.
  useEffect(() => {
    let cancelled = false
    const e = address.endereco
    setIsLoadingForm(true)
    ;(async () => {
      try {
        const estadosList = await storefrontLocationService.getStorefrontEstados()
        if (cancelled) return
        setEstados(estadosList)

        let cidadesList: Cidade[] = []
        if (e?.estado_uuid) {
          cidadesList = await storefrontLocationService.getStorefrontCidades(e.estado_uuid)
          if (cancelled) return
          setCidades(cidadesList)
        }

        let bairrosList: Bairro[] = []
        if (e?.cidade_uuid) {
          bairrosList = await storefrontLocationService.getStorefrontBairros(e.cidade_uuid)
          if (cancelled) return
          setBairros(bairrosList)
        }

        setValues({
          estado_uuid: e?.estado_uuid ?? '',
          cidade_uuid: e?.cidade_uuid ?? '',
          bairro_uuid: e?.bairro_uuid ?? '',
          logradouro: e?.logradouro ?? '',
          numero: e?.numero ?? '',
          complemento: e?.complemento ?? '',
          cep: e?.cep ?? '',
        })
      } catch {
        if (!cancelled) setFormError('Não foi possível carregar as opções de endereço agora.')
      } finally {
        if (!cancelled) setIsLoadingForm(false)
      }
    })()
    return () => {
      cancelled = true
    }
  }, [address])

  async function handleEstadoChange(estadoUuid: string) {
    setValues((current) => ({ ...current, estado_uuid: estadoUuid, cidade_uuid: '', bairro_uuid: '' }))
    setCidades([])
    setBairros([])
    if (!estadoUuid) return
    setIsLoadingCidades(true)
    try {
      setCidades(await storefrontLocationService.getStorefrontCidades(estadoUuid))
    } catch {
      setCidades([])
    } finally {
      setIsLoadingCidades(false)
    }
  }

  async function handleCidadeChange(cidadeUuid: string) {
    setValues((current) => ({ ...current, cidade_uuid: cidadeUuid, bairro_uuid: '' }))
    setBairros([])
    if (!cidadeUuid) return
    setIsLoadingBairros(true)
    try {
      setBairros(await storefrontLocationService.getStorefrontBairros(cidadeUuid))
    } catch {
      setBairros([])
    } finally {
      setIsLoadingBairros(false)
    }
  }

  function handleTextChange(field: 'logradouro' | 'numero' | 'complemento' | 'cep', value: string) {
    setValues((current) => ({ ...current, [field]: value }))
  }

  async function handleSubmit() {
    const errors: Record<string, string[]> = {}
    if (!values.logradouro.trim()) errors.logradouro = ['Campo obrigatório.']
    if (!values.bairro_uuid) errors.bairro_uuid = ['Campo obrigatório.']
    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors)
      return
    }
    setFieldErrors({})
    setFormError(null)
    setIsSubmitting(true)
    try {
      const updated = await portalAddressService.updatePortalAddress(address.client_uuid, {
        logradouro: values.logradouro.trim(),
        numero: values.numero.trim() || null,
        complemento: values.complemento.trim() || null,
        cep: values.cep.trim() || null,
        bairro_uuid: values.bairro_uuid,
      })
      onSaved({ ...address, endereco: updated })
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível salvar o endereço agora. Tente novamente.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Dialog open onClose={onClose} maxWidth="sm" fullWidth>
      <DialogTitle sx={{ fontSize: 17, fontWeight: 700 }}>Editar endereço — {address.tenant_name}</DialogTitle>
      <DialogContent>
        {formError && (
          <Alert severity="error" variant="outlined" sx={{ mb: 2 }} role="alert">
            {formError}
          </Alert>
        )}
        {isLoadingForm ? (
          <Stack sx={{ alignItems: 'center', py: 4 }}>
            <CircularProgress size={26} />
          </Stack>
        ) : (
          <ClientAddressFields
            values={values}
            estados={estados}
            cidades={cidades}
            bairros={bairros}
            isLoadingCidades={isLoadingCidades}
            isLoadingBairros={isLoadingBairros}
            fieldErrors={fieldErrors}
            onEstadoChange={(value) => void handleEstadoChange(value)}
            onCidadeChange={(value) => void handleCidadeChange(value)}
            onBairroChange={(value) => setValues((current) => ({ ...current, bairro_uuid: value }))}
            onTextChange={handleTextChange}
          />
        )}
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={isSubmitting}>
          Cancelar
        </Button>
        <Button variant="contained" onClick={() => void handleSubmit()} disabled={isSubmitting || isLoadingForm}>
          {isSubmitting ? 'Salvando…' : 'Salvar'}
        </Button>
      </DialogActions>
    </Dialog>
  )
}

function AddressCard({ address, onEdit }: { address: PortalAddress; onEdit: () => void }) {
  return (
    <Paper
      elevation={0}
      sx={{ p: 1.75, ...ELEVATED_SURFACE_SX }}
    >
      <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'flex-start', gap: 1.5 }}>
        <Box sx={{ minWidth: 0 }}>
          <Typography sx={{ fontSize: 14, fontWeight: 700, mb: 0.5 }}>{address.tenant_name}</Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)', wordBreak: 'break-word' }}>
            {formatAddressLine(address)}
          </Typography>
        </Box>
        <Button size="small" variant="outlined" onClick={onEdit} sx={{ flexShrink: 0, minHeight: 40 }}>
          Editar
        </Button>
      </Stack>
    </Paper>
  )
}

/** "Meus endereços" (roadmap Loja) — edita o endereço vinculado de cada loja onde o cliente comprou. */
export function PortalAddressesPage() {
  const [addresses, setAddresses] = useState<PortalAddress[] | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [editing, setEditing] = useState<PortalAddress | null>(null)

  useEffect(() => {
    setIsLoading(true)
    setErrorMessage(null)
    portalAddressService
      .listPortalAddresses()
      .then((result) => setAddresses(result))
      .catch((error: unknown) => {
        setErrorMessage(getApiErrorMessage(error, 'Não foi possível carregar seus endereços agora. Tente novamente.'))
      })
      .finally(() => setIsLoading(false))
  }, [])

  function handleSaved(updated: PortalAddress) {
    setAddresses((current) =>
      current ? current.map((item) => (item.client_uuid === updated.client_uuid ? updated : item)) : current,
    )
    setEditing(null)
  }

  return (
    <PortalShell title="Meus endereços" subtitle="Endereço de entrega em cada loja onde você já comprou.">
      {isLoading && <LoadingSkeleton />}

      {!isLoading && errorMessage && (
        <Alert severity="error" variant="outlined" role="alert">
          {errorMessage}
        </Alert>
      )}

      {!isLoading && !errorMessage && addresses && addresses.length === 0 && <EmptyState />}

      {!isLoading && !errorMessage && addresses && addresses.length > 0 && (
        <Stack spacing={1.25}>
          {addresses.map((address) => (
            <AddressCard key={address.client_uuid} address={address} onEdit={() => setEditing(address)} />
          ))}
        </Stack>
      )}

      {editing && <EditAddressDialog address={editing} onClose={() => setEditing(null)} onSaved={handleSaved} />}
    </PortalShell>
  )
}
