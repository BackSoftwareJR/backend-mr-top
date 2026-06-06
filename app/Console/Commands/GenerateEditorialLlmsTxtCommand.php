<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Editorial\EditorialLlmsTxtService;
use Illuminate\Console\Command;

class GenerateEditorialLlmsTxtCommand extends Command
{
    protected $signature = 'editorial:generate-llms-txt';

    protected $description = 'Generate llms.txt GEO manifest for Wenando editorial';

    public function handle(EditorialLlmsTxtService $llmsTxtService): int
    {
        $llmsTxtService->generate();

        $this->components->info('Generated storage/app/public/editorial/llms.txt');

        return self::SUCCESS;
    }
}
