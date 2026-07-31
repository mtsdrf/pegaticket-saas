/**
 * Fuzzy simples por subsequência: cada caractere da query precisa aparecer
 * em ordem no texto (case-insensitive), sem exigir contiguidade. Retorna
 * `null` quando não há match; caso contrário um score onde menor é melhor
 * (prioriza match contíguo e no início da string, sem trazer dependência
 * externa pra busca de navegação).
 */
export function fuzzyMatchScore(query: string, text: string): number | null {
  const normalizedQuery = query.trim().toLowerCase()
  if (!normalizedQuery) return 0

  const normalizedText = text.toLowerCase()

  const directIndex = normalizedText.indexOf(normalizedQuery)
  if (directIndex !== -1) {
    return directIndex
  }

  let textIndex = 0
  let gaps = 0
  let firstMatchIndex = -1

  for (const char of normalizedQuery) {
    const foundAt = normalizedText.indexOf(char, textIndex)
    if (foundAt === -1) return null
    if (firstMatchIndex === -1) firstMatchIndex = foundAt
    if (foundAt > textIndex) gaps += foundAt - textIndex
    textIndex = foundAt + 1
  }

  return 1000 + firstMatchIndex + gaps
}
