import ContentCopyOutlinedIcon from '@mui/icons-material/ContentCopyOutlined'
import { Alert, Box, Button, Divider, Link as MuiLink, Stack, TextField, Typography } from '@mui/material'
import { QRCodeSVG } from 'qrcode.react'
import { useState, type FormEvent } from 'react'
import { Link as RouterLink } from 'react-router-dom'
import * as accountingAuthService from '../../services/accountingAuthService'
import { UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import type { RegisterAccountingResult } from '../../types/accounting'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import { formatCpfCnpj } from '../../utils/cpfCnpj'
import { AccountingAuthLayout } from './AccountingAuthLayout'

export function AccountingRegisterPage() {
  const [cnpj, setCnpj] = useState('')
  const [companyName, setCompanyName] = useState('')
  const [responsibleName, setResponsibleName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [result, setResult] = useState<RegisterAccountingResult | null>(null)
  const [copied, setCopied] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    try {
      const data = await accountingAuthService.register({
        cnpj,
        company_name: companyName,
        responsible_name: responsibleName,
        email,
        password,
      })
      setResult(data)
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Não foi possível concluir o cadastro. Tente novamente.'))
      if (error instanceof ApiRequestError) setFieldErrors(error.errors)
    } finally {
      setIsSubmitting(false)
    }
  }

  async function handleCopy() {
    if (!result) return
    try {
      await navigator.clipboard.writeText(result.totp_secret)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    } catch {
      setCopied(false)
    }
  }

  if (result) {
    return (
      <AccountingAuthLayout
        title="Configure o autenticador"
        subtitle="Escaneie o QR Code no seu app autenticador (Google Authenticator, Authy, etc). Guarde o código abaixo: você vai precisar dele no próximo passo."
      >
        <Stack spacing={2.5}>
          <Box sx={{ display: 'flex', justifyContent: 'center' }}>
            <Box sx={{ ...SOFT_PANEL_SX, p: 2 }}>
              <QRCodeSVG value={result.otpauth_uri} size={180} />
            </Box>
          </Box>

          <Alert severity="warning" variant="outlined">
            Guarde este código com segurança. Se o QR Code não funcionar, cadastre-o manualmente no app.
          </Alert>

          <Box>
            <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)', mb: 0.5 }}>Código secreto (base32)</Typography>
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
              <Box
                sx={{ ...SOFT_PANEL_SX, flex: 1, fontFamily: 'monospace', fontSize: 15, letterSpacing: '0.08em', wordBreak: 'break-all', p: 1.25, borderRadius: UI_RADIUS.sm }}
              >
                {result.totp_secret}
              </Box>
              <Button
                onClick={handleCopy}
                variant="outlined"
                size="small"
                startIcon={<ContentCopyOutlinedIcon fontSize="small" />}
                sx={{ whiteSpace: 'nowrap', minHeight: UI_SIZE.control }}
              >
                {copied ? 'Copiado' : 'Copiar'}
              </Button>
            </Stack>
          </Box>

          <Divider />

          <Button
            component={RouterLink}
            to="/contador/cadastro/confirmar-totp"
            state={{ email }}
            variant="contained"
            size="large"
          >
            Já configurei — confirmar código
          </Button>
        </Stack>
      </AccountingAuthLayout>
    )
  }

  return (
    <AccountingAuthLayout
      title="Cadastre seu escritório"
      subtitle="Crie a conta do escritório de contabilidade para acompanhar as empresas que autorizarem seu acesso."
      maxWidth={480}
    >
      <Box component="form" onSubmit={handleSubmit} noValidate>
        <Stack spacing={2.25}>
          {formError && (
            <Alert severity="error" variant="outlined" role="alert">
              {formError}
            </Alert>
          )}

          <TextField
            label="CNPJ"
            value={cnpj}
            onChange={(event) => setCnpj(formatCpfCnpj(event.target.value))}
            error={Boolean(fieldErrors.cnpj?.[0])}
            helperText={fieldErrors.cnpj?.[0]}
            fullWidth
            required
          />
          <TextField
            label="Razão social"
            value={companyName}
            onChange={(event) => setCompanyName(event.target.value)}
            error={Boolean(fieldErrors.company_name?.[0])}
            helperText={fieldErrors.company_name?.[0]}
            fullWidth
            required
          />
          <TextField
            label="Responsável"
            value={responsibleName}
            onChange={(event) => setResponsibleName(event.target.value)}
            error={Boolean(fieldErrors.responsible_name?.[0])}
            helperText={fieldErrors.responsible_name?.[0]}
            fullWidth
            required
          />
          <TextField
            label="E-mail"
            type="email"
            autoComplete="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            error={Boolean(fieldErrors.email?.[0])}
            helperText={fieldErrors.email?.[0]}
            fullWidth
            required
          />
          <TextField
            label="Senha"
            type="password"
            autoComplete="new-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            error={Boolean(fieldErrors.password?.[0])}
            helperText={fieldErrors.password?.[0] ?? 'Mínimo de 8 caracteres.'}
            fullWidth
            required
          />

          <Button type="submit" variant="contained" size="large" disabled={isSubmitting}>
            {isSubmitting ? 'Cadastrando…' : 'Cadastrar escritório'}
          </Button>

          <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)', textAlign: 'center' }}>
            Já tem conta?{' '}
            <MuiLink component={RouterLink} to="/contador/entrar">
              Entrar
            </MuiLink>
          </Typography>
        </Stack>
      </Box>
    </AccountingAuthLayout>
  )
}
