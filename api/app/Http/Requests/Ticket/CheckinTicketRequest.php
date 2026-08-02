<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ao menos UM identificador precisa vir preenchido: qr_token (leitura de
 * câmera) OU code/sale_uuid/attendee_name/attendee_document (busca
 * manual, combináveis). `required_without_all` em cada um garante que a
 * validação falha só quando TODOS os 5 estão ausentes.
 */
class CheckinTicketRequest extends FormRequest
{
    private const IDENTIFIER_FIELDS = ['qr_token', 'code', 'sale_uuid', 'attendee_name', 'attendee_document'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qr_token' => ['required_without_all:code,sale_uuid,attendee_name,attendee_document', 'nullable', 'string'],
            'code' => ['required_without_all:qr_token,sale_uuid,attendee_name,attendee_document', 'nullable', 'string', 'max:8'],
            'sale_uuid' => ['required_without_all:qr_token,code,attendee_name,attendee_document', 'nullable', 'uuid'],
            'attendee_name' => ['required_without_all:qr_token,code,sale_uuid,attendee_document', 'nullable', 'string', 'max:255'],
            'attendee_document' => ['required_without_all:qr_token,code,sale_uuid,attendee_name', 'nullable', 'string', 'max:32'],
            'event_uuid' => ['nullable', 'uuid'],
            'event_session_uuid' => ['nullable', 'uuid'],
            'allow_reentry' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'gate_name' => ['nullable', 'string', 'max:255'],
            'device_info' => ['nullable', 'string', 'max:255'],
        ];
    }
}
