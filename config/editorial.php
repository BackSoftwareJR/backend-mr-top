<?php

declare(strict_types=1);

return [

    'site_url' => env('EDITORIAL_SITE_URL', env('APP_URL', 'https://wenando.com')),

    'structure_disclaimer' => 'Contenuto redatto dalla struttura. Wenando non garantisce l\'accuratezza delle informazioni e non sostituisce consulenza medica o professionale.',

    'seo' => [
        'min_score' => (int) env('EDITORIAL_SEO_MIN_SCORE', 70),
        'require_seo_approval' => (bool) env('EDITORIAL_SEO_REQUIRE_APPROVAL', true),
        'superadmin_bypass' => (bool) env('EDITORIAL_SEO_SUPERADMIN_BYPASS', false),
        'groq_model' => env('GROQ_EDITORIAL_SEO_MODEL', env('GROQ_MODEL', 'llama-3.3-70b-versatile')),
        'prompt_version' => 'editorial-seo-v1',
    ],

];
