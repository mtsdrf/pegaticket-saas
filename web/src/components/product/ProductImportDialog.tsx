import CheckCircleOutlineIcon from '@mui/icons-material/CheckCircleOutlined'
import ErrorOutlineIcon from '@mui/icons-material/ErrorOutlined'
import FileDownloadOutlinedIcon from '@mui/icons-material/FileDownloadOutlined'
import UploadFileOutlinedIcon from '@mui/icons-material/UploadFileOutlined'
import {
  Alert,
  Box,
  Button,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material'
import CloseIcon from '@mui/icons-material/Close'
import { useRef, useState } from 'react'
import * as productImportService from '../../services/productImportService'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type { ProductImportCommitResult, ProductImportPreviewResult } from '../../types/productImport'
import { formatCurrency } from '../../utils/format'

interface ProductImportDialogProps {
  open: boolean
  onClose: () => void
  /** Chamado depois de um commit com pelo menos 1 produto criado — a lista de produtos deve recarregar. */
  onImported: () => void
}

type Step = 'upload' | 'preview' | 'result'

export function ProductImportDialog({ open, onClose, onImported }: ProductImportDialogProps) {
  const fileInputRef = useRef<HTMLInputElement | null>(null)
  const [step, setStep] = useState<Step>('upload')
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [preview, setPreview] = useState<ProductImportPreviewResult | null>(null)
  const [result, setResult] = useState<ProductImportCommitResult | null>(null)

  function reset() {
    setStep('upload')
    setIsLoading(false)
    setError(null)
    setPreview(null)
    setResult(null)
    if (fileInputRef.current) fileInputRef.current.value = ''
  }

  function handleClose() {
    if (isLoading) return
    const hadImportedSomething = result !== null && result.created_count > 0
    reset()
    onClose()
    if (hadImportedSomething) onImported()
  }

  async function handleFileSelected(file: File) {
    setError(null)
    setIsLoading(true)
    try {
      const result = await productImportService.previewProductImport(file)
      setPreview(result)
      setStep('preview')
    } catch (err) {
      setError(
        getApiErrorMessage(
          err,
          'Não foi possível ler o arquivo enviado. Confira se é um CSV válido e tente novamente.',
        ),
      )
    } finally {
      setIsLoading(false)
    }
  }

  async function handleConfirmImport() {
    if (!preview) return
    setError(null)
    setIsLoading(true)

    const validRows = preview.rows
      .filter((row) => row.status === 'valid')
      .map((row) => ({
        nome: row.nome,
        categoria: row.categoria,
        tipo: row.tipo,
        preco: row.preco,
        descricao: row.descricao,
        sku: row.sku,
        disponivel: row.disponivel,
      }))

    try {
      const commitResult = await productImportService.commitProductImport(validRows)
      setResult(commitResult)
      setStep('result')
    } catch (err) {
      setError(
        getApiErrorMessage(err, 'Não foi possível concluir a importação agora. Tente novamente.'),
      )
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <Dialog open={open} onClose={handleClose} maxWidth="md" fullWidth>
      <DialogTitle sx={{ fontWeight: 600, display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 1 }}>
        Importar produtos (CSV)
        <IconButton onClick={handleClose} disabled={isLoading} size="small" aria-label="Fechar">
          <CloseIcon fontSize="small" />
        </IconButton>
      </DialogTitle>

      <DialogContent dividers>
        {step === 'upload' && (
          <Stack spacing={2.5} sx={{ py: 1 }}>
            <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)' }}>
              Envie um arquivo CSV com as colunas <strong>nome, categoria, tipo, preco, descricao, sku,
              disponivel</strong> (nome, tipo e preco são obrigatórios). Se o tipo informado já existir, a
              categoria da linha é ignorada; se não existir, a categoria é obrigatória e será criada
              automaticamente. Limite de 2000 linhas por arquivo.
            </Typography>

            <Button
              variant="text"
              startIcon={<FileDownloadOutlinedIcon />}
              component="a"
              href="/modelo-importacao-produtos.csv"
              download
              sx={{ alignSelf: 'flex-start', minHeight: 44 }}
            >
              Baixar modelo CSV
            </Button>

            {error && (
              <Alert severity="error" variant="outlined" onClose={() => setError(null)}>
                {error}
              </Alert>
            )}

            <input
              ref={fileInputRef}
              type="file"
              accept=".csv,.txt,text/csv"
              hidden
              onChange={(event) => {
                const file = event.target.files?.[0]
                if (file) void handleFileSelected(file)
              }}
            />

            <Box
              sx={{
                ...SOFT_PANEL_SX,
                borderStyle: 'dashed',
                p: { xs: 3, sm: 4 },
                textAlign: 'center',
              }}
            >
              {isLoading ? (
                <Stack spacing={1.5} sx={{ alignItems: 'center' }}>
                  <CircularProgress size={28} />
                  <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)' }}>Lendo arquivo…</Typography>
                </Stack>
              ) : (
                <Stack spacing={1.5} sx={{ alignItems: 'center' }}>
                  <UploadFileOutlinedIcon sx={{ fontSize: 36, color: 'var(--mk-muted)' }} />
                  <Button
                    variant="contained"
                    onClick={() => fileInputRef.current?.click()}
                    sx={{ minHeight: 44 }}
                  >
                    Selecionar arquivo CSV
                  </Button>
                </Stack>
              )}
            </Box>
          </Stack>
        )}

        {step === 'preview' && preview && (
          <Stack spacing={2}>
            <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap' }}>
              <Chip
                icon={<CheckCircleOutlineIcon />}
                label={`${preview.valid_count} válida${preview.valid_count === 1 ? '' : 's'}`}
                color="success"
                variant="outlined"
                size="small"
              />
              <Chip
                icon={<ErrorOutlineIcon />}
                label={`${preview.error_count} com erro`}
                color={preview.error_count > 0 ? 'error' : 'default'}
                variant="outlined"
                size="small"
              />
              <Chip label={`${preview.total} linha${preview.total === 1 ? '' : 's'} no total`} size="small" />
            </Stack>

            {error && (
              <Alert severity="error" variant="outlined" onClose={() => setError(null)}>
                {error}
              </Alert>
            )}

            {preview.valid_count === 0 && (
              <Alert severity="warning" variant="outlined">
                Nenhuma linha válida encontrada. Corrija o arquivo e envie novamente.
              </Alert>
            )}

            <Box sx={{ overflowX: 'auto' }}>
              <TableContainer sx={{ maxHeight: 360, minWidth: 640 }}>
                <Table size="small" stickyHeader>
                  <TableHead>
                    <TableRow>
                      <TableCell>Linha</TableCell>
                      <TableCell>Nome</TableCell>
                      <TableCell>Tipo</TableCell>
                      <TableCell>Categoria</TableCell>
                      <TableCell>Preço</TableCell>
                      <TableCell>Status / motivo</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {preview.rows.map((row) => (
                      <TableRow
                        key={row.line}
                        sx={
                          row.status === 'error'
                            ? { bgcolor: 'color-mix(in srgb, var(--mk-danger) 8%, transparent)' }
                            : undefined
                        }
                      >
                        <TableCell>{row.line}</TableCell>
                        <TableCell>{row.nome || '—'}</TableCell>
                        <TableCell>
                          {row.tipo || '—'}
                          {row.type_will_be_created && (
                            <Chip label="novo" size="small" sx={{ ml: 0.5, height: 18, fontSize: 11 }} />
                          )}
                        </TableCell>
                        <TableCell>
                          {row.categoria || '—'}
                          {row.category_will_be_created && (
                            <Chip label="novo" size="small" sx={{ ml: 0.5, height: 18, fontSize: 11 }} />
                          )}
                        </TableCell>
                        <TableCell>{row.preco !== null ? formatCurrency(row.preco) : '—'}</TableCell>
                        <TableCell>
                          {row.status === 'valid' ? (
                            <Chip label="Válida" color="success" size="small" variant="outlined" />
                          ) : (
                            <Stack spacing={0.25}>
                              {row.errors.map((message, index) => (
                                <Typography key={index} sx={{ fontSize: 12.5, color: 'var(--mk-danger)' }}>
                                  {message}
                                </Typography>
                              ))}
                            </Stack>
                          )}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
            </Box>
          </Stack>
        )}

        {step === 'result' && result && (
          <Stack spacing={2} sx={{ py: 1 }}>
            <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1.5 }}>
              <CheckCircleOutlineIcon sx={{ color: 'var(--mk-success, #2e7d32)', fontSize: 32 }} />
              <Box>
                <Typography sx={{ fontSize: 16, fontWeight: 600, color: 'var(--mk-text)' }}>
                  {result.created_count} produto{result.created_count === 1 ? '' : 's'} importado
                  {result.created_count === 1 ? '' : 's'} com sucesso
                </Typography>
                <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>
                  {result.categories_created_count} categoria{result.categories_created_count === 1 ? '' : 's'} nova
                  {result.categories_created_count === 1 ? '' : 's'} e {result.types_created_count} tipo
                  {result.types_created_count === 1 ? '' : 's'} novo{result.types_created_count === 1 ? '' : 's'}
                  {result.skipped_count > 0
                    ? ` • ${result.skipped_count} linha${result.skipped_count === 1 ? '' : 's'} ignorada${result.skipped_count === 1 ? '' : 's'}`
                    : ''}
                  .
                </Typography>
              </Box>
            </Box>
          </Stack>
        )}
      </DialogContent>

      <DialogActions sx={{ px: 3, py: 2, gap: 1 }}>
        {step === 'preview' && (
          <Button onClick={reset} disabled={isLoading} color="inherit" sx={{ mr: 'auto' }}>
            Escolher outro arquivo
          </Button>
        )}
        {step === 'result' ? (
          <Button onClick={handleClose} variant="contained" sx={{ minHeight: 44 }}>
            Concluir
          </Button>
        ) : (
          <>
            <Button onClick={handleClose} disabled={isLoading} color="inherit">
              Cancelar
            </Button>
            {step === 'preview' && (
              <Button
                onClick={() => void handleConfirmImport()}
                disabled={isLoading || !preview || preview.valid_count === 0}
                variant="contained"
                startIcon={isLoading ? <CircularProgress size={16} color="inherit" /> : undefined}
                sx={{ minHeight: 44 }}
              >
                {isLoading ? 'Importando…' : `Confirmar importação (${preview?.valid_count ?? 0})`}
              </Button>
            )}
          </>
        )}
      </DialogActions>
    </Dialog>
  )
}
