export interface ReleaseNote {
  uuid: string
  title: string
  body: string
  version: string | null
  published_at: string | null
  created_at: string
  updated_at: string
}
