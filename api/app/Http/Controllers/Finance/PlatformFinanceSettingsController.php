<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\UpdatePlatformFinanceSettingsRequest;
use App\Http\Resources\Finance\PlatformFinanceSettingsResource;
use App\Services\APIResponse;
use App\Services\Finance\PlatformFinanceSettingsService;

class PlatformFinanceSettingsController extends Controller
{
    public function __construct(
        private PlatformFinanceSettingsService $service,
    ) {}

    public function show()
    {
        return APIResponse::success(
            new PlatformFinanceSettingsResource($this->service->getCurrent()),
            'Configuracoes financeiras globais carregadas com sucesso.'
        );
    }

    public function update(UpdatePlatformFinanceSettingsRequest $request)
    {
        return APIResponse::success(
            new PlatformFinanceSettingsResource($this->service->update($request->validated())),
            'Configuracoes financeiras globais atualizadas com sucesso.'
        );
    }
}
