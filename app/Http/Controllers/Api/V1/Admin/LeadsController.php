<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\AssignLeadRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Models\Lead;
use App\Services\AdminOperationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadsController extends Controller
{
    public function __construct(
        private readonly AdminOperationsService $adminOps,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->adminOps->listLeads((int) $request->integer('per_page', 20));

        $leads = collect($paginator->items())->map(fn (Lead $lead) => [
            'id' => $lead->id,
            'utente' => $lead->contact_name,
            'esigenza' => $lead->need_summary,
            'ai_match' => $lead->leadMatches->first()?->metadata['ai_match_label'] ?? null,
            'stato' => $lead->admin_status ?? 'In routing',
            'email' => $lead->contact_email,
            'telefono' => $lead->contact_phone,
            'partner_assegnato' => $lead->leadMatches->first(fn ($m) => $m->assigned_by !== null)?->company?->organization_name,
            'note' => $lead->admin_notes,
            'created_at' => $lead->created_at?->toIso8601String(),
        ])->all();

        return ApiEnvelope::success(
            ['leads' => $leads],
            200,
            [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function show(int $id): JsonResponse
    {
        $detail = $this->adminOps->leadDetail($id);
        $lead = $detail['lead'];

        return ApiEnvelope::success([
            'lead' => $lead,
            'matches' => $detail['matches'],
        ]);
    }

    public function assign(AssignLeadRequest $request, int $id): JsonResponse
    {
        $company = $request->resolveCompany();
        if ($company === null) {
            abort(422, 'Partner non valido.');
        }

        $lead = Lead::query()->findOrFail($id);
        $result = $this->adminOps->assignPartner(
            $lead,
            $company->id,
            $request->user(),
            $request,
        );

        return ApiEnvelope::success([
            'lead' => $result['lead'],
            'assignment' => $result['assignment'],
        ]);
    }

    public function reroute(Request $request, int $id): JsonResponse
    {
        $lead = Lead::query()->findOrFail($id);

        return ApiEnvelope::success($this->adminOps->reroute($lead, $request->user(), $request));
    }
}
