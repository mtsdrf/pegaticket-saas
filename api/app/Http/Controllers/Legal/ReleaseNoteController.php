<?php

namespace App\Http\Controllers\Legal;

use App\DTOs\Legal\CreateReleaseNoteDTO;
use App\DTOs\Legal\UpdateReleaseNoteDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Legal\StoreReleaseNoteRequest;
use App\Http\Requests\Legal\UpdateReleaseNoteRequest;
use App\Http\Resources\Legal\ReleaseNoteResource;
use App\Models\Legal\ReleaseNote;
use App\Services\APIResponse;
use App\Services\Legal\ReleaseNoteService;
use Illuminate\Http\Request;

class ReleaseNoteController extends Controller
{
    public function __construct(
        private ReleaseNoteService $service
    ) {
    }

    /**
     * Últimas N release notes publicadas — 100% leitura, sem perm dedicada
     * (é conteúdo informativo pra qualquer usuário autenticado, mesmo
     * espírito de GET /legal-documents/{type}, mas exige jwt porque não
     * há motivo pra expor publicamente antes do usuário logar).
     */
    public function index(Request $request)
    {
        $limit = (int) $request->get('limit', 10);
        $notes = $this->service->latest(min(max($limit, 1), 50));

        return APIResponse::success(
            ReleaseNoteResource::collection($notes),
            __('messages.release_note.list')
        );
    }

    public function store(StoreReleaseNoteRequest $request)
    {
        $dto = CreateReleaseNoteDTO::fromArray($request->validated());
        $note = $this->service->create($dto);

        return APIResponse::success(
            new ReleaseNoteResource($note),
            __('messages.release_note.created'),
            201
        );
    }

    public function update(UpdateReleaseNoteRequest $request, ReleaseNote $releaseNote)
    {
        $dto = UpdateReleaseNoteDTO::fromArray($request->validated());
        $releaseNote = $this->service->update($releaseNote, $dto);

        return APIResponse::success(
            new ReleaseNoteResource($releaseNote),
            __('messages.release_note.updated')
        );
    }

    public function destroy(ReleaseNote $releaseNote)
    {
        $this->service->delete($releaseNote);

        return APIResponse::success(
            null,
            __('messages.release_note.deleted'),
            204
        );
    }
}
