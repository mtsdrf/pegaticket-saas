import AddCircleOutlineOutlinedIcon from '@mui/icons-material/AddCircleOutlineOutlined'
import DeleteOutlineOutlinedIcon from '@mui/icons-material/DeleteOutlineOutlined'
import ExpandMoreIcon from '@mui/icons-material/ExpandMore'
import {
  Accordion,
  AccordionDetails,
  AccordionSummary,
  Alert,
  Box,
  FormControlLabel,
  IconButton,
  InputAdornment,
  MenuItem,
  Stack,
  Switch,
  TextField,
  Typography,
} from '@mui/material'
import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ACCESS } from '../../access/requirements'
import { CrudFormShell } from '../../components/crud/CrudFormShell'
import { LocalAutocomplete } from '../../components/crud/LocalAutocomplete'
import { ProductImageUpload } from '../../components/product/ProductImageUpload'
import { useAccessControl } from '../../hooks/useAccessControl'
import * as productService from '../../services/productService'
import * as productTypeService from '../../services/productTypeService'
import { FORM_GRID_2_SX, FORM_GRID_3_SX, UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'

interface ProductFormState {
  product_type_uuid: string
  name: string
  price: string
  sku: string
  barcode: string
  brand: string
  ncm: string
  cest: string
  origin: string
  default_cfop: string
  csosn_cst: string
  unit: string
  description: string
  is_available: boolean
  surcharge_rate: string
  is_lot_controlled: boolean
  is_expiry_controlled: boolean
  is_serial_controlled: boolean
  min_stock: string
  max_stock: string
  reorder_point: string
  reorder_qty: string
  last_purchase_cost: string
  option_groups: ProductOptionGroupFormState[]
}

interface ProductOptionFormState {
  uuid?: string
  client_key: string
  name: string
  description: string
  price: string
  sort_order: string
  is_available: boolean
}

interface ProductOptionGroupFormState {
  uuid?: string
  client_key: string
  name: string
  description: string
  min_select: string
  max_select: string
  sort_order: string
  is_active: boolean
  options: ProductOptionFormState[]
}

function createEmptyOption(): ProductOptionFormState {
  return {
    client_key: globalThis.crypto?.randomUUID?.() ?? `opt-${Date.now()}-${Math.random()}`,
    name: '',
    description: '',
    price: '',
    sort_order: '0',
    is_available: true,
  }
}

function createEmptyGroup(): ProductOptionGroupFormState {
  return {
    client_key: globalThis.crypto?.randomUUID?.() ?? `grp-${Date.now()}-${Math.random()}`,
    name: '',
    description: '',
    min_select: '0',
    max_select: '1',
    sort_order: '0',
    is_active: true,
    options: [createEmptyOption()],
  }
}

const EMPTY_FORM: ProductFormState = {
  product_type_uuid: '',
  name: '',
  price: '',
  sku: '',
  barcode: '',
  brand: '',
  ncm: '',
  cest: '',
  origin: '',
  default_cfop: '',
  csosn_cst: '',
  unit: '',
  description: '',
  is_available: true,
  surcharge_rate: '',
  is_lot_controlled: false,
  is_expiry_controlled: false,
  is_serial_controlled: false,
  min_stock: '',
  max_stock: '',
  reorder_point: '',
  reorder_qty: '',
  last_purchase_cost: '',
  option_groups: [],
}

function toOptionalNumber(value: string): number | null {
  return value === '' ? null : Number(value)
}

function firstFieldError(fieldErrors: Record<string, string[]>, path: string): string | null {
  return fieldErrors[path]?.[0] ?? null
}

export function ProductFormPage() {
  const { uuid } = useParams<{ uuid: string }>()
  const isEditMode = Boolean(uuid)
  const navigate = useNavigate()
  const { can } = useAccessControl()

  const [form, setForm] = useState<ProductFormState>(EMPTY_FORM)
  const [imageFile, setImageFile] = useState<File | null>(null)
  const [existingImageUrl, setExistingImageUrl] = useState<string | null>(null)
  const [typeOptions, setTypeOptions] = useState<{ value: string; label: string }[]>([])
  const [isLoadingForm, setIsLoadingForm] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    setIsLoadingForm(true)
    setLoadError(null)

    const typesPromise = productTypeService.listProductTypes({ per_page: 100 })
    const recordPromise = uuid ? productService.getProduct(uuid) : Promise.resolve(null)

    Promise.all([typesPromise, recordPromise])
      .then(([types, record]) => {
        setTypeOptions(
          types.items.map((type) => ({
            value: type.uuid,
            label: `${type.product_category_name} — ${type.name}`,
          })),
        )

        if (record) {
          setForm({
            product_type_uuid: record.product_type?.uuid ?? '',
            name: record.name,
            price: String(record.price),
            sku: record.sku ?? '',
            barcode: record.barcode ?? '',
            brand: record.brand ?? '',
            ncm: record.ncm ?? '',
            cest: record.cest ?? '',
            origin: record.origin ?? '',
            default_cfop: record.default_cfop ?? '',
            csosn_cst: record.csosn_cst ?? '',
            unit: record.unit ?? '',
            description: record.description ?? '',
            is_available: record.is_available,
            surcharge_rate: record.surcharge_rate === null ? '' : String(record.surcharge_rate),
            is_lot_controlled: record.is_lot_controlled,
            is_expiry_controlled: record.is_expiry_controlled,
            is_serial_controlled: record.is_serial_controlled,
            // decimal:3 no backend chega como string com 3 casas fixas
            // (ex. "5.000") — Number()+String() normaliza pro campo editável
            // sem o ".000" que confundia.
            min_stock: record.min_stock === null ? '' : String(Number(record.min_stock)),
            max_stock: record.max_stock === null ? '' : String(Number(record.max_stock)),
            reorder_point: record.reorder_point === null ? '' : String(Number(record.reorder_point)),
            reorder_qty: record.reorder_qty === null ? '' : String(Number(record.reorder_qty)),
            last_purchase_cost: record.last_purchase_cost == null ? '' : String(record.last_purchase_cost),
            option_groups: (record.option_groups ?? []).map((group) => ({
              uuid: group.uuid,
              client_key: group.uuid,
              name: group.name,
              description: group.description ?? '',
              min_select: String(group.min_select),
              max_select: String(group.max_select),
              sort_order: String(group.sort_order),
              is_active: group.is_active,
              options: group.options.map((option) => ({
                uuid: option.uuid,
                client_key: option.uuid,
                name: option.name,
                description: option.description ?? '',
                price: String(option.price),
                sort_order: String(option.sort_order),
                is_available: option.is_available,
              })),
            })),
          })
          setExistingImageUrl(record.image_url)
        }
      })
      .catch((error) => setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os dados do produto agora.')))
      .finally(() => setIsLoadingForm(false))
  }, [uuid])

  function updateField<K extends keyof ProductFormState>(key: K, value: ProductFormState[K]) {
    setForm((current) => ({ ...current, [key]: value }))
  }

  function updateOptionGroup(index: number, updater: (group: ProductOptionGroupFormState) => ProductOptionGroupFormState) {
    setForm((current) => ({
      ...current,
      option_groups: current.option_groups.map((group, groupIndex) => (groupIndex === index ? updater(group) : group)),
    }))
  }

  function updateOption(
    groupIndex: number,
    optionIndex: number,
    updater: (option: ProductOptionFormState) => ProductOptionFormState,
  ) {
    updateOptionGroup(groupIndex, (group) => ({
      ...group,
      options: group.options.map((option, currentOptionIndex) => (currentOptionIndex === optionIndex ? updater(option) : option)),
    }))
  }

  function addOptionGroup() {
    setForm((current) => ({
      ...current,
      option_groups: [...current.option_groups, createEmptyGroup()],
    }))
  }

  function removeOptionGroup(index: number) {
    setForm((current) => ({
      ...current,
      option_groups: current.option_groups.filter((_, groupIndex) => groupIndex !== index),
    }))
  }

  function addOption(groupIndex: number) {
    updateOptionGroup(groupIndex, (group) => ({
      ...group,
      options: [...group.options, createEmptyOption()],
    }))
  }

  function removeOption(groupIndex: number, optionIndex: number) {
    updateOptionGroup(groupIndex, (group) => ({
      ...group,
      options: group.options.filter((_, currentOptionIndex) => currentOptionIndex !== optionIndex),
    }))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)
    setFieldErrors({})
    setIsSubmitting(true)

    const payload = {
      product_type_uuid: form.product_type_uuid,
      name: form.name.trim(),
      price: Number(form.price),
      sku: form.sku.trim() || undefined,
      barcode: form.barcode.trim() || undefined,
      brand: form.brand.trim() || undefined,
      ncm: form.ncm.trim() || undefined,
      cest: form.cest.trim() || undefined,
      origin: form.origin || undefined,
      default_cfop: form.default_cfop.trim() || undefined,
      csosn_cst: form.csosn_cst.trim() || undefined,
      unit: form.unit.trim() || undefined,
      description: form.description.trim() || undefined,
      is_available: form.is_available,
      surcharge_rate: toOptionalNumber(form.surcharge_rate),
      is_lot_controlled: form.is_lot_controlled,
      is_expiry_controlled: form.is_expiry_controlled,
      is_serial_controlled: form.is_serial_controlled,
      min_stock: toOptionalNumber(form.min_stock),
      max_stock: toOptionalNumber(form.max_stock),
      reorder_point: toOptionalNumber(form.reorder_point),
      reorder_qty: toOptionalNumber(form.reorder_qty),
      last_purchase_cost: toOptionalNumber(form.last_purchase_cost),
      option_groups: form.option_groups.map((group) => ({
        uuid: group.uuid,
        name: group.name.trim(),
        description: group.description.trim() || undefined,
        min_select: Number(group.min_select || 0),
        max_select: Number(group.max_select || 0),
        sort_order: Number(group.sort_order || 0),
        is_active: group.is_active,
        options: group.options.map((option) => ({
          uuid: option.uuid,
          name: option.name.trim(),
          description: option.description.trim() || undefined,
          price: Number(option.price),
          sort_order: Number(option.sort_order || 0),
          is_available: option.is_available,
        })),
      })),
    }

    try {
      if (uuid) {
        await productService.updateProduct(uuid, payload, imageFile)
      } else {
        await productService.createProduct(payload, imageFile)
      }
      navigate('/produtos')
    } catch (err) {
      setFormError(getApiErrorMessage(err, 'Não foi possível salvar o produto agora.'))
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <CrudFormShell
      backLabel="Produtos"
      backTo="/produtos"
      title={isEditMode ? 'Editar produto' : 'Novo produto'}
      subtitle={isEditMode ? 'Atualize os dados do produto.' : 'Cadastre um novo produto no catálogo.'}
      loadError={loadError}
      isLoadingRecord={isLoadingForm}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={(event) => void handleSubmit(event)}
    >
      <LocalAutocomplete
        label="Tipo"
        options={typeOptions}
        value={typeOptions.find((option) => option.value === form.product_type_uuid) ?? null}
        onChange={(option) => updateField('product_type_uuid', option?.value ?? '')}
        getOptionLabel={(option) => option.label}
        getOptionKey={(option) => option.value}
        required
        fullWidth
        error={Boolean(fieldErrors.product_type_uuid)}
        helperText={fieldErrors.product_type_uuid?.[0]}
        sx={{ mb: 2, maxWidth: { sm: 560 } }}
      />

      <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'minmax(0, 2fr) minmax(0, 1fr)' }, mb: 2 }}>
        <TextField
          label="Nome"
          value={form.name}
          onChange={(event) => updateField('name', event.target.value)}
          error={Boolean(fieldErrors.name)}
          helperText={fieldErrors.name?.[0]}
          required
        />
        <TextField
          label="Preço"
          type="number"
          value={form.price}
          onChange={(event) => updateField('price', event.target.value)}
          error={Boolean(fieldErrors.price)}
          helperText={fieldErrors.price?.[0]}
          required
          slotProps={{
            input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
            htmlInput: { min: 0, step: '0.01' },
          }}
        />
      </Box>

      <Box sx={{ ...FORM_GRID_3_SX, mb: 2 }}>
        <TextField
          label="SKU"
          value={form.sku}
          onChange={(event) => updateField('sku', event.target.value)}
          error={Boolean(fieldErrors.sku)}
          helperText={fieldErrors.sku?.[0]}
          slotProps={{ htmlInput: { maxLength: 255 } }}
        />
        <TextField
          label="Código de barras"
          value={form.barcode}
          onChange={(event) => updateField('barcode', event.target.value)}
          error={Boolean(fieldErrors.barcode)}
          helperText={fieldErrors.barcode?.[0]}
          slotProps={{ htmlInput: { maxLength: 255 } }}
        />
        <TextField
          label="Marca"
          value={form.brand}
          onChange={(event) => updateField('brand', event.target.value)}
          error={Boolean(fieldErrors.brand)}
          helperText={fieldErrors.brand?.[0]}
          slotProps={{ htmlInput: { maxLength: 255 } }}
        />
      </Box>

      <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'minmax(0, 1fr) minmax(0, 2fr)' }, mb: 2 }}>
        <TextField
          label="Unidade"
          placeholder="un, kg, cx…"
          value={form.unit}
          onChange={(event) => updateField('unit', event.target.value)}
          error={Boolean(fieldErrors.unit)}
          helperText={fieldErrors.unit?.[0]}
          slotProps={{ htmlInput: { maxLength: 50 } }}
        />
        <ProductImageUpload existingImageUrl={existingImageUrl} onFileSelected={setImageFile} />
      </Box>

      <TextField
        label="Descrição"
        value={form.description}
        onChange={(event) => updateField('description', event.target.value)}
        error={Boolean(fieldErrors.description)}
        helperText={fieldErrors.description?.[0]}
        fullWidth
        multiline
        minRows={2}
        sx={{ mb: 2 }}
      />

      <FormControlLabel
        sx={{ mb: 1 }}
        control={
          <Switch
            checked={form.is_available}
            onChange={(event) => updateField('is_available', event.target.checked)}
          />
        }
        label="Disponível"
      />

      <Accordion
        variant="outlined"
        sx={{ mt: 2, ...SOFT_PANEL_SX, '&::before': { display: 'none' } }}
      >
        <AccordionSummary expandIcon={<ExpandMoreIcon />}>
          <Typography sx={{ fontSize: 14.5, fontWeight: 600 }}>Complementos e opcionais</Typography>
        </AccordionSummary>
        <AccordionDetails>
          <Stack spacing={2}>
            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
              Estruture os grupos de escolha do produto para delivery e futuros marketplaces. Cada grupo pode ter mínimo/máximo de seleção e vários complementos com preço próprio.
            </Typography>

            {form.option_groups.map((group, groupIndex) => (
              <Box
                key={group.client_key}
                sx={{
                  p: { xs: 1.5, sm: 2 },
                  ...SOFT_PANEL_SX,
                  display: 'flex',
                  flexDirection: 'column',
                  gap: 2,
                  borderRadius: UI_RADIUS.lg,
                }}
              >
                <Stack direction="row" spacing={1} sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
                  <Typography sx={{ fontSize: 14, fontWeight: 700 }}>Grupo {groupIndex + 1}</Typography>
                  <IconButton aria-label="Remover grupo" color="error" onClick={() => removeOptionGroup(groupIndex)}>
                    <DeleteOutlineOutlinedIcon fontSize="small" />
                  </IconButton>
                </Stack>

                <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', lg: 'minmax(0, 2fr) repeat(3, minmax(0, 1fr))' } }}>
                  <TextField
                    label="Nome do grupo"
                    value={group.name}
                    onChange={(event) => updateOptionGroup(groupIndex, (current) => ({ ...current, name: event.target.value }))}
                    error={Boolean(firstFieldError(fieldErrors, `option_groups.${groupIndex}.name`))}
                    helperText={firstFieldError(fieldErrors, `option_groups.${groupIndex}.name`) ?? 'Ex.: Bebidas, acompanhamentos, extras.'}
                  />
                  <TextField
                    label="Mínimo"
                    type="number"
                    value={group.min_select}
                    onChange={(event) => updateOptionGroup(groupIndex, (current) => ({ ...current, min_select: event.target.value }))}
                    error={Boolean(firstFieldError(fieldErrors, `option_groups.${groupIndex}.min_select`))}
                    helperText={firstFieldError(fieldErrors, `option_groups.${groupIndex}.min_select`)}
                    slotProps={{ htmlInput: { min: 0, step: '1' } }}
                  />
                  <TextField
                    label="Máximo"
                    type="number"
                    value={group.max_select}
                    onChange={(event) => updateOptionGroup(groupIndex, (current) => ({ ...current, max_select: event.target.value }))}
                    error={Boolean(firstFieldError(fieldErrors, `option_groups.${groupIndex}.max_select`))}
                    helperText={firstFieldError(fieldErrors, `option_groups.${groupIndex}.max_select`)}
                    slotProps={{ htmlInput: { min: 0, step: '1' } }}
                  />
                  <TextField
                    label="Ordem"
                    type="number"
                    value={group.sort_order}
                    onChange={(event) => updateOptionGroup(groupIndex, (current) => ({ ...current, sort_order: event.target.value }))}
                    error={Boolean(firstFieldError(fieldErrors, `option_groups.${groupIndex}.sort_order`))}
                    helperText={firstFieldError(fieldErrors, `option_groups.${groupIndex}.sort_order`)}
                    slotProps={{ htmlInput: { min: 0, step: '1' } }}
                  />
                </Box>

                <TextField
                  label="Descrição do grupo"
                  value={group.description}
                  onChange={(event) => updateOptionGroup(groupIndex, (current) => ({ ...current, description: event.target.value }))}
                  error={Boolean(firstFieldError(fieldErrors, `option_groups.${groupIndex}.description`))}
                  helperText={firstFieldError(fieldErrors, `option_groups.${groupIndex}.description`)}
                  fullWidth
                />

                <FormControlLabel
                  control={
                    <Switch
                      checked={group.is_active}
                      onChange={(event) => updateOptionGroup(groupIndex, (current) => ({ ...current, is_active: event.target.checked }))}
                    />
                  }
                  label="Grupo ativo"
                />

                <Stack spacing={1.5}>
                  {group.options.map((option, optionIndex) => (
                    <Box
                      key={option.client_key}
                      sx={{
                        p: { xs: 1.25, sm: 1.5 },
                        ...SOFT_PANEL_SX,
                        borderStyle: 'dashed',
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 1.5,
                        borderRadius: UI_RADIUS.md,
                      }}
                    >
                      <Stack direction="row" spacing={1} sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
                        <Typography sx={{ fontSize: 13.5, fontWeight: 600 }}>Complemento {optionIndex + 1}</Typography>
                        <IconButton
                          aria-label="Remover complemento"
                          color="error"
                          onClick={() => removeOption(groupIndex, optionIndex)}
                          disabled={group.options.length <= 1}
                        >
                          <DeleteOutlineOutlinedIcon fontSize="small" />
                        </IconButton>
                      </Stack>

                      <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', lg: 'minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr)' } }}>
                        <TextField
                          label="Nome do complemento"
                          value={option.name}
                          onChange={(event) => updateOption(groupIndex, optionIndex, (current) => ({ ...current, name: event.target.value }))}
                          error={Boolean(firstFieldError(fieldErrors, `option_groups.${groupIndex}.options.${optionIndex}.name`))}
                          helperText={firstFieldError(fieldErrors, `option_groups.${groupIndex}.options.${optionIndex}.name`)}
                        />
                        <TextField
                          label="Preço adicional"
                          type="number"
                          value={option.price}
                          onChange={(event) => updateOption(groupIndex, optionIndex, (current) => ({ ...current, price: event.target.value }))}
                          error={Boolean(firstFieldError(fieldErrors, `option_groups.${groupIndex}.options.${optionIndex}.price`))}
                          helperText={firstFieldError(fieldErrors, `option_groups.${groupIndex}.options.${optionIndex}.price`)}
                          slotProps={{
                            input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
                            htmlInput: { min: 0, step: '0.01' },
                          }}
                        />
                        <TextField
                          label="Ordem"
                          type="number"
                          value={option.sort_order}
                          onChange={(event) => updateOption(groupIndex, optionIndex, (current) => ({ ...current, sort_order: event.target.value }))}
                          error={Boolean(firstFieldError(fieldErrors, `option_groups.${groupIndex}.options.${optionIndex}.sort_order`))}
                          helperText={firstFieldError(fieldErrors, `option_groups.${groupIndex}.options.${optionIndex}.sort_order`)}
                          slotProps={{ htmlInput: { min: 0, step: '1' } }}
                        />
                      </Box>

                      <TextField
                        label="Descrição do complemento"
                        value={option.description}
                        onChange={(event) => updateOption(groupIndex, optionIndex, (current) => ({ ...current, description: event.target.value }))}
                        error={Boolean(firstFieldError(fieldErrors, `option_groups.${groupIndex}.options.${optionIndex}.description`))}
                        helperText={firstFieldError(fieldErrors, `option_groups.${groupIndex}.options.${optionIndex}.description`)}
                        fullWidth
                      />

                      <FormControlLabel
                        control={
                          <Switch
                            checked={option.is_available}
                            onChange={(event) => updateOption(groupIndex, optionIndex, (current) => ({ ...current, is_available: event.target.checked }))}
                          />
                        }
                        label="Complemento disponível"
                      />
                    </Box>
                  ))}

                  <Box>
                    <IconButton
                      aria-label="Adicionar complemento"
                      color="primary"
                      onClick={() => addOption(groupIndex)}
                      sx={{ width: UI_SIZE.iconButton, height: UI_SIZE.iconButton, borderRadius: UI_RADIUS.md }}
                    >
                      <AddCircleOutlineOutlinedIcon />
                    </IconButton>
                  </Box>
                </Stack>
              </Box>
            ))}

            <Box>
              <IconButton
                aria-label="Adicionar grupo"
                color="primary"
                onClick={addOptionGroup}
                sx={{ width: UI_SIZE.iconButton, height: UI_SIZE.iconButton, borderRadius: UI_RADIUS.md }}
              >
                <AddCircleOutlineOutlinedIcon />
              </IconButton>
            </Box>
          </Stack>
        </AccordionDetails>
      </Accordion>

      <Accordion
        variant="outlined"
        sx={{ mt: 2, ...SOFT_PANEL_SX, '&::before': { display: 'none' } }}
      >
        <AccordionSummary expandIcon={<ExpandMoreIcon />}>
          <Typography sx={{ fontSize: 14.5, fontWeight: 600 }}>Fiscal</Typography>
        </AccordionSummary>
        <AccordionDetails>
          <Stack spacing={2}>
            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
              Preencha os dados fiscais do produto para preparar a empresa para regras tributárias e emissão futura.
            </Typography>

            <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'repeat(2, minmax(0, 1fr))' }, gap: 2 }}>
              <TextField
                label="NCM"
                value={form.ncm}
                onChange={(event) => updateField('ncm', event.target.value.replace(/\D/g, '').slice(0, 8))}
                error={Boolean(fieldErrors.ncm)}
                helperText={fieldErrors.ncm?.[0] ?? '8 dígitos.'}
                inputMode="numeric"
                slotProps={{ htmlInput: { maxLength: 8 } }}
              />
              <TextField
                label="CEST"
                value={form.cest}
                onChange={(event) => updateField('cest', event.target.value.slice(0, 10))}
                error={Boolean(fieldErrors.cest)}
                helperText={fieldErrors.cest?.[0] ?? 'Opcional.'}
                slotProps={{ htmlInput: { maxLength: 10 } }}
              />
              <TextField
                select
                label="Origem da mercadoria"
                value={form.origin}
                onChange={(event) => updateField('origin', event.target.value)}
                error={Boolean(fieldErrors.origin)}
                helperText={fieldErrors.origin?.[0] ?? 'Código fiscal de origem do item.'}
              >
                <MenuItem value="">Não definido</MenuItem>
                <MenuItem value="0">0 - Nacional</MenuItem>
                <MenuItem value="1">1 - Estrangeira importação direta</MenuItem>
                <MenuItem value="2">2 - Estrangeira adquirida no mercado interno</MenuItem>
                <MenuItem value="3">3 - Nacional com conteúdo de importação acima de 40%</MenuItem>
                <MenuItem value="4">4 - Nacional produzida conforme PPB</MenuItem>
                <MenuItem value="5">5 - Nacional com conteúdo de importação até 40%</MenuItem>
                <MenuItem value="6">6 - Estrangeira importação direta sem similar nacional</MenuItem>
                <MenuItem value="7">7 - Estrangeira mercado interno sem similar nacional</MenuItem>
                <MenuItem value="8">8 - Nacional com conteúdo de importação acima de 70%</MenuItem>
              </TextField>
              <TextField
                label="CFOP padrão"
                value={form.default_cfop}
                onChange={(event) => updateField('default_cfop', event.target.value.slice(0, 10))}
                error={Boolean(fieldErrors.default_cfop)}
                helperText={fieldErrors.default_cfop?.[0] ?? 'Ex.: 5102'}
                slotProps={{ htmlInput: { maxLength: 10 } }}
              />
              <TextField
                label="CSOSN / CST"
                value={form.csosn_cst}
                onChange={(event) => updateField('csosn_cst', event.target.value.slice(0, 10))}
                error={Boolean(fieldErrors.csosn_cst)}
                helperText={fieldErrors.csosn_cst?.[0] ?? 'O significado depende do regime tributário da empresa.'}
                slotProps={{ htmlInput: { maxLength: 10 } }}
              />
            </Box>
          </Stack>
        </AccordionDetails>
      </Accordion>

      <Accordion
        variant="outlined"
        sx={{ mt: 2, ...SOFT_PANEL_SX, '&::before': { display: 'none' } }}
      >
        <AccordionSummary expandIcon={<ExpandMoreIcon />}>
          <Typography sx={{ fontSize: 14.5, fontWeight: 600 }}>Avançado (disponibilidade e custos)</Typography>
        </AccordionSummary>
        <AccordionDetails>
          <Stack spacing={2}>
            <TextField
              label="Taxa de acréscimo (%)"
              type="number"
              value={form.surcharge_rate}
              onChange={(event) => updateField('surcharge_rate', event.target.value)}
              error={Boolean(fieldErrors.surcharge_rate)}
              helperText={fieldErrors.surcharge_rate?.[0]}
              slotProps={{ htmlInput: { min: 0, step: '0.01' } }}
              sx={{ maxWidth: { sm: 240 } }}
            />

            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
              <FormControlLabel
                control={
                  <Switch
                    checked={form.is_lot_controlled}
                    onChange={(event) => updateField('is_lot_controlled', event.target.checked)}
                  />
                }
                label="Controla lote"
              />
              <FormControlLabel
                control={
                  <Switch
                    checked={form.is_expiry_controlled}
                    onChange={(event) => updateField('is_expiry_controlled', event.target.checked)}
                  />
                }
                label="Controla validade"
              />
              <FormControlLabel
                control={
                  <Switch
                    checked={form.is_serial_controlled}
                    onChange={(event) => updateField('is_serial_controlled', event.target.checked)}
                  />
                }
                label="Controla série"
              />
            </Stack>

            <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'repeat(4, minmax(0, 1fr))' }, gap: 2 }}>
              <TextField
                label="Disponibilidade mínima"
                type="number"
                value={form.min_stock}
                onChange={(event) => updateField('min_stock', event.target.value)}
                error={Boolean(fieldErrors.min_stock)}
                helperText={fieldErrors.min_stock?.[0]}
                slotProps={{ htmlInput: { min: 0, step: '0.001' } }}
              />
              <TextField
                label="Disponibilidade máxima"
                type="number"
                value={form.max_stock}
                onChange={(event) => updateField('max_stock', event.target.value)}
                error={Boolean(fieldErrors.max_stock)}
                helperText={fieldErrors.max_stock?.[0]}
                slotProps={{ htmlInput: { min: 0, step: '0.001' } }}
              />
              <TextField
                label="Ponto de reposição"
                type="number"
                value={form.reorder_point}
                onChange={(event) => updateField('reorder_point', event.target.value)}
                error={Boolean(fieldErrors.reorder_point)}
                helperText={fieldErrors.reorder_point?.[0]}
                slotProps={{ htmlInput: { min: 0, step: '0.001' } }}
              />
              <TextField
                label="Qtd. de reposição"
                type="number"
                value={form.reorder_qty}
                onChange={(event) => updateField('reorder_qty', event.target.value)}
                error={Boolean(fieldErrors.reorder_qty)}
                helperText={fieldErrors.reorder_qty?.[0]}
                slotProps={{ htmlInput: { min: 0, step: '0.001' } }}
              />
            </Box>

            <TextField
              label="Último custo de compra"
              type="number"
              value={form.last_purchase_cost}
              onChange={(event) => updateField('last_purchase_cost', event.target.value)}
              error={Boolean(fieldErrors.last_purchase_cost)}
              helperText={fieldErrors.last_purchase_cost?.[0]}
              slotProps={{
                input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
                htmlInput: { min: 0, step: '0.01' },
              }}
              sx={{ maxWidth: { sm: 240 } }}
            />
          </Stack>
        </AccordionDetails>
      </Accordion>

      {can(ACCESS.productsUpdate) && (
        <Alert severity="info" sx={{ mt: 2 }}>
          Preços por categoria de cliente foram descontinuados nesta migração e não fazem mais parte do cadastro do produto.
        </Alert>
      )}
    </CrudFormShell>
  )
}
