import ApartmentOutlinedIcon from '@mui/icons-material/ApartmentOutlined'
import PersonOutlineOutlinedIcon from '@mui/icons-material/PersonOutlineOutlined'
import { Box, Button, Paper, Stack, TextField, Typography } from '@mui/material'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import type { PagBankAccountPersonType } from '../../types/pagBankConnect'

interface PagBankAccountTypeStepProps {
  personType: PagBankAccountPersonType | null
  onChangePersonType: (value: PagBankAccountPersonType) => void
  email: string
  onChangeEmail: (value: string) => void
  emailError?: string
  onBack: () => void
  onNext: () => void
}

/** Escolha PF/PJ + e-mail da conta de recebimento — primeiro passo do caminho Account/Cadastro. */
export function PagBankAccountTypeStep({
  personType,
  onChangePersonType,
  email,
  onChangeEmail,
  emailError,
  onBack,
  onNext,
}: PagBankAccountTypeStepProps) {
  return (
    <Stack spacing={2.5}>
      <Box>
        <Typography sx={{ fontWeight: 700, fontSize: 18, mb: 0.5 }}>Como você vai receber?</Typography>
        <Typography sx={{ fontSize: 14, color: 'var(--pt-muted)' }}>
          Escolha o tipo de conta que vamos criar para receber suas vendas.
        </Typography>
      </Box>

      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
        <Paper
          variant="outlined"
          onClick={() => onChangePersonType('pf')}
          sx={{
            p: 2.5,
            flex: 1,
            cursor: 'pointer',
            ...ELEVATED_SURFACE_SX,
            borderColor: personType === 'pf' ? 'var(--pt-primary)' : undefined,
            borderWidth: personType === 'pf' ? 2 : 1,
          }}
        >
          <Stack spacing={1}>
            <PersonOutlineOutlinedIcon sx={{ color: 'var(--pt-primary)' }} />
            <Typography sx={{ fontWeight: 700, fontSize: 15 }}>Pessoa física</Typography>
            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>Você recebe em seu próprio CPF.</Typography>
          </Stack>
        </Paper>

        <Paper
          variant="outlined"
          onClick={() => onChangePersonType('pj')}
          sx={{
            p: 2.5,
            flex: 1,
            cursor: 'pointer',
            ...ELEVATED_SURFACE_SX,
            borderColor: personType === 'pj' ? 'var(--pt-primary)' : undefined,
            borderWidth: personType === 'pj' ? 2 : 1,
          }}
        >
          <Stack spacing={1}>
            <ApartmentOutlinedIcon sx={{ color: 'var(--pt-primary)' }} />
            <Typography sx={{ fontWeight: 700, fontSize: 15 }}>Pessoa jurídica</Typography>
            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>Sua empresa recebe no CNPJ.</Typography>
          </Stack>
        </Paper>
      </Stack>

      <TextField
        label="E-mail da conta de recebimento"
        type="email"
        value={email}
        onChange={(event) => onChangeEmail(event.target.value)}
        error={Boolean(emailError)}
        helperText={emailError ?? 'Usaremos este e-mail para avisos sobre sua conta de recebimento.'}
        sx={{ maxWidth: { sm: 420 } }}
        required
      />

      <Stack direction="row" spacing={1.5}>
        <Button variant="text" onClick={onBack}>
          Voltar
        </Button>
        <Button variant="contained" disabled={!personType || !email.trim()} onClick={onNext}>
          Continuar
        </Button>
      </Stack>
    </Stack>
  )
}
