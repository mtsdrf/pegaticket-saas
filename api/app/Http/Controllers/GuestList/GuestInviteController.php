<?php

namespace App\Http\Controllers\GuestList;

use App\DTOs\GuestList\RedeemGuestListEntryDTO;
use App\Exceptions\GuestListEntryAlreadyRedeemedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuestList\RedeemGuestListEntryRequest;
use App\Http\Resources\GuestList\GuestInviteResource;
use App\Services\APIResponse;
use App\Services\GuestList\GuestListService;
use App\Services\Security\AntiBotGuardService;

/** Autoatendimento público (sem login) do convite — token individual por convidado. */
class GuestInviteController extends Controller
{
    public function __construct(
        private GuestListService $service,
        private AntiBotGuardService $antiBotGuard,
    ) {}

    public function show(string $token)
    {
        $entry = $this->service->findByToken($token);

        return APIResponse::success(
            new GuestInviteResource($entry),
            __('messages.guest_list.invite_shown')
        );
    }

    public function redeem(RedeemGuestListEntryRequest $request, string $token)
    {
        $this->antiBotGuard->assertHuman(
            $request->validated('website'),
            $request->validated('form_rendered_at'),
            $request->validated('turnstile_token'),
            $request->ip()
        );

        $dto = RedeemGuestListEntryDTO::fromArray($request->validated());

        try {
            $entry = $this->service->redeem($token, $dto);
        } catch (GuestListEntryAlreadyRedeemedException $e) {
            return APIResponse::error($e->getMessage(), 422, 'GUEST_LIST_ENTRY_ALREADY_REDEEMED');
        }

        return APIResponse::success(
            [
                'sale_uuid' => $entry->sale->uuid,
                'tracking_url' => rtrim((string) config('app.frontend_url'), '/').'/rastreio/'.$entry->sale->uuid,
            ],
            __('messages.guest_list.redeemed')
        );
    }
}
