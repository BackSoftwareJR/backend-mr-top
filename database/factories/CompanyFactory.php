<?php

namespace Database\Factories;

use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sectorId = Sector::query()->value('id');

        return [
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sectorId ?? 1,
            'organization_name' => fake()->company(),
            'legal_name' => fake()->company().' S.r.l.',
            'city' => fake()->city(),
            'vetting_status' => VettingStatus::Approved,
            'approved_at' => now(),
            'dynamic_attributes' => [
                'sector' => 'adi',
                'capacity' => 20,
                'nonSelfSufficient' => true,
                'nightStaff' => true,
            ],
        ];
    }
}
