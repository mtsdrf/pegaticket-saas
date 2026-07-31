<?php

namespace App\Http\Controllers\Pdv;

use App\DTOs\Pdv\SetOperatorPinDTO;
use App\Exceptions\Pdv\InvalidPinException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pdv\OperatorSessionRequest;
use App\Http\Requests\Pdv\SetOperatorPinRequest;
use App\Http\Resources\Pdv\OperatorResource;
use App\Services\APIResponse;
use App\Services\Pdv\UserPinService;
use Illuminate\Support\Facades\Auth;

class OperatorPinController extends Controller
{
    public function __construct(
        private UserPinService $service
    ) {
    }

    /**
     * Cadastra/troca o PIN do próprio operador autenticado, no tenant atual.
     */
    public function setOwnPin(SetOperatorPinRequest $request)
    {
        $dto = SetOperatorPinDTO::fromArray($request->validated());

        try {
            $this->service->setOwnPin(app('tenant_id'), (int) Auth::id(), $dto);
        } catch (InvalidPinException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_PIN');
        }

        return APIResponse::success(null, __('messages.operator_pin.pin_set'));
    }

    /**
     * Segunda camada de identificação DENTRO da sessão de staff já aberta
     * (não re-autentica via JWT). Retorna qual operador do tenant tem este
     * PIN, para o frontend usar como `operator_uuid` nas próximas ações do
     * terminal (ex.: venda de PDV).
     */
    public function resolve(OperatorSessionRequest $request)
    {
        try {
            $operator = $this->service->resolveOperator(app('tenant_id'), $request->validated('pin'));
        } catch (InvalidPinException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_PIN');
        }

        return APIResponse::success(
            new OperatorResource($operator),
            __('messages.operator_pin.resolved')
        );
    }
}
