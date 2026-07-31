<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Product;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\Response;

/**
 * Ver App\Http\Controllers\User\UserAvatarController — mesmo padrão.
 */
class ProductImageController extends Controller
{
    public function __construct(
        private MediaStorageService $mediaStorage
    ) {
    }

    public function show(Product $product): Response
    {
        return $this->mediaStorage->publicMediaResponse(
            $product->image_path,
            $product->image_data,
            $product->image_mime,
            'product'
        );
    }
}
