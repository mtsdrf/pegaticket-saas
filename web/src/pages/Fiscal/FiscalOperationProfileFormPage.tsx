import { useEffect, useState, type FormEvent } from 'react'
import { FormControlLabel, MenuItem, Stack, Switch, TextField } from '@mui/material'
import { useNavigate, useParams } from 'react-router-dom'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import * as fiscalOperationProfileService from '../../services/fiscalOperationProfileService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import {
  FISCAL_DOCUMENT_TYPE_LABELS,
  FISCAL_OPERATION_NATURE_LABELS,
  type FiscalDocumentType,
  type FiscalOperationNature,
  type FiscalOperationProfilePayload,
  type FiscalOperationProfileScope,
} from '../../types/fiscalOperationProfile'

function parseCsvList(value: string): string[] | undefined {
  const items = value
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)

  return items.length > 0 ? items : undefined
}

export function FiscalOperationProfileFormPage() {
  const navigate = useNavigate()
  const { uuid } = useParams<{ uuid: string }>()
  const isEditMode = Boolean(uuid)

  const [name, setName] = useState('')
  const [operationNature, setOperationNature] = useState<FiscalOperationNature>('sale')
  const [documentType, setDocumentType] = useState<FiscalDocumentType>('nfce')
  const [defaultCfop, setDefaultCfop] = useState('')
  const [orderOrigin, setOrderOrigin] = useState('')
  const [fulfillmentType, setFulfillmentType] = useState('')
  const [destinationType, setDestinationType] = useState('')
  const [description, setDescription] = useState('')
  const [isActive, setIsActive] = useState(true)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [isLoading, setIsLoading] = useState(isEditMode)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  useEffect(() => {
    if (!uuid) return

    fiscalOperationProfileService
      .getFiscalOperationProfile(uuid)
      .then((profile) => {
        setName(profile.name)
        setOperationNature(profile.operation_nature)
        setDocumentType(profile.document_type)
        setDefaultCfop(profile.default_cfop ?? '')
        setOrderOrigin(profile.scope?.order_origin?.join(', ') ?? '')
        setFulfillmentType(profile.scope?.fulfillment_type?.join(', ') ?? '')
        setDestinationType(profile.scope?.destination_type?.join(', ') ?? '')
        setDescription(profile.description ?? '')
        setIsActive(profile.is_active)
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar o perfil fiscal agora.')))
      .finally(() => setIsLoading(false))
  }, [uuid])

  function buildScope(): FiscalOperationProfileScope | null {
    const scope: FiscalOperationProfileScope = {}
    const originList = parseCsvList(orderOrigin)
    const fulfillmentList = parseCsvList(fulfillmentType)
    const destinationList = parseCsvList(destinationType)
    if (originList) scope.order_origin = originList as FiscalOperationProfileScope['order_origin']
    if (fulfillmentList) scope.fulfillment_type = fulfillmentList as FiscalOperationProfileScope['fulfillment_type']
    if (destinationList) scope.destination_type = destinationList as FiscalOperationProfileScope['destination_type']
    return Object.keys(scope).length > 0 ? scope : null
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFieldErrors({})
    setIsSubmitting(true)

    const payload: FiscalOperationProfilePayload = {
      name: name.trim(),
      operation_nature: operationNature,
      document_type: documentType,
      default_cfop: defaultCfop.trim() || null,
      scope: buildScope(),
      description: description.trim() || null,
      is_active: isActive,
    }

    try {
      if (uuid) {
        await fiscalOperationProfileService.updateFiscalOperationProfile(uuid, payload)
      } else {
        await fiscalOperationProfileService.createFiscalOperationProfile(payload)
      }
      navigate('/configuracoes/perfis-fiscais')
    } catch (error) {
      if (error instanceof ApiRequestError) {
        setFieldErrors(error.errors)
      }
      setLoadError(getApiErrorMessage(error, 'Não foi possível salvar o perfil fiscal agora.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backTo="/configuracoes/perfis-fiscais"
      backLabel="Perfis fiscais"
      title={isEditMode ? 'Editar perfil fiscal' : 'Novo perfil fiscal'}
      subtitle="Defina natureza da operação, documento preferido e CFOP base para orientar o fluxo fiscal da empresa."
      breadcrumbs={[{ label: 'Configurações', to: '/configuracoes' }, { label: 'Perfis fiscais', to: '/configuracoes/perfis-fiscais' }, { label: isEditMode ? 'Editar' : 'Novo' }]}
      isLoadingRecord={isLoading}
      loadError={loadError}
      isSubmitting={isSubmitting}
      onSubmit={(event) => void handleSubmit(event)}
      submitLabel={isEditMode ? 'Salvar alterações' : 'Criar perfil'}
    >
      <TextField
        label="Nome do perfil"
        value={name}
        onChange={(event) => setName(event.target.value)}
        error={Boolean(fieldErrors.name)}
        helperText={fieldErrors.name?.[0] ?? 'Ex.: Venda balcão NFC-e, Serviço NFS-e, Transferência interna.'}
        fullWidth
        required
      />

      <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
        <TextField
          select
          label="Natureza da operação"
          value={operationNature}
          onChange={(event) => setOperationNature(event.target.value as FiscalOperationNature)}
          error={Boolean(fieldErrors.operation_nature)}
          helperText={fieldErrors.operation_nature?.[0]}
          fullWidth
        >
          {Object.entries(FISCAL_OPERATION_NATURE_LABELS).map(([value, label]) => (
            <MenuItem key={value} value={value}>{label}</MenuItem>
          ))}
        </TextField>

        <TextField
          select
          label="Documento fiscal preferido"
          value={documentType}
          onChange={(event) => setDocumentType(event.target.value as FiscalDocumentType)}
          error={Boolean(fieldErrors.document_type)}
          helperText={fieldErrors.document_type?.[0]}
          fullWidth
        >
          {Object.entries(FISCAL_DOCUMENT_TYPE_LABELS).map(([value, label]) => (
            <MenuItem key={value} value={value}>{label}</MenuItem>
          ))}
        </TextField>

        <TextField
          label="CFOP base"
          value={defaultCfop}
          onChange={(event) => setDefaultCfop(event.target.value.slice(0, 10))}
          error={Boolean(fieldErrors.default_cfop)}
          helperText={fieldErrors.default_cfop?.[0] ?? 'Ex.: 5102'}
          fullWidth
        />
      </Stack>

      <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
        <TextField
          label="Origens do pedido"
          value={orderOrigin}
          onChange={(event) => setOrderOrigin(event.target.value.toLowerCase())}
          error={Boolean(fieldErrors['scope.order_origin'])}
          helperText={fieldErrors['scope.order_origin']?.[0] ?? 'Ex.: pdv, counter, storefront'}
          fullWidth
        />
        <TextField
          label="Tipos de entrega"
          value={fulfillmentType}
          onChange={(event) => setFulfillmentType(event.target.value.toLowerCase())}
          error={Boolean(fieldErrors['scope.fulfillment_type'])}
          helperText={fieldErrors['scope.fulfillment_type']?.[0] ?? 'Ex.: delivery, pickup'}
          fullWidth
        />
        <TextField
          label="Tipos de destinatário"
          value={destinationType}
          onChange={(event) => setDestinationType(event.target.value.toLowerCase())}
          error={Boolean(fieldErrors['scope.destination_type'])}
          helperText={fieldErrors['scope.destination_type']?.[0] ?? 'Ex.: consumer_final, business'}
          fullWidth
        />
      </Stack>

      <TextField
        label="Descrição"
        value={description}
        onChange={(event) => setDescription(event.target.value)}
        error={Boolean(fieldErrors.description)}
        helperText={fieldErrors.description?.[0] ?? 'Use para orientar o time fiscal e o contador sobre quando este perfil deve ser aplicado.'}
        multiline
        minRows={3}
        fullWidth
      />

      <FormControlLabel
        control={<Switch checked={isActive} onChange={(event) => setIsActive(event.target.checked)} />}
        label="Perfil ativo"
      />
    </CrudFormShell>
  )
}
