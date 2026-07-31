import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type {
  ProductImportCommitResult,
  ProductImportCommitRow,
  ProductImportPreviewResult,
} from '../types/productImport'

export function previewProductImport(file: File): Promise<ProductImportPreviewResult> {
  const formData = new FormData()
  formData.append('file', file)

  return unwrap(
    apiClient.post<ApiSuccess<ProductImportPreviewResult>>('/products/import/preview', formData),
  )
}

export function commitProductImport(rows: ProductImportCommitRow[]): Promise<ProductImportCommitResult> {
  return unwrap(
    apiClient.post<ApiSuccess<ProductImportCommitResult>>('/products/import/commit', { rows }),
  )
}
