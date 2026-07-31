import ImageOutlinedIcon from '@mui/icons-material/ImageOutlined'
import PhotoCameraOutlinedIcon from '@mui/icons-material/PhotoCameraOutlined'
import UploadOutlinedIcon from '@mui/icons-material/UploadOutlined'
import { Alert, Box, Button, Stack, Typography } from '@mui/material'
import { useRef, useState, type ChangeEvent, type ReactNode } from 'react'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { cropImageToSquare } from '../../utils/cropImageToSquare'

const IMAGE_UPLOAD_MAX_SIZE_BYTES = 5 * 1024 * 1024
const IMAGE_UPLOAD_ACCEPTED_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp']

interface ImageUploadFieldProps {
  label: string
  existingImageUrl: string | null
  onFileSelected: (file: File | null) => void
  /** `circle` pra avatar de pessoa, `square` (default) pra imagem de produto/logo. Ignorado quando `renderPreview` é passado. */
  shape?: 'circle' | 'square'
  size?: number
  showFileName?: boolean
  /** Preview customizado (ex.: `UserAvatar` com fallback de iniciais) — recebe a URL atual (arquivo novo ou já salvo). */
  renderPreview?: (displayUrl: string | null) => ReactNode
  maxSizeBytes?: number
  acceptedTypes?: string[]
}

/**
 * Upload de imagem compartilhado por avatar de usuário, imagem de produto e
 * logo de empresa — dois `<input type="file">` escondidos (um deles com
 * `capture="environment"`, que faz o navegador mobile abrir a câmera
 * traseira direto; em desktop sem câmera o atributo é ignorado e cai no
 * seletor de arquivo normal, sem precisar de detecção via JS) disparados por
 * dois botões visíveis ("Tirar foto"/"Escolher da galeria"). Preview e
 * validação de tipo/tamanho ficam centralizados aqui.
 */
export function ImageUploadField({
  label,
  existingImageUrl,
  onFileSelected,
  shape = 'square',
  size = 76,
  showFileName = true,
  renderPreview,
  maxSizeBytes = IMAGE_UPLOAD_MAX_SIZE_BYTES,
  acceptedTypes = IMAGE_UPLOAD_ACCEPTED_TYPES,
}: ImageUploadFieldProps) {
  const cameraInputRef = useRef<HTMLInputElement>(null)
  const galleryInputRef = useRef<HTMLInputElement>(null)
  const [previewUrl, setPreviewUrl] = useState<string | null>(null)
  const [fileName, setFileName] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const displayUrl = previewUrl ?? existingImageUrl
  const acceptAttr = acceptedTypes.join(',')
  const maxSizeMb = Math.round(maxSizeBytes / (1024 * 1024))

  async function handleFile(file: File | undefined | null) {
    setError(null)

    if (!file) {
      onFileSelected(null)
      return
    }

    if (!acceptedTypes.includes(file.type)) {
      setError('Formato não suportado. Use JPG, PNG ou WEBP.')
      return
    }

    if (file.size > maxSizeBytes) {
      setError(`Arquivo muito grande. Tamanho máximo: ${maxSizeMb}MB.`)
      return
    }

    const squareFile = await cropImageToSquare(file)

    setPreviewUrl(URL.createObjectURL(squareFile))
    setFileName(squareFile.name)
    onFileSelected(squareFile)
  }

  function handleInputChange(event: ChangeEvent<HTMLInputElement>) {
    void handleFile(event.target.files?.[0])
    // Permite escolher o mesmo arquivo de novo em seguida (troca de ideia).
    event.target.value = ''
  }

  return (
    <Box>
      <Typography sx={{ fontSize: 13, fontWeight: 500, color: 'var(--mk-text)', mb: 0.75 }}>{label}</Typography>

      <Box sx={{ display: 'flex', alignItems: 'center', gap: 2, flexWrap: 'wrap' }}>
        {renderPreview ? (
          renderPreview(displayUrl)
        ) : (
          <Box
            sx={{
              width: size,
              height: size,
              ...SOFT_PANEL_SX,
              borderRadius: shape === 'circle' ? '50%' : 'var(--mk-radius-md)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              overflow: 'hidden',
              flexShrink: 0,
            }}
          >
            {displayUrl ? (
              <Box component="img" src={displayUrl} alt="" sx={{ width: '100%', height: '100%', objectFit: 'cover' }} />
            ) : (
              <ImageOutlinedIcon sx={{ color: 'var(--mk-muted)' }} />
            )}
          </Box>
        )}

        <Box sx={{ minWidth: 0, flex: { xs: '1 1 auto', sm: '0 1 auto' } }}>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
            <Button
              variant="outlined"
              size="small"
              startIcon={<PhotoCameraOutlinedIcon fontSize="small" />}
              onClick={() => cameraInputRef.current?.click()}
              sx={{ minHeight: 44, width: { xs: '100%', sm: 'auto' } }}
            >
              Tirar foto
            </Button>
            <Button
              variant="outlined"
              size="small"
              startIcon={<UploadOutlinedIcon fontSize="small" />}
              onClick={() => galleryInputRef.current?.click()}
              sx={{ minHeight: 44, width: { xs: '100%', sm: 'auto' } }}
            >
              Escolher da galeria
            </Button>
          </Stack>

          {showFileName && fileName && (
            <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)', mt: 0.5, wordBreak: 'break-all' }}>
              {fileName}
            </Typography>
          )}

          <input
            ref={cameraInputRef}
            type="file"
            accept={acceptAttr}
            capture="environment"
            hidden
            onChange={handleInputChange}
          />
          <input ref={galleryInputRef} type="file" accept={acceptAttr} hidden onChange={handleInputChange} />
        </Box>
      </Box>

      {error && (
        <Alert severity="error" variant="outlined" sx={{ mt: 1.5 }}>
          {error}
        </Alert>
      )}
    </Box>
  )
}
