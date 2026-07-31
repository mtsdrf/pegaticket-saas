<?php

namespace App\Services\Pdv;

use App\DTOs\Pdv\SetOperatorPinDTO;
use App\Events\Pdv\OperatorPinSet;
use App\Events\Pdv\OperatorSessionResolved;
use App\Exceptions\Pdv\InvalidPinException;
use App\Models\Pdv\UserPin;
use App\Models\Tenant\TenantUser;
use App\Models\User\User;
use App\Repositories\Contracts\UserPinRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * PIN individual por operador (roadmap A4, item 15) — camada de
 * identificação DENTRO de uma sessão de staff já autenticada via JWT.
 * `resolveOperator()` NUNCA re-autentica (não gera token, não abre sessão
 * nova); só devolve qual operador do tenant tem aquele PIN, para o
 * frontend gravar como responsável nas próximas ações do terminal
 * (ex.: `operator_uuid` em CreatePdvSaleDTO -> orders.operated_by).
 */
class UserPinService
{
    public function __construct(
        private UserPinRepositoryInterface $repository
    ) {
    }

    /**
     * Cadastra ou troca o PIN do PRÓPRIO usuário autenticado, no tenant
     * atual. Exige que o usuário seja de fato staff ativo do tenant
     * (TenantUser ativo) — não é uma perm de functionality porque é uma
     * ação sobre o próprio operador, não sobre o recurso `pdv`.
     */
    public function setOwnPin(int $tenantId, int $userId, SetOperatorPinDTO $dto): UserPin
    {
        $isTenantStaff = TenantUser::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->exists();

        if (!$isTenantStaff) {
            abort(404);
        }

        $pinHash = self::hashPin($dto->pin);

        if ($this->repository->hashExistsForTenant($tenantId, $pinHash, $userId)) {
            throw new InvalidPinException(__('messages.operator_pin.pin_in_use'));
        }

        return DB::transaction(function () use ($tenantId, $userId, $pinHash) {
            $existing = $this->repository->findForTenantUser($tenantId, $userId);

            if ($existing) {
                $userPin = $this->repository->update($existing, ['pin_hash' => $pinHash]);
            } else {
                $userPin = $this->repository->create([
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'pin_hash' => $pinHash,
                ]);
            }

            $tenantUuid = \App\Models\Tenant\Tenant::where('id', $tenantId)->value('uuid');
            $userUuid = User::where('id', $userId)->value('uuid');

            event(new OperatorPinSet(
                tenantUuid: (string) $tenantUuid,
                userUuid: (string) $userUuid,
                actorId: (int) Auth::id()
            ));

            return $userPin;
        });
    }

    /**
     * Resolve qual operador do tenant tem este PIN. Lança InvalidPinException
     * se não houver match (sem distinguir "PIN errado" de "nenhum PIN
     * cadastrado" na mensagem, para não vazar enumeração).
     */
    public function resolveOperator(int $tenantId, string $pin): User
    {
        $pinHash = self::hashPin($pin);

        $userPin = $this->repository->findByTenantAndHash($tenantId, $pinHash);

        if ($userPin === null) {
            throw new InvalidPinException(__('messages.operator_pin.invalid_pin'));
        }

        $operator = User::find($userPin->user_id);

        if ($operator === null) {
            throw new InvalidPinException(__('messages.operator_pin.invalid_pin'));
        }

        $tenantUuid = \App\Models\Tenant\Tenant::where('id', $tenantId)->value('uuid');

        event(new OperatorSessionResolved(
            tenantUuid: (string) $tenantUuid,
            operatorUuid: $operator->uuid,
            actorId: (int) Auth::id()
        ));

        return $operator;
    }

    /**
     * Hash determinístico (não bcrypt) — precisa de lookup direto por
     * tenant_id+hash sem saber o usuário previamente. Mesmo padrão de
     * `final_customer_otps.code_hash` (PortalAuthService). Mitigação de
     * força bruta é via throttle da rota + unicidade por tenant (não pela
     * função de hash em si, PIN é baixa entropia por natureza).
     */
    public static function hashPin(string $pin): string
    {
        return hash('sha256', $pin);
    }
}
