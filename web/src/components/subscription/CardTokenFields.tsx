import { MenuItem, Stack, TextField } from '@mui/material'
import type { CardTokenFormState } from './cardTokenFields.helpers'

interface CardTokenFieldsProps {
  form: CardTokenFormState
  onChange: (key: keyof CardTokenFormState, value: string) => void
  disabled?: boolean
}

/**
 * Campos de número/nome/validade/CVV/documento reaproveitados em todo fluxo
 * que precisa tokenizar cartão via MP.js (troca de cartão, contratação e
 * troca de plano) — o número e o CVV nunca saem do navegador crus, só o
 * token resultante (`createCardToken`) chega ao backend PegaTicket.
 */
export function CardTokenFields({ form, onChange, disabled = false }: CardTokenFieldsProps) {
  return (
    <Stack spacing={2}>
      <TextField
        label="Número do cartão"
        value={form.cardNumber}
        onChange={(event) => onChange('cardNumber', event.target.value.replaceAll(/\D/g, '').slice(0, 19))}
        required
        fullWidth
        disabled={disabled}
        slotProps={{ htmlInput: { inputMode: 'numeric', autoComplete: 'cc-number' } }}
      />
      <TextField
        label="Nome impresso no cartão"
        value={form.cardholderName}
        onChange={(event) => onChange('cardholderName', event.target.value)}
        required
        fullWidth
        disabled={disabled}
        slotProps={{ htmlInput: { maxLength: 255, autoComplete: 'cc-name' } }}
      />

      <Stack direction="row" spacing={1.5}>
        <TextField
          label="Mês"
          placeholder="MM"
          value={form.expirationMonth}
          onChange={(event) => onChange('expirationMonth', event.target.value.replaceAll(/\D/g, '').slice(0, 2))}
          required
          disabled={disabled}
          sx={{ width: 90 }}
          slotProps={{ htmlInput: { inputMode: 'numeric', autoComplete: 'cc-exp-month' } }}
        />
        <TextField
          label="Ano"
          placeholder="AAAA"
          value={form.expirationYear}
          onChange={(event) => onChange('expirationYear', event.target.value.replaceAll(/\D/g, '').slice(0, 4))}
          required
          disabled={disabled}
          sx={{ width: 110 }}
          slotProps={{ htmlInput: { inputMode: 'numeric', autoComplete: 'cc-exp-year' } }}
        />
        <TextField
          label="CVV"
          value={form.securityCode}
          onChange={(event) => onChange('securityCode', event.target.value.replaceAll(/\D/g, '').slice(0, 4))}
          required
          disabled={disabled}
          sx={{ flex: 1 }}
          slotProps={{ htmlInput: { inputMode: 'numeric', autoComplete: 'cc-csc' } }}
        />
      </Stack>

      <Stack direction="row" spacing={1.5}>
        <TextField
          select
          label="Documento"
          value={form.identificationType}
          onChange={(event) => onChange('identificationType', event.target.value)}
          disabled={disabled}
          sx={{ width: 120 }}
        >
          <MenuItem value="CPF">CPF</MenuItem>
          <MenuItem value="CNPJ">CNPJ</MenuItem>
        </TextField>
        <TextField
          label={form.identificationType === 'CNPJ' ? 'Número do CNPJ' : 'Número do CPF'}
          value={form.identificationNumber}
          onChange={(event) => onChange('identificationNumber', event.target.value.replaceAll(/\D/g, '').slice(0, 14))}
          required
          fullWidth
          disabled={disabled}
          slotProps={{ htmlInput: { inputMode: 'numeric' } }}
        />
      </Stack>
    </Stack>
  )
}
