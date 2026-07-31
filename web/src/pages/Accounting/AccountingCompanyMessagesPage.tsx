import { useCallback, useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { AccountingMessageThread } from '../../components/accounting/AccountingMessageThread'
import * as accountingService from '../../services/accountingService'
import type { AccountingMessage, CreateAccountingMessagePayload } from '../../types/accounting'
import { getApiErrorMessage } from '../../types/api'

export function AccountingCompanyMessagesPage() {
  const { tenantUuid } = useParams<{ tenantUuid: string }>()
  const [messages, setMessages] = useState<AccountingMessage[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  const load = useCallback(async () => {
    if (!tenantUuid) return
    setIsLoading(true)
    setLoadError(null)
    try {
      setMessages(await accountingService.listMessages(tenantUuid))
    } catch (err) {
      setLoadError(getApiErrorMessage(err, 'Não foi possível carregar as mensagens agora.'))
    } finally {
      setIsLoading(false)
    }
  }, [tenantUuid])

  useEffect(() => {
    void load()
  }, [load])

  const handleSend = useCallback(
    async (payload: CreateAccountingMessagePayload) => {
      if (!tenantUuid) return
      await accountingService.sendMessage(tenantUuid, payload)
      await load()
    },
    [tenantUuid, load],
  )

  return (
    <AccountingMessageThread
      messages={messages}
      isLoading={isLoading}
      loadError={loadError}
      currentSender="accounting_office"
      onSend={handleSend}
    />
  )
}
