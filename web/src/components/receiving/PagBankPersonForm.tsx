import { Stack, TextField, Typography } from '@mui/material'
import { PagBankAddressForm, type AddressFormErrors } from './PagBankAddressForm'
import { FORM_GRID_2_SX } from '../../styles/layoutStandards'
import type { PagBankPersonPayload } from '../../types/pagBankConnect'
import { formatCpfCnpj } from '../../utils/cpfCnpj'
import { formatBrazilPhoneDisplay, splitBrazilPhone } from '../../utils/pagBankMasks'

export type PersonFormErrors = Partial<Record<'name' | 'birth_date' | 'mother_name' | 'tax_id' | 'phone', string>> & {
  address?: AddressFormErrors
}

interface PagBankPersonFormProps {
  value: PagBankPersonPayload
  onChange: (value: PagBankPersonPayload) => void
  errors?: PersonFormErrors
  disabled?: boolean
  /** PJ usa este formulário para o responsável legal — muda o rótulo da seção. */
  heading?: string
}

export function PagBankPersonForm({ value, onChange, errors, disabled, heading = 'Seus dados' }: PagBankPersonFormProps) {
  function set<K extends keyof PagBankPersonPayload>(field: K, fieldValue: PagBankPersonPayload[K]) {
    onChange({ ...value, [field]: fieldValue })
  }

  const phoneDisplay = formatBrazilPhoneDisplay(`${value.phone.area}${value.phone.number}`)

  return (
    <Stack spacing={2}>
      <Typography sx={{ fontWeight: 700, fontSize: 15 }}>{heading}</Typography>

      <Stack sx={FORM_GRID_2_SX}>
        <TextField
          label="Nome completo"
          value={value.name}
          onChange={(event) => set('name', event.target.value)}
          error={Boolean(errors?.name)}
          helperText={errors?.name}
          disabled={disabled}
          required
        />
        <TextField
          label="CPF"
          value={formatCpfCnpj(value.tax_id)}
          onChange={(event) => set('tax_id', event.target.value)}
          error={Boolean(errors?.tax_id)}
          helperText={errors?.tax_id}
          disabled={disabled}
          slotProps={{ htmlInput: { inputMode: 'numeric', maxLength: 14 } }}
          required
        />
      </Stack>

      <Stack sx={FORM_GRID_2_SX}>
        <TextField
          label="Data de nascimento"
          type="date"
          value={value.birth_date}
          onChange={(event) => set('birth_date', event.target.value)}
          error={Boolean(errors?.birth_date)}
          helperText={errors?.birth_date}
          disabled={disabled}
          slotProps={{ inputLabel: { shrink: true } }}
          required
        />
        <TextField
          label="Nome da mãe"
          value={value.mother_name}
          onChange={(event) => set('mother_name', event.target.value)}
          error={Boolean(errors?.mother_name)}
          helperText={errors?.mother_name}
          disabled={disabled}
          required
        />
      </Stack>

      <TextField
        label="Telefone"
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

      <Typography sx={{ fontWeight: 700, fontSize: 14, mt: 1 }}>Endereço</Typography>
      <PagBankAddressForm
        value={value.address}
        onChange={(address) => onChange({ ...value, address })}
        errors={errors?.address}
        disabled={disabled}
      />
    </Stack>
  )
}
