import SaveOutlinedIcon from '@mui/icons-material/SaveOutlined'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  MenuItem,
  Paper,
  Stack,
  Switch,
  TextField,
  Typography,
} from '@mui/material'
import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { PageHeader } from '../../components/layout/PageHeader'
import {
  getFiscalOperationProfile,
  updateFiscalOperationProfile,
} from '../../services/fiscalOperationProfileService'
import { FORM_GRID_2_SX, SECTION_CARD_PADDING_SX } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import {
  FISCAL_DOCUMENT_TYPE_LABELS,
  type FiscalDocumentType,
  type FiscalOperationProfilePayload,
} from '../../types/fiscalOperationProfile'

export function FiscalOperationProfileFormPage() {
  const navigate = useNavigate()
  const { uuid = '' } = useParams()
  const [isLoading, setIsLoading] = useState(true)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [formError, setFormError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [name, setName] = useState('')
  const [operationNature, setOperationNature] = useState('sale')
  const [documentType, setDocumentType] = useState<FiscalDocumentType>('nfce')
  const [defaultCfop, setDefaultCfop] = useState('')
  const [description, setDescription] = useState('')
  const [isActive, setIsActive] = useState(true)

  useEffect(() => {
    let cancelled = false

    setIsLoading(true)
    setLoadError(null)

    getFiscalOperationProfile(uuid)
      .then((profile) => {
        if (cancelled) return
        setName(profile.name)
        setOperationNature(profile.operation_nature)
        setDocumentType(profile.document_type)
        setDefaultCfop(profile.default_cfop ?? '')
        setDescription(profile.description ?? '')
        setIsActive(profile.is_active)
      })
      .catch((error) => {
        if (cancelled) return
        setLoadError(getApiErrorMessage(error, 'Não foi possível carregar o perfil fiscal agora.'))
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [uuid])

  async function handleSubmit() {
    setIsSubmitting(true)
    setFormError(null)
    setFieldErrors({})

    const payload: FiscalOperationProfilePayload = {
      name,
      operation_nature: operationNature,
      document_type: documentType,
      default_cfop: defaultCfop.trim() || null,
      description: description.trim() || null,
      is_active: isActive,
    }

    try {
      await updateFiscalOperationProfile(uuid, payload)
      navigate('/configuracoes/perfis-fiscais')
    } catch (error) {
      if (typeof error === 'object' && error && 'errors' in error) {
        setFieldErrors((error as { errors?: Record<string, string[]> }).errors ?? {})
      }
      setFormError(getApiErrorMessage(error, 'Não foi possível salvar o perfil fiscal agora.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Box sx={{ width: '100%', maxWidth: 1080 }}>
      <PageHeader
        title="Editar perfil fiscal"
        subtitle="Ajuste o perfil operacional que será usado como base nas decisões fiscais futuras."
        backLink={{ label: 'Voltar para Perfis fiscais', to: '/configuracoes/perfis-fiscais' }}
      />

      <Paper variant="outlined" sx={{ ...SECTION_CARD_PADDING_SX, ...ELEVATED_SURFACE_SX }}>
        {isLoading ? (
          <Stack sx={{ minHeight: 240, alignItems: 'center', justifyContent: 'center' }}>
            <CircularProgress size={28} />
          </Stack>
        ) : loadError ? (
          <Alert severity="error">{loadError}</Alert>
        ) : (
          <Stack spacing={2.5}>
            {formError ? <Alert severity="error">{formError}</Alert> : null}

            <Box sx={FORM_GRID_2_SX}>
              <TextField
                label="Nome do perfil"
                value={name}
                onChange={(event) => setName(event.target.value)}
                error={Boolean(fieldErrors.name)}
                helperText={fieldErrors.name?.[0] ?? 'Use um nome claro para a operação fiscal.'}
                fullWidth
              />

              <TextField
                select
                label="Tipo de documento"
                value={documentType}
                onChange={(event) => setDocumentType(event.target.value as FiscalDocumentType)}
                error={Boolean(fieldErrors.document_type)}
                helperText={fieldErrors.document_type?.[0]}
                fullWidth
              >
                {Object.entries(FISCAL_DOCUMENT_TYPE_LABELS).map(([value, label]) => (
                  <MenuItem key={value} value={value}>
                    {label}
                  </MenuItem>
                ))}
              </TextField>

              <TextField
                label="Natureza da operação"
                value={operationNature}
                onChange={(event) => setOperationNature(event.target.value)}
                error={Boolean(fieldErrors.operation_nature)}
                helperText={fieldErrors.operation_nature?.[0] ?? 'Ex.: sale, return, transfer.'}
                fullWidth
              />

              <TextField
                label="CFOP base"
                value={defaultCfop}
                onChange={(event) => setDefaultCfop(event.target.value)}
                error={Boolean(fieldErrors.default_cfop)}
                helperText={fieldErrors.default_cfop?.[0] ?? 'Código principal da operação.'}
                fullWidth
              />
            </Box>

            <TextField
              label="Descrição"
              value={description}
              onChange={(event) => setDescription(event.target.value)}
              error={Boolean(fieldErrors.description)}
              helperText={fieldErrors.description?.[0] ?? 'Contexto interno para quem mantém o módulo fiscal.'}
              fullWidth
              multiline
              minRows={3}
            />

            <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
              <Switch checked={isActive} onChange={(event) => setIsActive(event.target.checked)} />
              <Box>
                <Typography sx={{ fontWeight: 700 }}>Perfil ativo</Typography>
                <Typography sx={{ color: 'var(--pt-muted)' }}>
                  Perfis inativos ficam preservados para histórico, mas saem da operação normal.
                </Typography>
              </Box>
            </Stack>

            <Stack direction={{ xs: 'column-reverse', sm: 'row' }} spacing={1.5} sx={{ justifyContent: 'flex-end' }}>
              <Button color="inherit" onClick={() => navigate('/configuracoes/perfis-fiscais')} disabled={isSubmitting}>
                Cancelar
              </Button>
              <Button
                variant="contained"
                startIcon={<SaveOutlinedIcon />}
                onClick={() => void handleSubmit()}
                disabled={isSubmitting}
              >
                {isSubmitting ? 'Salvando…' : 'Salvar perfil'}
              </Button>
            </Stack>
          </Stack>
        )}
      </Paper>
    </Box>
  )
}
