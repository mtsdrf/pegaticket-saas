import AutoFixHighOutlinedIcon from '@mui/icons-material/AutoFixHighOutlined'
import VisibilityOffOutlinedIcon from '@mui/icons-material/VisibilityOffOutlined'
import VisibilityOutlinedIcon from '@mui/icons-material/VisibilityOutlined'
import { IconButton, InputAdornment, TextField, Tooltip, type TextFieldProps } from '@mui/material'
import { useState } from 'react'

type PasswordFieldProps = TextFieldProps & {
  /** Quando presente, exibe um segundo botão no endAdornment para gerar uma senha forte automaticamente. */
  onGenerate?: () => void
}

export function PasswordField({ onGenerate, ...props }: PasswordFieldProps) {
  const [showPassword, setShowPassword] = useState(false)

  return (
    <TextField
      {...props}
      type={showPassword ? 'text' : 'password'}
      slotProps={{
        ...props.slotProps,
        input: {
          ...props.slotProps?.input,
          endAdornment: (
            <InputAdornment position="end">
              {onGenerate && (
                <Tooltip title="Gerar senha forte">
                  <IconButton aria-label="Gerar senha forte" onClick={onGenerate} edge="start" size="small">
                    <AutoFixHighOutlinedIcon fontSize="small" />
                  </IconButton>
                </Tooltip>
              )}
              <IconButton
                aria-label={showPassword ? 'Ocultar senha' : 'Mostrar senha'}
                onClick={() => setShowPassword((current) => !current)}
                edge="end"
                size="small"
              >
                {showPassword ? <VisibilityOffOutlinedIcon fontSize="small" /> : <VisibilityOutlinedIcon fontSize="small" />}
              </IconButton>
            </InputAdornment>
          ),
        },
      }}
    />
  )
}
