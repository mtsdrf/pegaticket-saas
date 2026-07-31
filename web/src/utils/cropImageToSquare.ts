/**
 * Corta o centro da imagem num quadrado (1:1) via canvas — usado por todo
 * upload que tem opção de câmera (`ImageUploadField`), pra imagem nunca sair
 * esticada/cortada de forma diferente dependendo de onde for exibida depois
 * (avatar circular, logo, foto de produto, story). Se `createImageBitmap`/canvas
 * não estiver disponível, retorna o arquivo original sem cortar.
 */
export async function cropImageToSquare(file: File): Promise<File> {
  try {
    const bitmap = await createImageBitmap(file)
    const size = Math.min(bitmap.width, bitmap.height)
    const sx = (bitmap.width - size) / 2
    const sy = (bitmap.height - size) / 2

    const canvas = document.createElement('canvas')
    canvas.width = size
    canvas.height = size
    const ctx = canvas.getContext('2d')
    if (!ctx) return file

    ctx.drawImage(bitmap, sx, sy, size, size, 0, 0, size, size)
    bitmap.close()

    const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, file.type, 0.92))
    if (!blob) return file

    return new File([blob], file.name, { type: file.type })
  } catch {
    return file
  }
}
