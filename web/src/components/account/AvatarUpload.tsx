import { ImageUploadField } from '../shared/ImageUploadField'
import { UserAvatar } from '../UserAvatar'

interface AvatarUploadProps {
  name: string
  existingAvatarUrl: string | null
  onFileSelected: (file: File | null) => void
}

/** Upload de foto de perfil com preview circular (iniciais como fallback) — variante do `ImageUploadField` compartilhado pro contexto de avatar de usuário. */
export function AvatarUpload({ name, existingAvatarUrl, onFileSelected }: AvatarUploadProps) {
  return (
    <ImageUploadField
      label="Foto"
      existingImageUrl={existingAvatarUrl}
      onFileSelected={onFileSelected}
      showFileName={false}
      renderPreview={(displayUrl) => <UserAvatar name={name} avatarUrl={displayUrl} size={72} />}
    />
  )
}
