const DEFAULT_EVENT_COVER_URL = '/evento_padrao.png'

export function resolveEventCoverImageUrl(coverImageUrl: string | null | undefined): string {
  return typeof coverImageUrl === 'string' && coverImageUrl.trim() !== '' ? coverImageUrl : DEFAULT_EVENT_COVER_URL
}
