<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2B;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Services\B2bOnboardingService;
use App\Services\ExportService;
use App\Support\CentralLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(
        private readonly ExportService $exportService,
        private readonly B2bOnboardingService $onboardingService,
    ) {}

    public function index(): JsonResponse
    {
        return ApiEnvelope::success([
            'exports' => $this->exportService->availableTypes(),
        ]);
    }

    public function store(Request $request): JsonResponse|StreamedResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:leads,crm,transactions,appointments,profile'],
            'format' => ['required', 'in:csv,json'],
        ]);

        $company = $this->onboardingService->companyForUser($request->user());

        $result = $this->exportService->export(
            $company,
            $validated['type'],
            $validated['format'],
        );

        CentralLog::b2b('export.generated', [
            'company_id' => $company->id,
            'type' => $validated['type'],
            'format' => $validated['format'],
            'row_count' => $result['row_count'],
        ]);

        if ($result['format'] === 'csv' && $result['content'] instanceof StreamedResponse) {
            $result['content']->headers->set(
                'Content-Disposition',
                'attachment; filename="'.$result['filename'].'"',
            );

            return $result['content'];
        }

        return ApiEnvelope::success([
            'filename' => $result['filename'],
            'row_count' => $result['row_count'],
            'rows' => json_decode((string) $result['content'], true, 512, JSON_THROW_ON_ERROR)['rows'] ?? [],
        ]);
    }
}
