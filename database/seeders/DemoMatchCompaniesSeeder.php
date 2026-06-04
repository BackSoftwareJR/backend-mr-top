<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\CompanyProfile;
use App\Models\Sector;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoMatchCompaniesSeeder extends Seeder
{
    public function run(): void
    {
        $sector = Sector::query()->where('slug', 'senior-care')->first();

        if ($sector === null) {
            return;
        }

        $companies = [
            [
                'organization_name' => 'Casa Serenità',
                'legal_name' => 'Casa Serenità S.r.l.',
                'city' => 'Milano',
                'dynamic_attributes' => ['sector' => 'adi', 'capacity' => 20, 'nonSelfSufficient' => true, 'nightStaff' => true],
                'profile' => [
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
                ],
            ],
            [
                'organization_name' => 'Residenza Il Girasole',
                'legal_name' => 'Residenza Il Girasole S.r.l.',
                'city' => 'Milano',
                'dynamic_attributes' => ['sector' => 'rsa', 'capacity' => 48, 'nonSelfSufficient' => true, 'nightStaff' => true],
                'profile' => [
                    'display_name' => 'Residenza Il Girasole',
                    'service_type' => 'RSA',
                    'tagline' => 'Struttura accogliente con programmi sociali',
                    'description' => 'Struttura accogliente con programmi sociali e assistenza 24h.',
                    'pros' => [
                        'Assistenza infermieristica h24',
                        'Programmi sociali e fisioterapia',
                        'Visite illimitate per familiari',
                    ],
                    'image_url' => 'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=400&h=300&fit=crop&q=80',
                    'location_label' => 'Milano, Zona Porta Romana',
                    'contact_hint' => 'Prenota una visita guidata: il referente ti richiama entro un giorno lavorativo.',
                ],
            ],
            [
                'organization_name' => 'Care Home Milano',
                'legal_name' => 'Care Home Milano S.r.l.',
                'city' => 'Milano',
                'dynamic_attributes' => ['sector' => 'adi', 'capacity' => 30, 'nonSelfSufficient' => true, 'nightStaff' => false],
                'profile' => [
                    'display_name' => 'Care Home Milano',
                    'service_type' => 'Assistenza Domiciliare',
                    'tagline' => 'Team dedicato per demenza e patologie croniche',
                    'description' => 'Team dedicato con esperienza in demenza e patologie croniche.',
                    'pros' => [
                        'Specializzazione in demenza e Alzheimer',
                        'Coordinamento con medico di base',
                        'Piano assistenziale personalizzato',
                    ],
                    'image_url' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400&h=300&fit=crop&q=80',
                    'location_label' => 'Milano, Zona Isola',
                    'contact_hint' => 'Compila il modulo online o chiama il numero verde: consulenza iniziale gratuita.',
                ],
            ],
        ];

        foreach ($companies as $data) {
            $profile = $data['profile'];
            unset($data['profile']);

            $company = Company::query()->firstOrCreate(
                ['organization_name' => $data['organization_name'], 'sector_id' => $sector->id],
                [
                    ...$data,
                    'uuid' => (string) Str::uuid(),
                    'vetting_status' => VettingStatus::Approved,
                    'approved_at' => now(),
                ],
            );

            CompanyProfile::query()->updateOrCreate(
                ['company_id' => $company->id],
                $profile,
            );
        }
    }
}
