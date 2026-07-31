<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\UpdateStoreAddressRequest;
use App\Http\Resources\Location\EnderecoResource;
use App\Services\APIResponse;
use App\Services\Storefront\StoreAddressService;

class StoreAddressController extends Controller
{
    public function __construct(
        private StoreAddressService $service
    ) {
    }

    public function show()
    {
        $endereco = $this->service->getForTenant(app('tenant_id'));

        return APIResponse::success(
            $endereco ? new EnderecoResource($endereco) : null,
            __('messages.store_address.show')
        );
    }

    public function update(UpdateStoreAddressRequest $request)
    {
        $endereco = $this->service->updateForTenant(app('tenant_id'), $request->validated());

        return APIResponse::success(
            new EnderecoResource($endereco),
            __('messages.store_address.updated')
        );
    }
}
