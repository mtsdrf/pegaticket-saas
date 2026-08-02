import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import { Alert, Box, Button, Divider, IconButton, InputAdornment, Paper, Stack, TextField, Typography } from '@mui/material'
import { useCallback, useMemo, useRef, useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { AsyncAutocomplete } from '../../components/crud/AsyncAutocomplete'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import { useAuth } from '../../hooks/useAuth'
import * as eventProductService from '../../services/eventProductService'
import * as finalCustomerService from '../../services/finalCustomerService'
import * as saleService from '../../services/saleService'
import * as ticketTypeService from '../../services/ticketTypeService'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type { FinalCustomerSearchResult } from '../../types/finalCustomer'
import type { Sale, SaleCreateItemPayload, SaleFinalCustomerRef } from '../../types/sale'
import { formatCurrency } from '../../utils/format'
import { buildSaleCreatedWhatsAppMessage, buildWhatsAppUrl, isDigitsOnlyPhone } from '../../utils/whatsApp'

const NOTES_MAX_LENGTH = 500

/** Item de catálogo vendável — `ticket_type` OU `event_product`, nunca os dois (mesma regra do backend, `SaleCreateItemPayload`). */
interface CatalogItemOption {
  kind: 'ticket_type' | 'event_product'
  uuid: string
  name: string
  price: number
}

interface DraftItem {
  id: string
  item: CatalogItemOption | null
  quantity: string
  /** Preço praticado — pré-preenchido com `item.price` ao selecionar, mas editável (desconto/acréscimo manual). */
  unitPrice: string
}

function createDraftItem(): DraftItem {
  return { id: crypto.randomUUID(), item: null, quantity: '1', unitPrice: '' }
}

/** `unitPrice` vazio (item recém-adicionado, ainda sem edição manual) cai no preço de tabela. */
function effectiveUnitPrice(item: DraftItem): number {
  if (item.unitPrice.trim() !== '' && Number.isFinite(Number(item.unitPrice))) return Number(item.unitPrice)
  return item.item?.price ?? 0
}

/** Busca combinada de tipos de ingresso e adicionais do evento — um único autocomplete cobre `ticket_type_uuid`/`event_product_uuid`. */
async function fetchCatalogOptions(query: string): Promise<CatalogItemOption[]> {
  const [ticketTypes, eventProducts] = await Promise.all([
    ticketTypeService.listTicketTypes({ name: query || undefined, per_page: 15, sort_by: 'name', sort_dir: 'asc' }),
    eventProductService.listEventProducts({ name: query || undefined, per_page: 15, sort_by: 'name', sort_dir: 'asc' }),
  ])

  return [
    ...ticketTypes.items.map((ticketType) => ({
      kind: 'ticket_type' as const,
      uuid: ticketType.uuid,
      name: `${ticketType.name} (ingresso)`,
      price: ticketType.price,
    })),
    ...eventProducts.items.map((eventProduct) => ({
      kind: 'event_product' as const,
      uuid: eventProduct.uuid,
      name: `${eventProduct.name} (adicional)`,
      price: eventProduct.price,
    })),
  ]
}

export function SaleFormPage() {
  const navigate = useNavigate()
  const { activeTenantUuid, activeTenant } = useAuth()

  const [customerOption, setCustomerOption] = useState<FinalCustomerSearchResult | null>(null)
  /**
   * Derivado da opção selecionada no autocomplete — `uuid` aqui é o do
   * comprador GLOBAL (`customerOption.final_customer.uuid`), não o do
   * vínculo tenant×comprador (`customerOption.uuid`), porque é esse que o
   * backend espera em `SalePayload.final_customer_uuid`.
   */
  const client: SaleFinalCustomerRef | null = customerOption
    ? {
        uuid: customerOption.final_customer.uuid,
        name: customerOption.final_customer.name,
        last_name: customerOption.final_customer.last_name,
        phone_primary: customerOption.phone_primary,
      }
    : null
  const [notes, setNotes] = useState('')
  const [items, setItems] = useState<DraftItem[]>([createDraftItem()])
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const whatsAppWindowRef = useRef<Window | null>(null)

  const fetchClientOptions = useCallback(async (query: string): Promise<FinalCustomerSearchResult[]> => {
    if (!activeTenantUuid) return []
    const result = await finalCustomerService.searchFinalCustomers(query, 20)
    return result.items
  }, [activeTenantUuid])

  const fetchProductOptions = useCallback(
    async (query: string): Promise<CatalogItemOption[]> => {
      if (!activeTenantUuid) return []
      return fetchCatalogOptions(query)
    },
    [activeTenantUuid],
  )

  function updateItem(id: string, patch: Partial<DraftItem>) {
    setItems((current) => current.map((item) => (item.id === id ? { ...item, ...patch } : item)))
  }

  function handleItemProductChange(id: string, item: CatalogItemOption | null) {
    // Troca de item reinicia o preço praticado pro preço de tabela do novo
    // item — o "desconto manual" anterior não faz sentido pra um item
    // diferente. Preços por categoria de cliente foram descontinuados
    // nesta migração (sem endpoint de sugestão para ticket_type/event_product).
    updateItem(id, { item, unitPrice: item ? String(item.price) : '' })
  }

  const summary = useMemo(() => {
    let totalTable = 0
    let totalPracticed = 0

    for (const item of items) {
      const quantity = Number(item.quantity)
      if (!item.item || !quantity || quantity <= 0) continue
      totalTable += item.item.price * quantity
      totalPracticed += effectiveUnitPrice(item) * quantity
    }

    return {
      totalTable,
      totalPracticed,
      discount: totalTable - totalPracticed,
    }
  }, [items])

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)

    if (!client) {
      setFormError('Selecione um cliente.')
      return
    }

    const validItems = items.filter((item) => item.item && Number(item.quantity) > 0)
    if (validItems.length === 0) {
      setFormError('Adicione ao menos um item com ingresso/adicional e quantidade.')
      return
    }

    if (notes.length > NOTES_MAX_LENGTH) {
      setFormError(`Observações não pode passar de ${NOTES_MAX_LENGTH} caracteres.`)
      return
    }

    setIsSubmitting(true)

    const itemsPayload: SaleCreateItemPayload[] = validItems.map((item) => ({
      ...(item.item!.kind === 'ticket_type'
        ? { ticket_type_uuid: item.item!.uuid }
        : { event_product_uuid: item.item!.uuid }),
      quantity: Number(item.quantity),
      unit_price: effectiveUnitPrice(item),
    }))

    try {
      const sale: Sale = await saleService.createSale({
        final_customer_uuid: client.uuid,
        is_installment: false,
        installments_count: null,
        notes: notes.trim() || undefined,
        items: itemsPayload,
        mark_as_completed: true,
        mark_as_paid: true,
      })

      if (isDigitsOnlyPhone(client.phone_primary)) {
        const message = buildSaleCreatedWhatsAppMessage({
          clientName: client.name,
          items: validItems.map((item) => ({
            name: item.item!.name,
            quantity: item.quantity,
            unitPrice: effectiveUnitPrice(item),
          })),
          total: sale.total_amount,
          isPaid: true,
          paidAmount: sale.total_amount,
          trackingUrl: activeTenant?.send_tracking_link_whatsapp
            ? `${window.location.origin}/rastreio/${sale.uuid}`
            : undefined,
        })
        const url = buildWhatsAppUrl(client.phone_primary, message)
        whatsAppWindowRef.current = window.open(url, '_blank')
      }

      navigate('/vendas-manuais')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível criar a venda agora.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backLabel="Vendas manuais"
      backTo="/vendas-manuais"
      title="Nova venda"
      subtitle="Monte os itens, defina a forma de pagamento e confirme a venda."
      breadcrumbs={[{ label: 'Vendas manuais', to: '/vendas-manuais' }, { label: 'Nova' }]}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={handleSubmit}
    >
      <Stack spacing={2.2}>
        <Box sx={{ maxWidth: { md: '50%' } }}>
          <AsyncAutocomplete
            label="Cliente"
            required
            value={customerOption}
            onChange={setCustomerOption}
            fetchOptions={fetchClientOptions}
            getOptionLabel={(option) => {
              const fullName = [option.final_customer.name, option.final_customer.last_name].filter(Boolean).join(' ')
              const identifier = option.cpf_cnpj || option.phone_primary
              return identifier ? `${fullName} (${identifier})` : fullName
            }}
            getOptionKey={(option) => option.uuid}
            placeholder="Buscar cliente pelo nome, e-mail, CPF/CNPJ ou telefone"
          />
        </Box>

        <Stack spacing={1.5}>
          <Typography sx={{ fontWeight: 700 }}>Itens da venda</Typography>

          {items.map((item) => {
            const discount = item.item && Number(item.quantity) > 0 ? (item.item.price - effectiveUnitPrice(item)) * Number(item.quantity) : 0
            const hasDiscountInfo = item.item && Number(item.quantity) > 0 && Math.abs(discount) > 0.005

            return (
              <Box key={item.id}>
                <Box
                  sx={{
                    display: 'grid',
                    gridTemplateColumns: { xs: 'minmax(0, 1fr)', md: 'minmax(0, 2fr) 110px 150px 44px' },
                    gap: 1.5,
                    alignItems: 'flex-start',
                  }}
                >
                  <AsyncAutocomplete
                    label="Ingresso / adicional"
                    value={item.item}
                    onChange={(option) => handleItemProductChange(item.id, option)}
                    fetchOptions={fetchProductOptions}
                    getOptionLabel={(option) => option.name}
                    getOptionKey={(option) => `${option.kind}:${option.uuid}`}
                    placeholder="Buscar tipo de ingresso ou adicional pelo nome"
                  />

                  <TextField
                    label="Quantidade"
                    type="number"
                    value={item.quantity}
                    onChange={(event) => updateItem(item.id, { quantity: event.target.value })}
                    slotProps={{ htmlInput: { min: 0.001, step: '0.001' } }}
                  />

                  <TextField
                    label="Valor unitário"
                    type="number"
                    value={item.unitPrice}
                    onChange={(event) => updateItem(item.id, { unitPrice: event.target.value })}
                    disabled={!item.item}
                    placeholder={item.item ? String(item.item.price) : undefined}
                    slotProps={{
                      htmlInput: { min: 0, step: '0.01' },
                      input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
                    }}
                  />

                  <IconButton
                    aria-label="Remover item"
                    disabled={items.length === 1}
                    onClick={() => setItems((current) => current.filter((entry) => entry.id !== item.id))}
                    sx={{ minWidth: 44, minHeight: 44, justifySelf: { xs: 'end', md: 'auto' } }}
                  >
                    <DeleteOutlineIcon />
                  </IconButton>
                </Box>

                {hasDiscountInfo && (
                  <Typography
                    sx={{ fontSize: 13, mt: 0.5, color: discount > 0 ? 'var(--pt-success)' : 'var(--pt-warning)' }}
                  >
                    {discount > 0
                      ? `Desconto neste item: ${formatCurrency(discount)}`
                      : `Acréscimo neste item: ${formatCurrency(Math.abs(discount))}`}
                  </Typography>
                )}

              </Box>
            )
          })}

          <Button
            variant="outlined"
            onClick={() => setItems((current) => [...current, createDraftItem()])}
            sx={{ minHeight: 44, alignSelf: { xs: 'stretch', sm: 'flex-start' }, width: { xs: '100%', sm: 'auto' } }}
          >
            Adicionar item
          </Button>
        </Stack>

        <TextField
          label="Observações"
          value={notes}
          onChange={(event) => setNotes(event.target.value.slice(0, NOTES_MAX_LENGTH))}
          minRows={3}
          multiline
          fullWidth
          helperText={`${notes.length}/${NOTES_MAX_LENGTH}`}
          slotProps={{ htmlInput: { maxLength: NOTES_MAX_LENGTH } }}
        />

        <Alert severity="info" variant="outlined">
          Vendas manuais sao registradas como concluidas e pagas no momento da criacao.
        </Alert>

        <Paper variant="outlined" sx={{ p: 2, ...SOFT_PANEL_SX }}>
          <Typography sx={{ fontWeight: 700, mb: 1.5 }}>Resumo</Typography>
          <Stack spacing={0.75}>
            <Stack direction="row" sx={{ justifyContent: 'space-between' }}>
              <Typography sx={{ color: 'var(--pt-muted)' }}>Total sem desconto</Typography>
              <Typography>{formatCurrency(summary.totalTable)}</Typography>
            </Stack>
            <Stack direction="row" sx={{ justifyContent: 'space-between' }}>
              <Typography sx={{ color: 'var(--pt-muted)' }}>{summary.discount >= 0 ? 'Desconto' : 'Acréscimo'}</Typography>
              <Typography sx={{ color: summary.discount >= 0 ? 'var(--pt-success)' : 'var(--pt-warning)' }}>
                {formatCurrency(Math.abs(summary.discount))}
              </Typography>
            </Stack>
            <Divider sx={{ my: 0.5 }} />
            <Stack direction="row" sx={{ justifyContent: 'space-between' }}>
              <Typography sx={{ fontWeight: 700 }}>Total da venda</Typography>
              <Typography sx={{ fontWeight: 700 }}>{formatCurrency(summary.totalPracticed)}</Typography>
            </Stack>

            <Stack direction="row" sx={{ justifyContent: 'space-between' }}>
              <Typography sx={{ color: 'var(--pt-muted)' }}>Status inicial</Typography>
              <Typography>Concluida e paga</Typography>
            </Stack>
          </Stack>
        </Paper>

        {!isDigitsOnlyPhone(client?.phone_primary) && client && (
          <Alert severity="info" variant="outlined">
            Este cliente não tem um telefone válido pra notificação automática por WhatsApp — a venda é criada normalmente, só o aviso não é enviado.
          </Alert>
        )}
      </Stack>
    </CrudFormShell>
  )
}
