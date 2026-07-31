<?php

namespace App\Services\Privacy;

use App\DTOs\Privacy\CreatePrivacyRequestDTO;
use App\DTOs\Privacy\UpdatePrivacyRequestDTO;
use App\Models\AuditLog;
use App\Models\Privacy\PrivacyRequest;
use App\Repositories\Contracts\PrivacyRequestRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PrivacyRequestService
{
    public function __construct(
        private PrivacyRequestRepositoryInterface $repository,
    ) {
    }

    public function listForTenant(int $tenantId): Collection
    {
        return $this->repository->listForTenant($tenantId);
    }

    public function create(int $tenantId, CreatePrivacyRequestDTO $dto): PrivacyRequest
    {
        return DB::transaction(function () use ($tenantId, $dto) {
            $request = $this->repository->create([
                'tenant_id' => $tenantId,
                'requested_by_user_id' => Auth::id(),
                'requester_name' => $dto->requesterName,
                'requester_email' => $dto->requesterEmail,
                'requester_role' => $dto->requesterRole,
                'request_type' => $dto->requestType,
                'channel' => $dto->channel,
                'status' => PrivacyRequest::STATUS_OPEN,
                'subject' => $dto->subject,
                'description' => $dto->description,
                'requested_at' => now(),
            ]);

            AuditLog::record('privacy_request_created', $request, [
                'tenant_id' => $tenantId,
                'request_type' => $request->request_type,
                'status' => $request->status,
            ]);

            return $request;
        });
    }

    public function update(int $tenantId, string $uuid, UpdatePrivacyRequestDTO $dto): PrivacyRequest
    {
        $request = $this->repository->findForTenantByUuid($tenantId, $uuid);

        if (!$request) {
            throw (new ModelNotFoundException())->setModel(PrivacyRequest::class);
        }

        return DB::transaction(function () use ($request, $dto, $tenantId) {
            $resolvedAt = in_array($dto->status, [PrivacyRequest::STATUS_COMPLETED, PrivacyRequest::STATUS_REJECTED], true)
                ? now()
                : null;

            $updated = $this->repository->update($request, [
                'status' => $dto->status,
                'resolution_notes' => $dto->resolutionNotes,
                'resolved_at' => $resolvedAt,
            ]);

            AuditLog::record('privacy_request_updated', $updated, [
                'tenant_id' => $tenantId,
                'status' => $updated->status,
            ]);

            return $updated;
        });
    }
}
