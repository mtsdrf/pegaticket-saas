import { MenuItem, Stack, TextField } from '@mui/material'
import { FORM_GRID_2_SX, FORM_GRID_3_SX } from '../../styles/layoutStandards'
import type { PagBankAddressPayload } from '../../types/pagBankConnect'
import { formatCep } from '../../utils/pagBankMasks'

const BRAZIL_STATES = [
  'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG',
  'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
]

export type AddressFormErrors = Partial<Record<keyof PagBankAddressPayload, string>>

interface PagBankAddressFormProps {
  value: PagBankAddressPayload
  onChange: (value: PagBankAddressPayload) => void
  errors?: AddressFormErrors
  disabled?: boolean
}

/**
 * Formulário de endereço reaproveitado pelo caminho Account/Cadastro
 * (roadmap seção 9.5.2) — não existia componente de endereço/CEP
 * compartilhado no projeto até esta sub-fase (busca em `src/components`
 * não encontrou nenhum), então este é local ao domínio `receiving/` em vez
 * de forçar uma extração prematura para `components/shared`.
 */
export function PagBankAddressForm({ value, onChange, errors, disabled }: PagBankAddressFormProps) {
  function set<K extends keyof PagBankAddressPayload>(field: K, fieldValue: PagBankAddressPayload[K]) {
    onChange({ ...value, [field]: fieldValue })
  }

  return (
    <Stack spacing={2}>
      <Stack sx={FORM_GRID_3_SX}>
        <TextField
          label="CEP"
          value={value.postal_code}
          onChange={(event) => set('postal_code', formatCep(event.target.value))}
          error={Boolean(errors?.postal_code)}
          helperText={errors?.postal_code}
          disabled={disabled}
          slotProps={{ htmlInput: { inputMode: 'numeric', maxLength: 9 } }}
          required
        />
        <TextField
          label="Cidade"
          value={value.city}
          onChange={(event) => set('city', event.target.value)}
          error={Boolean(errors?.city)}
          helperText={errors?.city}
          disabled={disabled}
          required
        />
        <TextField
          label="Estado"
          select
          value={value.region_code}
          onChange={(event) => set('region_code', event.target.value)}
          error={Boolean(errors?.region_code)}
          helperText={errors?.region_code}
          disabled={disabled}
          required
        >
          {BRAZIL_STATES.map((uf) => (
            <MenuItem key={uf} value={uf}>
              {uf}
            </MenuItem>
          ))}
        </TextField>
      </Stack>

      <TextField
        label="Bairro"
        value={value.locality}
        onChange={(event) => set('locality', event.target.value)}
        error={Boolean(errors?.locality)}
        helperText={errors?.locality}
        disabled={disabled}
        required
      />

      <Stack sx={FORM_GRID_2_SX}>
        <TextField
          label="Rua"
          value={value.street}
          onChange={(event) => set('street', event.target.value)}
          error={Boolean(errors?.street)}
          helperText={errors?.street}
          disabled={disabled}
          required
        />
        <Stack direction="row" spacing={2}>
          <TextField
            label="Número"
            value={value.number}
            onChange={(event) => set('number', event.target.value)}
            error={Boolean(errors?.number)}
            helperText={errors?.number}
            disabled={disabled}
            required
            sx={{ flex: 1 }}
          />
          <TextField
            label="Complemento"
            value={value.complement ?? ''}
            onChange={(event) => set('complement', event.target.value)}
            error={Boolean(errors?.complement)}
            helperText={errors?.complement}
            disabled={disabled}
            sx={{ flex: 1 }}
          />
        </Stack>
      </Stack>
    </Stack>
  )
}
