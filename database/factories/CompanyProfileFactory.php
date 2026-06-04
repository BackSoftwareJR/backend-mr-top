<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyProfile>
 */
class CompanyProfileFactory extends Factory
{
    protected $model = CompanyProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'display_name' => fake()->company(),
            'service_type' => 'Assistenza Domiciliare',
            'tagline' => fake()->optional()->sentence(6),
            'description' => fake()->sentence(12),
            'pros' => [
                'Operatori qualificati',
                'Orari flessibili',
                'Supporto per detrazioni fiscali',
            ],
            'image_url' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=400&h=300&fit=crop&q=80',
            'location_label' => fake()->city().', Zona Centro',
            'contact_hint' => 'Richiedi un sopralluogo gratuito: rispondono entro 24 ore.',
        ];
    }

    /**
     * Profile aligned with mockMatches.js — Casa Serenità.
     */
    public function casaSerenita(): static
    {
        return $this->state(fn () => [
            'display_name' => 'Casa Serenità',
            'service_type' => 'Assistenza Domiciliare',
            'tagline' => 'Assistenza personalizzata con operatori qualificati',
            'description' => 'Assistenza personalizzata con operatori qualificati, flessibilità oraria.',
            'pros' => [
                'Operatori fissi e referente dedicato',
                'Orari su misura, anche weekend',
                'Supporto per ADI e detrazioni fiscali',
            ],
            'image_url' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=400&h=300&fit=crop&q=80',
            'location_label' => 'Milano, Zona Navigli',
            'contact_hint' => 'Richiedi un sopralluogo gratuito: rispondono entro 24 ore via telefono o WhatsApp.',
        ]);
    }
}
