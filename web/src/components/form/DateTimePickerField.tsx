import { TextField, type TextFieldProps } from '@mui/material'
import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs'
import { DateTimePicker } from '@mui/x-date-pickers/DateTimePicker'
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider'
import dayjs, { type Dayjs } from 'dayjs'
import 'dayjs/locale/pt-br'

interface DateTimePickerFieldProps {
  label: string
  value: string
  onChange: (value: string) => void
  error?: boolean
  helperText?: TextFieldProps['helperText']
  required?: boolean
  fullWidth?: boolean
  disabled?: boolean
  minDateTime?: string
}

function parseDateTimeValue(value: string): Dayjs | null {
  if (!value) return null
  const parsed = dayjs(value)
  return parsed.isValid() ? parsed : null
}

function formatDateTimeValue(value: Dayjs | null): string {
  if (!value || !value.isValid()) return ''
  return value.format('YYYY-MM-DDTHH:mm')
}

/**
 * Campo padrão do sistema para data e hora: abre calendário e, após a
 * escolha da data, exibe relógio para hora/minuto. Mantém o valor em string
 * local (`YYYY-MM-DDTHH:mm`) para encaixar sem atrito nos formulários já
 * existentes.
 */
export function DateTimePickerField({
  label,
  value,
  onChange,
  error = false,
  helperText,
  required = false,
  fullWidth = true,
  disabled = false,
  minDateTime,
}: DateTimePickerFieldProps) {
  return (
    <LocalizationProvider dateAdapter={AdapterDayjs} adapterLocale="pt-br">
      <DateTimePicker
        label={label}
        value={parseDateTimeValue(value)}
        onChange={(nextValue) => onChange(formatDateTimeValue(nextValue))}
        ampm={false}
        minutesStep={1}
        disabled={disabled}
        minDateTime={parseDateTimeValue(minDateTime ?? '') ?? undefined}
        format="DD/MM/YYYY HH:mm"
        slots={{ textField: TextField }}
        slotProps={{
          textField: {
            error,
            helperText,
            required,
            fullWidth,
          },
          actionBar: {
            actions: ['clear', 'today', 'accept'],
          },
          tabs: {
            hidden: false,
          },
        }}
      />
    </LocalizationProvider>
  )
}
