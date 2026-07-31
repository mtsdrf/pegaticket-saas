<?php

namespace App\Http\Controllers\Accounting;

use App\DTOs\Accounting\CreateAccountingMessageDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\CreateAccountingMessageRequest;
use App\Http\Resources\Accounting\AccountingMessageResource;
use App\Services\Accounting\AccountingMessageService;
use App\Services\APIResponse;

/**
 * Lado do CONTADOR da central de pendências. O vínculo aprovado já foi
 * resolvido por ResolveAccountingTenant (app('accounting_office_tenant')).
 */
class AccountingMessageController extends Controller
{
    public function __construct(
        private AccountingMessageService $service
    ) {
    }

    public function index()
    {
        $link = app('accounting_office_tenant');

        return APIResponse::success(
            AccountingMessageResource::collection($this->service->listForLink($link)),
            __('messages.accounting_message.list')
        );
    }

    public function store(CreateAccountingMessageRequest $request)
    {
        $link = app('accounting_office_tenant');

        $message = $this->service->create(
            $link,
            CreateAccountingMessageDTO::fromArray($request->validated()),
            AccountingMessageService::SENDER_OFFICE,
            null,
            $request->file('attachment')
        );

        return APIResponse::success(
            new AccountingMessageResource($message),
            __('messages.accounting_message.sent'),
            201
        );
    }
}
