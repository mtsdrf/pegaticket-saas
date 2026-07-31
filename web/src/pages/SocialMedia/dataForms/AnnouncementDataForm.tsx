import { Stack, TextField } from '@mui/material'
import { useEffect, useState } from 'react'
import { ImageUploadField } from '../../../components/shared/ImageUploadField'
import type { StorySingleContent } from '../../../types/socialMedia'
import { fileToDataUrl } from '../../../utils/remoteImageToDataUrl'

const TITLE_MAX_LENGTH = 80
const BODY_MAX_LENGTH = 300

interface AnnouncementDataFormProps {
  /** Passar diretamente o setter de estado do pai (identidade estável) — ver mesma nota em `ProductDataForm`. */
  onContentChange: (content: StorySingleContent | null) => void
}

/** Formulário de dado do conteúdo "Comunicado livre" — sem fetch, só texto livre + imagem opcional (upload local). */
export function AnnouncementDataForm({ onContentChange }: AnnouncementDataFormProps) {
  const [title, setTitle] = useState('')
  const [body, setBody] = useState('')
  const [imageDataUrl, setImageDataUrl] = useState<string | null>(null)

  function handleFileSelected(file: File | null) {
    if (!file) {
      setImageDataUrl(null)
      return
    }
    fileToDataUrl(file).then(setImageDataUrl)
  }

  useEffect(() => {
    if (!title.trim()) {
      onContentChange(null)
      return
    }

    onContentChange({
      kind: 'single',
      eyebrow: 'Comunicado',
      title: title.trim(),
      description: body.trim() || undefined,
      imageDataUrl,
    })
  }, [title, body, imageDataUrl, onContentChange])

  return (
    <Stack spacing={2}>
      <TextField
        label="Título"
        value={title}
        onChange={(event) => setTitle(event.target.value.slice(0, TITLE_MAX_LENGTH))}
        required
        helperText={`${title.length}/${TITLE_MAX_LENGTH}`}
      />
      <TextField
        label="Texto do comunicado"
        value={body}
        onChange={(event) => setBody(event.target.value.slice(0, BODY_MAX_LENGTH))}
        multiline
        minRows={4}
        helperText={`${body.length}/${BODY_MAX_LENGTH}`}
      />
      <ImageUploadField
        label="Imagem (opcional)"
        existingImageUrl={null}
        onFileSelected={handleFileSelected}
        shape="square"
        size={96}
      />
    </Stack>
  )
}
