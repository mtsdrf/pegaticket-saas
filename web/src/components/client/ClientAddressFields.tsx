import MyLocationOutlinedIcon from '@mui/icons-material/MyLocationOutlined'
import { Box, Button, CircularProgress, InputAdornment, Stack, TextField, Typography } from '@mui/material'
import { LocalAutocomplete } from '../crud/LocalAutocomplete'
import { FORM_GRID_2_SX, FORM_GRID_3_SX, UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'
import type { Bairro, Cidade, Estado } from '../../types/location'

export interface ClientAddressValues {
  estado_uuid: string
  cidade_uuid: string
  bairro_uuid: string
  logradouro: string
  numero: string
  complemento: string
  cep: string
}

interface ClientAddressFieldsProps {
  values: ClientAddressValues
  estados: Estado[]
  cidades: Cidade[]
  bairros: Bairro[]
  isLoadingCidades: boolean
  isLoadingBairros: boolean
  fieldErrors: Record<string, string[]>
  onEstadoChange: (uuid: string) => void
  onCidadeChange: (uuid: string) => void
  onBairroChange: (uuid: string) => void
  onTextChange: (field: 'logradouro' | 'numero' | 'complemento' | 'cep', value: string) => void
  onUseCurrentLocation?: () => void
  isLocating?: boolean
  /** Rótulo do botão de localização — default serve o cadastro de cliente do staff; checkout da loja usa um texto voltado a "não sei meu CEP". */
  locationButtonLabel?: string
  /** Spinner no campo CEP enquanto a busca automática (ViaCEP) está em andamento — quem dispara a busca é o componente pai, ver `StorefrontCheckoutPage`. */
  isCepLoading?: boolean
  /**
   * CEP primeiro (com "usar minha localização" logo abaixo dele), antes de
   * Estado/Cidade/Bairro/Logradouro — faz sentido só onde o CEP dispara
   * autopreenchimento em cascata (`StorefrontCheckoutPage`, via ViaCEP).
   * Cadastro de cliente do staff não tem essa busca automática, então
   * mantém a ordem original (default `false`) — não é uma mudança de
   * comportamento pra ele, só de rótulo/posição do botão de localização.
   */
  cepFirst?: boolean
}

export function ClientAddressFields({
  values,
  estados,
  cidades,
  bairros,
  isLoadingCidades,
  isLoadingBairros,
  fieldErrors,
  onEstadoChange,
  onCidadeChange,
  onBairroChange,
  onTextChange,
  onUseCurrentLocation,
  isLocating = false,
  locationButtonLabel = 'Usar minha localização atual',
  isCepLoading = false,
  cepFirst = false,
}: ClientAddressFieldsProps) {
  const cepField = (
    <TextField
      label="CEP"
      value={values.cep}
      onChange={(event) => onTextChange('cep', event.target.value)}
      error={Boolean(fieldErrors.cep)}
      helperText={fieldErrors.cep?.[0]}
      inputMode="numeric"
      fullWidth={cepFirst}
      slotProps={{
        htmlInput: { maxLength: 20 },
        input: {
          endAdornment: isCepLoading ? (
            <InputAdornment position="end">
              <CircularProgress size={16} />
            </InputAdornment>
          ) : undefined,
        },
      }}
    />
  )

  const locationButton = onUseCurrentLocation ? (
    <Button
      size="small"
      variant="outlined"
      startIcon={isLocating ? <CircularProgress size={14} /> : <MyLocationOutlinedIcon />}
      disabled={isLocating}
      onClick={onUseCurrentLocation}
      sx={{ minHeight: UI_SIZE.control, borderRadius: UI_RADIUS.md }}
    >
      {isLocating ? 'Localizando…' : locationButtonLabel}
    </Button>
  ) : null

  const estadoCidadeBairroGrid = (
    <Box sx={{ ...FORM_GRID_3_SX, mb: 2 }}>
      <LocalAutocomplete
        label="Estado"
        required
        options={estados}
        value={estados.find((estado) => estado.uuid === values.estado_uuid) ?? null}
        onChange={(estado) => onEstadoChange(estado?.uuid ?? '')}
        getOptionLabel={(estado) => `${estado.name} (${estado.uf})`}
        getOptionKey={(estado) => estado.uuid}
        error={Boolean(fieldErrors.estado_uuid)}
        helperText={fieldErrors.estado_uuid?.[0]}
      />

      <LocalAutocomplete
        label="Cidade"
        required
        disabled={!values.estado_uuid || isLoadingCidades}
        loading={isLoadingCidades}
        options={cidades}
        value={cidades.find((cidade) => cidade.uuid === values.cidade_uuid) ?? null}
        onChange={(cidade) => onCidadeChange(cidade?.uuid ?? '')}
        getOptionLabel={(cidade) => cidade.name}
        getOptionKey={(cidade) => cidade.uuid}
        error={Boolean(fieldErrors.cidade_uuid)}
        helperText={fieldErrors.cidade_uuid?.[0]}
      />

      <LocalAutocomplete
        label="Bairro"
        required
        disabled={!values.cidade_uuid || isLoadingBairros}
        loading={isLoadingBairros}
        options={bairros}
        value={bairros.find((bairro) => bairro.uuid === values.bairro_uuid) ?? null}
        onChange={(bairro) => onBairroChange(bairro?.uuid ?? '')}
        getOptionLabel={(bairro) => bairro.name}
        getOptionKey={(bairro) => bairro.uuid}
        error={Boolean(fieldErrors.bairro_uuid)}
        helperText={fieldErrors.bairro_uuid?.[0]}
      />
    </Box>
  )

  const complementoField = (
    <TextField
      label="Complemento"
      value={values.complemento}
      onChange={(event) => onTextChange('complemento', event.target.value)}
      error={Boolean(fieldErrors.complemento)}
      helperText={fieldErrors.complemento?.[0]}
      fullWidth
      slotProps={{ htmlInput: { maxLength: 255 } }}
    />
  )

  if (cepFirst) {
    return (
      <Box>
        <Typography sx={{ fontSize: 13, fontWeight: 600, color: 'var(--mk-muted)', textTransform: 'uppercase', letterSpacing: 0.4, mb: 1.5 }}>
          Endereço
        </Typography>

        <Box sx={{ mb: locationButton ? 1 : 2 }}>{cepField}</Box>
        {locationButton && <Box sx={{ mb: 2 }}>{locationButton}</Box>}

        {estadoCidadeBairroGrid}

        <Box sx={{ ...FORM_GRID_2_SX, mb: 2 }}>
          <TextField
            label="Logradouro"
            value={values.logradouro}
            onChange={(event) => onTextChange('logradouro', event.target.value)}
            error={Boolean(fieldErrors.logradouro)}
            helperText={fieldErrors.logradouro?.[0]}
            required
            slotProps={{ htmlInput: { maxLength: 255 } }}
          />
          <TextField
            label="Número"
            value={values.numero}
            onChange={(event) => onTextChange('numero', event.target.value)}
            error={Boolean(fieldErrors.numero)}
            helperText={fieldErrors.numero?.[0]}
            slotProps={{ htmlInput: { maxLength: 20 } }}
          />
        </Box>

        {complementoField}
      </Box>
    )
  }

  return (
    <Box>
      <Stack
        direction="row"
        spacing={1}
        sx={{ alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', mb: 1.5, rowGap: 1 }}
      >
        <Typography sx={{ fontSize: 13, fontWeight: 600, color: 'var(--mk-muted)', textTransform: 'uppercase', letterSpacing: 0.4 }}>
          Endereço
        </Typography>
        {locationButton}
      </Stack>

      {estadoCidadeBairroGrid}

      <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr)' }, mb: 2 }}>
        <TextField
          label="Logradouro"
          value={values.logradouro}
          onChange={(event) => onTextChange('logradouro', event.target.value)}
          error={Boolean(fieldErrors.logradouro)}
          helperText={fieldErrors.logradouro?.[0]}
          required
          slotProps={{ htmlInput: { maxLength: 255 } }}
        />
        <TextField
          label="Número"
          value={values.numero}
          onChange={(event) => onTextChange('numero', event.target.value)}
          error={Boolean(fieldErrors.numero)}
          helperText={fieldErrors.numero?.[0]}
          slotProps={{ htmlInput: { maxLength: 20 } }}
        />
        {cepField}
      </Box>

      {complementoField}
    </Box>
  )
}
