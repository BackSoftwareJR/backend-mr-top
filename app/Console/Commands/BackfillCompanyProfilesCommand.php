<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\B2bCompanyProfileService;
use Illuminate\Console\Command;

class BackfillCompanyProfilesCommand extends Command
{
    protected $signature = 'company-profiles:backfill {--dry-run : Count companies without creating profiles}';

    protected $description = 'Create company_profiles rows for companies missing a profile';

    public function handle(B2bCompanyProfileService $profileService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $companiesWithoutProfile = Company::query()
            ->whereDoesntHave('profile')
            ->get();

        $count = $companiesWithoutProfile->count();

        if ($dryRun) {
            $this->components->info(sprintf(
                'Would create %d company profile(s) for companies without a profile.',
                $count,
            ));

            return self::SUCCESS;
        }

        foreach ($companiesWithoutProfile as $company) {
            $profileService->ensureProfileForCompany($company);
        }

        $this->components->info(sprintf(
            'Created %d company profile(s) for companies without a profile.',
            $count,
        ));

        return self::SUCCESS;
    }
}
