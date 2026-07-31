import { Alert, Button } from '@mui/material'

interface AnalyticsErrorAlertProps {
  message: string
  onRetry: () => void
}

/** Alert de erro padrão das abas de Análises, com retry. */
export function AnalyticsErrorAlert({ message, onRetry }: AnalyticsErrorAlertProps) {
  return (
    <Alert
      severity="error"
      variant="outlined"
      action={
        <Button color="inherit" size="small" onClick={onRetry}>
          Tentar de novo
        </Button>
      }
    >
      {message}
    </Alert>
  )
}
