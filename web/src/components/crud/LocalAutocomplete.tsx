import { Autocomplete, CircularProgress, TextField, type SxProps, type Theme } from '@mui/material'

interface LocalAutocompleteProps<T> {
  label: string
  value: T | null
  onChange: (value: T | null) => void
  /** Lista já carregada por inteiro (não paginada) — o filtro é o `filterOptions` padrão do MUI (contains, sem acento-sensibilidade extra). */
  options: T[]
  getOptionLabel: (option: T) => string
  getOptionKey: (option: T) => string
  placeholder?: string
  required?: boolean
  error?: boolean
  helperText?: string
  disabled?: boolean
  fullWidth?: boolean
  size?: 'small' | 'medium'
  sx?: SxProps<Theme>
  /** Ex.: opções dependem de outro campo (estado→cidade→bairro) ainda em carregamento — mostra spinner no campo, mesmo padrão do `<Select>` anterior. */
  loading?: boolean
}

/**
 * Autocomplete pra listas já carregadas inteiras na memória do componente pai
 * (estado/cidade/bairro, tipo/categoria de produto, grupo, role, local de
 * estoque, usuário, dia ideal/período ideal, tenant, plano, functionality) —
 * mesmo contrato de `AsyncAutocomplete` (`value`/`onChange`/`getOptionLabel`/
 * `getOptionKey`/`error`/`helperText`), mas sem busca assíncrona: `options`
 * já vem pronta via prop e o filtro é o `filterOptions` padrão do MUI. Troca
 * direta pra `<Select>` de escolher um item de uma lista que cresce — usar
 * `AsyncAutocomplete` em vez deste quando a lista vem paginada de uma API.
 * "Vazio/não selecionado" (equivalente ao antigo `<MenuItem value=""><em>
 * Selecione</em></MenuItem>`) é só `value={null}` — o próprio Autocomplete já
 * mostra o botão de limpar quando não é obrigatório.
 */
export function LocalAutocomplete<T>({
  label,
  value,
  onChange,
  options,
  getOptionLabel,
  getOptionKey,
  placeholder,
  required,
  error,
  helperText,
  disabled,
  fullWidth,
  size = 'small',
  sx,
  loading,
}: LocalAutocompleteProps<T>) {
  return (
    <Autocomplete
      size={size}
      sx={sx}
      fullWidth={fullWidth}
      disabled={disabled}
      value={value}
      onChange={(_event, newValue) => onChange(newValue)}
      options={options}
      getOptionLabel={getOptionLabel}
      getOptionKey={getOptionKey}
      isOptionEqualToValue={(option, val) => getOptionKey(option) === getOptionKey(val)}
      noOptionsText="Nenhuma opção encontrada"
      renderInput={(params) => (
        <TextField
          {...params}
          label={label}
          placeholder={placeholder}
          required={required}
          error={error}
          helperText={helperText}
          slotProps={{
            ...params.slotProps,
            input: {
              ...params.slotProps.input,
              endAdornment: (
                <>
                  {loading ? <CircularProgress color="inherit" size={16} /> : null}
                  {params.slotProps.input.endAdornment}
                </>
              ),
            },
          }}
        />
      )}
    />
  )
}
