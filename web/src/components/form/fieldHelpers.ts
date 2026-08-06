import type { TextFieldProps } from '@mui/material'

export const DATE_FIELD_SLOT_PROPS: TextFieldProps['slotProps'] = {
  inputLabel: { shrink: true },
}

export const DATETIME_FIELD_SLOT_PROPS: TextFieldProps['slotProps'] = {
  inputLabel: { shrink: true },
  htmlInput: { step: 60 },
}

export function sanitizeIntegerInput(value: string) {
  return value.replace(/[^\d-]/g, '')
}

export function sanitizePositiveIntegerInput(value: string) {
  return value.replace(/\D+/g, '')
}

export async function getBrowserCurrentCoordinates(): Promise<{ latitude: number; longitude: number }> {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error('Geolocalização não está disponível neste navegador.'))
      return
    }

    navigator.geolocation.getCurrentPosition(
      (position) =>
        resolve({
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
        }),
      () => reject(new Error('Não foi possível obter a localização atual.')),
      {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0,
      },
    )
  })
}
