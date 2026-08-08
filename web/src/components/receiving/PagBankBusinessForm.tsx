import { Stack, TextField, Typography } from '@mui/material'
import { PagBankAddressForm, type AddressFormErrors } from './PagBankAddressForm'
import type { PagBankCompanyPayload } from '../../types/pagBankConnect'
import { formatCpfCnpj } from '../../utils/cpfCnpj'
import { formatBrazilPhoneDisplay, splitBrazilPhone } from '../../utils/pagBankMasks'

export type BusinessFormErrors = Partial<Record<'name' | 'tax_id' | 'phone', string>> & {
  address?: AddressFormErrors
}

interface PagBankBusinessFormProps {
  value: PagBankCompanyPayload
  onChange: (value: PagBankCompanyPayload) => void
  errors?: BusinessFormErrors
  disabled?: boolean
}

/** Dados da empresa (caminho PJ) — sempre exibido junto de `PagBankPersonForm` (responsável legal), nunca sozinho. */
export function PagBankBusinessForm({ value, onChange, errors, disabled }: PagBankBusinessFormProps) {
  function set<K extends keyof PagBankCompanyPayload>(field: K, fieldValue: PagBankCompanyPayload[K]) {
    onChange({ ...value, [field]: fieldValue })
  }

  const phoneDisplay = formatBrazilPhoneDisplay(`${value.phone.area}${value.phone.number}`)

  return (
    <Stack spacing={2}>
      <Typography sx={{ fontWeight: 700, fontSize: 15 }}>Dados da empresa</Typography>

      <TextField
        label="Razão social"
        value={value.name}
        onChange={(event) => set('name', event.target.value)}
        error={Boolean(errors?.name)}
        helperText={errors?.name}
        disabled={disabled}
        required
      />

      <TextField
        label="CNPJ"
        value={formatCpfCnpj(value.tax_id)}
        onChange={(event) => set('tax_id', event.target.value)}
        error={Boolean(errors?.tax_id)}
        helperText={errors?.tax_id}
        disabled={disabled}
        slotProps={{ htmlInput: { inputMode: 'numeric', maxLength: 18 } }}
        required
        sx={{ maxWidth: { sm: 320 } }}
      />

      <TextField
        label="Telefone comercial"
        value={phoneDisplay}
        onChange={(event) => {
          const { area, number } = splitBrazilPhone(event.target.value)
          onChange({ ...value, phone: { ...value.phone, area, number } })
        }}
        error={Boolean(errors?.phone)}
        helperText={errors?.phone}
        disabled={disabled}
        slotProps={{ htmlInput: { inputMode: 'numeric', maxLength: 16 } }}
        required
        sx={{ maxWidth: { sm: 320 } }}
      />

      <Typography sx={{ fontWeight: 700, fontSize: 14, mt: 1 }}>Endereço da empresa</Typography>
      <PagBankAddressForm
        value={value.address}
        onChange={(address) => onChange({ ...value, address })}
        errors={errors?.address}
        disabled={disabled}
      />
    </Stack>
  )
}
