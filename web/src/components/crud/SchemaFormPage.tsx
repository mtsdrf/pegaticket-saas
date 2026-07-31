import { Box, FormControlLabel, Switch, TextField } from '@mui/material'
import type { FormEvent } from 'react'
import { CrudFormShell } from './CrudFormShell'
import { LocalAutocomplete } from './LocalAutocomplete'
import type { CrudFieldDef, CrudFormValues } from './schemaFormTypes'
import type { PageHeaderBreadcrumb } from '../layout/PageHeader'

interface SchemaFormPageProps {
  backLabel: string
  backTo: string
  title: string
  subtitle: string
  breadcrumbs?: PageHeaderBreadcrumb[]
  fields: CrudFieldDef[]
  values: CrudFormValues
  onChange: (name: string, value: string | number | boolean) => void
  fieldErrors: Record<string, string[]>
  formError: string | null
  loadError?: string | null
  isLoadingRecord?: boolean
  isSubmitting: boolean
  onSubmit: (event: FormEvent<HTMLFormElement>) => void
}

/**
 * Formulário genérico dirigido por schema declarativo — pra entidades
 * simples (nome + prioridade + ativo, opcionalmente 1-2 selects), onde
 * escrever o JSX à mão em cada módulo seria puro copy-paste (Categoria
 * de produto, Tipo de produto, Local de estoque). Entidades com upload
 * de arquivo, selects em cascata ou lógica condicional (Cliente,
 * Produto) continuam com formulário próprio sobre `CrudFormShell`.
 */
export function SchemaFormPage({
  backLabel,
  backTo,
  title,
  subtitle,
  breadcrumbs,
  fields,
  values,
  onChange,
  fieldErrors,
  formError,
  loadError,
  isLoadingRecord,
  isSubmitting,
  onSubmit,
}: SchemaFormPageProps) {
  return (
    <CrudFormShell
      backLabel={backLabel}
      backTo={backTo}
      title={title}
      subtitle={subtitle}
      breadcrumbs={breadcrumbs}
      loadError={loadError}
      isLoadingRecord={isLoadingRecord}
      formError={formError}
      isSubmitting={isSubmitting}
      onSubmit={onSubmit}
    >
      <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'repeat(2, minmax(0, 1fr))' }, gap: 2, mb: 1 }}>
        {fields.map((field) => {
          const gridColumn = field.half ? undefined : { xs: '1 / -1', sm: '1 / -1' }
          // Campo que ocupa a linha inteira (ex.: "Nome") não deve esticar até
          // a largura toda do container em telas grandes — um input de texto
          // sozinho com >600px de largura fica com aparência ruim/vazia. Só
          // se aplica a quem realmente ocupa a linha inteira; campo `half`
          // já fica naturalmente limitado pela coluna do grid.
          const fullSpanMaxWidth = field.half ? undefined : { sm: 560 }
          const error = Boolean(fieldErrors[field.name]?.[0])
          const helperText = fieldErrors[field.name]?.[0]

          if (field.type === 'switch') {
            return (
              <FormControlLabel
                key={field.name}
                sx={{ gridColumn }}
                control={
                  <Switch
                    checked={Boolean(values[field.name])}
                    onChange={(event) => onChange(field.name, event.target.checked)}
                  />
                }
                label={field.label}
              />
            )
          }

          if (field.type === 'select') {
            const currentValue = String(values[field.name] ?? '')
            return (
              <LocalAutocomplete
                key={field.name}
                sx={{ gridColumn, maxWidth: fullSpanMaxWidth }}
                label={field.label}
                required={field.required}
                error={error}
                helperText={helperText}
                options={field.options ?? []}
                value={field.options?.find((option) => option.value === currentValue) ?? null}
                onChange={(option) => onChange(field.name, option?.value ?? '')}
                getOptionLabel={(option) => option.label}
                getOptionKey={(option) => option.value}
              />
            )
          }

          return (
            <TextField
              key={field.name}
              sx={{ gridColumn, maxWidth: fullSpanMaxWidth }}
              label={field.label}
              type={field.type === 'number' ? 'number' : 'text'}
              value={values[field.name] ?? ''}
              onChange={(event) =>
                onChange(field.name, field.type === 'number' ? event.target.value : event.target.value)
              }
              error={error}
              helperText={helperText}
              required={field.required}
              fullWidth
              slotProps={{
                htmlInput:
                  field.type === 'number'
                    ? { min: field.min, step: field.step ?? 1 }
                    : { maxLength: field.maxLength },
              }}
            />
          )
        })}
      </Box>
    </CrudFormShell>
  )
}
