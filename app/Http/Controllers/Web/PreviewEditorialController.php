<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\EditorialContent;
use App\Services\Editorial\EditorialPreviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PreviewEditorialController extends Controller
{
    public function __construct(
        private readonly EditorialPageController $pageController,
        private readonly EditorialPreviewService $previewService,
    ) {}

    public function show(Request $request, string $uuid): View|Response
    {
        $token = $request->query('token');
        if (! is_string($token) || $token === '') {
            abort(403);
        }

        if (! $this->previewService->validate($uuid, $token)) {
            abort(403);
        }

        $content = EditorialContent::query()
            ->where('uuid', $uuid)
            ->with(['rubric', 'heroMedia', 'authors.avatarMedia', 'company'])
            ->firstOrFail();

        if (! $this->previewService->isPreviewable($content)) {
            abort(403);
        }

        return response($this->pageController->renderShowView($content))
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
