/** "Ana Paula" -> "AP", "Ana" -> "A", "" -> "" — sempre maiúsculas. */
export function getInitials(fullName: string): string {
  const words = fullName.trim().split(/\s+/).filter(Boolean)

  if (words.length === 0) return ''
  if (words.length === 1) return words[0].charAt(0).toUpperCase()

  return (words[0].charAt(0) + words[1].charAt(0)).toUpperCase()
}
