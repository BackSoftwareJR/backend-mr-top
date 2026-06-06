<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Editorial\EditorialSitemapService;
use Illuminate\Console\Command;

class GenerateEditorialSitemapsCommand extends Command
{
    protected $signature = 'editorial:generate-sitemaps';

    protected $description = 'Generate editorial sitemap XML files for published, indexable content';

    public function handle(EditorialSitemapService $sitemapService): int
    {
        $result = $sitemapService->generate();

        $this->components->info(sprintf(
            'Generated %d URL(s) across %d file(s)%s.',
            $result['url_count'],
            count($result['files']),
            $result['chunked'] ? ' (chunked)' : '',
        ));

        foreach ($result['files'] as $file) {
            $this->line('  - storage/app/public/'.$file);
        }

        return self::SUCCESS;
    }
}
