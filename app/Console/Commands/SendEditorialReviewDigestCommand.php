<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendEditorialReviewDigestJob;
use Illuminate\Console\Command;

class SendEditorialReviewDigestCommand extends Command
{
    protected $signature = 'editorial:review-digest {--sync : Invia subito senza accodare il job}';

    protected $description = 'Invia il riepilogo giornaliero revisioni editoriali ai revisori';

    public function handle(): int
    {
        if ($this->option('sync')) {
            SendEditorialReviewDigestJob::dispatchSync();

            $this->info('Digest editoriale inviato (sync).');

            return self::SUCCESS;
        }

        SendEditorialReviewDigestJob::dispatch();
        $this->info('Digest editoriale accodato.');

        return self::SUCCESS;
    }
}
