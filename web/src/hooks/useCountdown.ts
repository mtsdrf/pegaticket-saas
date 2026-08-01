import { useEffect, useRef, useState } from 'react'

/** "MM:SS" a partir de segundos restantes — nunca negativo. */
export function formatCountdown(secondsLeft: number): string {
  const safe = Math.max(0, secondsLeft)
  const minutes = Math.floor(safe / 60)
  const seconds = safe % 60
  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
}

/**
 * Contagem regressiva em segundos (validade do código OTP) — decrementa a
 * cada 1s via `setInterval`, limpo no cleanup do efeito (mesmo padrão de
 * `clearInterval` usado no polling de `SaleTrackingPage`). `restart` reseta
 * a contagem com um novo total, usado ao reenviar o código.
 */
export function useCountdown(totalSeconds: number) {
  const [secondsLeft, setSecondsLeft] = useState(totalSeconds)
  const totalRef = useRef(totalSeconds)

  useEffect(() => {
    totalRef.current = totalSeconds
    setSecondsLeft(totalSeconds)
  }, [totalSeconds])

  useEffect(() => {
    if (secondsLeft <= 0) return
    const interval = setInterval(() => {
      setSecondsLeft((current) => Math.max(0, current - 1))
    }, 1000)
    return () => clearInterval(interval)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [secondsLeft > 0])

  function restart(newTotalSeconds: number) {
    setSecondsLeft(newTotalSeconds)
  }

  return { secondsLeft, isExpired: secondsLeft <= 0, restart }
}
