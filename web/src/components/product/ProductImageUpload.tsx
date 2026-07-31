import { ImageUploadField } from '../shared/ImageUploadField'

interface ProductImageUploadProps {
  /** URL da imagem já salva (modo edição) — usada como preview até o usuário escolher um arquivo novo. */
  existingImageUrl: string | null
  onFileSelected: (file: File | null) => void
}

export function ProductImageUpload({ existingImageUrl, onFileSelected }: ProductImageUploadProps) {
  return <ImageUploadField label="Imagem" existingImageUrl={existingImageUrl} onFileSelected={onFileSelected} />
}
