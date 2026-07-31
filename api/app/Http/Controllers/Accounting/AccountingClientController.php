<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\UpdateAccountingClientFiscalRequest;
use App\Http\Requests\Client\ListClientRequest;
use App\Http\Resources\Client\ClientResource;
use App\Models\AuditLog;
use App\Models\Client\Client;
use App\Services\APIResponse;
use App\Support\BrazilDocument;

class AccountingClientController extends Controller
{
    public function index(ListClientRequest $request)
    {
        $tenantId = app('tenant_id');
        $validated = $request->validated();

        $query = Client::query()
            ->with(['endereco.estado', 'endereco.cidade', 'endereco.bairro', 'diaIdeal', 'periodoIdeal', 'categories'])
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        if (!empty($validated['name'])) {
            $query->where('name', 'like', '%' . $validated['name'] . '%');
        }

        if (!empty($validated['phone_primary'])) {
            $query->where('phone_primary', 'like', '%' . $validated['phone_primary'] . '%');
        }

        if (!empty($validated['cidade_name'])) {
            $query->whereHas('endereco.cidade', fn($q) => $q->where('name', 'like', '%' . $validated['cidade_name'] . '%'));
        }

        if (!empty($validated['cidade_uuid'])) {
            $query->whereHas('endereco.cidade', fn($q) => $q->where('uuid', $validated['cidade_uuid']));
        }

        if (!empty($validated['bairro_uuid'])) {
            $query->whereHas('endereco.bairro', fn($q) => $q->where('uuid', $validated['bairro_uuid']));
        }

        if (!empty($validated['dia_ideal_uuid'])) {
            $query->whereHas('diaIdeal', fn($q) => $q->where('uuid', $validated['dia_ideal_uuid']));
        }

        if (!empty($validated['periodo_ideal_uuid'])) {
            $query->whereHas('periodoIdeal', fn($q) => $q->where('uuid', $validated['periodo_ideal_uuid']));
        }

        if (array_key_exists('is_trusted', $validated) && $validated['is_trusted'] !== null) {
            $query->where('is_trusted', filter_var($validated['is_trusted'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('is_active', $validated) && $validated['is_active'] !== null) {
            $query->where('is_active', filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($validated['q'])) {
            $term = $validated['q'];
            $query->where(function ($sub) use ($term) {
                $sub->where('name', 'like', '%' . $term . '%')
                    ->orWhere('phone_primary', 'like', '%' . $term . '%')
                    ->orWhereHas('endereco.cidade', fn($q) => $q->where('name', 'like', '%' . $term . '%'));
            });
        }

        $list = $query->orderBy('name')->paginate((int) ($validated['per_page'] ?? 15));

        return APIResponse::success(
            ClientResource::collection($list),
            __('messages.client.list'),
            200,
            [
                'pagination' => [
                    'current_page' => $list->currentPage(),
                    'per_page' => $list->perPage(),
                    'total' => $list->total(),
                    'last_page' => $list->lastPage(),
                ]
            ]
        );
    }

    public function updateFiscal(UpdateAccountingClientFiscalRequest $request, Client $client)
    {
        if ((int) $client->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }

        $data = [];
        $validated = $request->validated();

        if (array_key_exists('cpf_cnpj', $validated)) {
            $data['cpf_cnpj'] = BrazilDocument::normalizeCpfOrCnpj($validated['cpf_cnpj'] ?? null);
        }
        if (array_key_exists('ie', $validated)) {
            $data['ie'] = $validated['ie'];
        }
        if (array_key_exists('ie_indicator', $validated)) {
            $data['ie_indicator'] = $validated['ie_indicator'];
        }

        $client->fill($data);
        $client->save();
        $client->load(['endereco.estado', 'endereco.cidade', 'endereco.bairro', 'diaIdeal', 'periodoIdeal', 'categories']);

        $office = accounting_office();

        AuditLog::recordForNonUser('accounting_office.updated_client_fiscal', [
            'accounting_office_id' => $office?->id,
            'accounting_office_uuid' => $office?->uuid,
            'tenant_id' => app('tenant_id'),
            'client_uuid' => $client->uuid,
            'changed_fields' => array_keys($validated),
        ]);

        return APIResponse::success(
            new ClientResource($client),
            __('messages.client.updated')
        );
    }
}
