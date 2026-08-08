import { Alert, Box, Button, Checkbox, Divider, FormControlLabel, Stack, Typography } from '@mui/material'
import type { PagBankCompanyPayload, PagBankPersonPayload } from '../../types/pagBankConnect'
import { onlyDigits } from '../../utils/cpfCnpj'

interface ReceivingReviewStepProps {
  email: string
  person: PagBankPersonPayload
  company?: PagBankCompanyPayload
  termsAccepted: boolean
  onChangeTermsAccepted: (value: boolean) => void
  onBack: () => void
  onSubmit: () => void
  isSubmitting: boolean
  error: string | null
}

/** Mostra só os últimos 2 dígitos do documento — nunca o CPF/CNPJ completo, nem antes do envio. */
function maskTaxId(value: string): string {
  const digits = onlyDigits(value)
  if (digits.length < 3) return digits
  return `${'*'.repeat(digits.length - 2)}${digits.slice(-2)}`
}

function ReviewRow({ label, value }: { label: string; value: string }) {
  return (
    <Stack direction={{ xs: 'column', sm: 'row' }} sx={{ py: 0.9, gap: { xs: 0.2, sm: 2 } }}>
      <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', minWidth: { sm: 200 } }}>{label}</Typography>
      <Typography sx={{ fontSize: 14, fontWeight: 500 }}>{value || '—'}</Typography>
    </Stack>
  )
}

/**
 * Revisão final antes de enviar a criação da conta SELLER — documento
 * sempre mascarado na tela (roadmap seção 9.7/checklist), mesmo sendo dado
 * ainda não persistido. Botão desabilitado enquanto os termos não forem
 * aceitos e bloqueado contra duplo-submit durante o envio.
 */
export function ReceivingReviewStep({
  email,
  person,
  company,
  termsAccepted,
  onChangeTermsAccepted,
  onBack,
  onSubmit,
  isSubmitting,
  error,
}: ReceivingReviewStepProps) {
  const addressLine = (address: PagBankPersonPayload['address']) =>
    `${address.street}, ${address.number}${address.complement ? ` - ${address.complement}` : ''} - ${address.locality}, ${address.city}/${address.region_code} - ${address.postal_code}`

  return (
    <Stack spacing={2.5}>
      <Box>
        <Typography sx={{ fontWeight: 700, fontSize: 18, mb: 0.5 }}>Revise antes de enviar</Typography>
        <Typography sx={{ fontSize: 14, color: 'var(--pt-muted)' }}>
          Confira os dados abaixo. Depois de enviar, vamos processar a criação da sua conta de recebimento.
        </Typography>
      </Box>

      {error && <Alert severity="error">{error}</Alert>}

      <Box>
        <Typography sx={{ fontWeight: 700, fontSize: 14, mb: 0.5 }}>{company ? 'Responsável legal' : 'Seus dados'}</Typography>
        <ReviewRow label="Nome" value={person.name} />
        <ReviewRow label="CPF" value={maskTaxId(person.tax_id)} />
        <ReviewRow label="E-mail" value={email} />
        <ReviewRow label="Telefone" value={`(${person.phone.area}) ${person.phone.number}`} />
        <ReviewRow label="Endereço" value={addressLine(person.address)} />
      </Box>

      {company && (
        <>
          <Divider />
          <Box>
            <Typography sx={{ fontWeight: 700, fontSize: 14, mb: 0.5 }}>Dados da empresa</Typography>
            <ReviewRow label="Razão social" value={company.name} />
            <ReviewRow label="CNPJ" value={maskTaxId(company.tax_id)} />
            <ReviewRow label="Telefone" value={`(${company.phone.area}) ${company.phone.number}`} />
            <ReviewRow label="Endereço" value={addressLine(company.address)} />
          </Box>
        </>
      )}

      <FormControlLabel
        control={<Checkbox checked={termsAccepted} onChange={(event) => onChangeTermsAccepted(event.target.checked)} disabled={isSubmitting} />}
        label="Li e aceito os termos de uso da conta de recebimento do PagBank."
      />

      <Stack direction="row" spacing={1.5}>
        <Button variant="text" disabled={isSubmitting} onClick={onBack}>
          Voltar
        </Button>
        <Button variant="contained" disabled={!termsAccepted || isSubmitting} onClick={onSubmit}>
          {isSubmitting ? 'Enviando…' : 'Enviar para criação da conta'}
        </Button>
      </Stack>
    </Stack>
  )
}
