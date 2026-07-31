<?php

namespace App\Http\Controllers\Product;

use App\DTOs\Product\CommitProductImportDTO;
use App\Exceptions\Product\InvalidProductImportFileException;
use App\Exceptions\Product\ProductImportLimitExceededException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductImportCommitRequest;
use App\Http\Requests\Product\ProductImportPreviewRequest;
use App\Services\APIResponse;
use App\Services\Product\ProductImportService;
use Illuminate\Support\Facades\Auth;

class ProductImportController extends Controller
{
    public function __construct(
        private ProductImportService $service
    ) {
    }

    public function preview(ProductImportPreviewRequest $request)
    {
        try {
            $result = $this->service->preview($request->file('file'), app('tenant_id'));
        } catch (InvalidProductImportFileException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_IMPORT_FILE');
        } catch (ProductImportLimitExceededException $e) {
            return APIResponse::error($e->getMessage(), 422, 'PRODUCT_IMPORT_LIMIT_EXCEEDED');
        }

        return APIResponse::success($result, __('messages.product_import.preview'));
    }

    public function commit(ProductImportCommitRequest $request)
    {
        $dto = CommitProductImportDTO::fromArray($request->validated());

        try {
            $result = $this->service->commit($dto->rows, app('tenant_id'), Auth::id());
        } catch (ProductImportLimitExceededException $e) {
            return APIResponse::error($e->getMessage(), 422, 'PRODUCT_IMPORT_LIMIT_EXCEEDED');
        }

        return APIResponse::success($result, __('messages.product_import.committed'));
    }
}
