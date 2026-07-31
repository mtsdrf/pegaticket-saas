import { Autocomplete, CircularProgress, TextField } from '@mui/material'
import { useEffect, useState } from 'react'
import { useDebouncedValue } from '../../hooks/useDebouncedValue'

interface AsyncAutocompleteProps<T> {
  label: string
  value: T | null
  onChange: (value: T | null) => void
  /**
   * Busca as opções pro termo digitado (debounced) — chamada de novo toda
   * vez que o campo abre ou o texto muda. Passe uma função estável
   * (`useCallback`) no chamador: como é recriada a cada render se for uma
   * arrow function inline, isso dispararia uma busca nova em toda
   * renderização do formulário pai (ex.: outro campo do form mudando).
   */
  fetchOptions: (query: string) => Promise<T[]>
  getOptionLabel: (option: T) => string
  getOptionKey: (option: T) => string
  placeholder?: string
  required?: boolean
  error?: boolean
  helperText?: string
  disabled?: boolean
}

/**
 * Primeiro `Autocomplete` do projeto — busca assíncrona debounced contra um
 * endpoint paginado/filtrável (`name` contains, mesmo padrão dos grids) em
 * vez de carregar até 100 registros de uma vez com `<Select>` (isso escondia
 * clientes/produtos além do teto). Segue os tokens `--pt-*` automaticamente
 * via os overrides já existentes em `theme/index.ts` pro `TextField`
 * (`OutlinedInput`/`InputLabel`), sem precisar de CSS próprio.
 */
export function AsyncAutocomplete<T>({
  label,
  value,
  onChange,
  fetchOptions,
  getOptionLabel,
  getOptionKey,
  placeholder,
  required,
  error,
  helperText,
  disabled,
}: AsyncAutocompleteProps<T>) {
  const [inputValue, setInputValue] = useState('')
  const [open, setOpen] = useState(false)
  const debouncedInput = useDebouncedValue(inputValue, 350)
  const [options, setOptions] = useState<T[]>([])
  const [isLoading, setIsLoading] = useState(false)

  useEffect(() => {
    if (!open) return
    let cancelled = false
    setIsLoading(true)

    fetchOptions(debouncedInput)
      .then((result) => {
        if (!cancelled) setOptions(result)
      })
      .catch(() => {
        if (!cancelled) setOptions([])
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [open, debouncedInput, fetchOptions])

  return (
    <Autocomplete
      size="small"
      disabled={disabled}
      open={open}
      onOpen={() => setOpen(true)}
      onClose={() => setOpen(false)}
      value={value}
      onChange={(_event, newValue) => onChange(newValue)}
      inputValue={inputValue}
      onInputChange={(_event, newInputValue) => setInputValue(newInputValue)}
      options={options}
      filterOptions={(opts) => opts}
      loading={isLoading}
      getOptionLabel={getOptionLabel}
      getOptionKey={getOptionKey}
      isOptionEqualToValue={(option, val) => getOptionKey(option) === getOptionKey(val)}
      noOptionsText="Nenhum resultado encontrado"
      loadingText="Buscando…"
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
                  {isLoading ? <CircularProgress color="inherit" size={16} /> : null}
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
