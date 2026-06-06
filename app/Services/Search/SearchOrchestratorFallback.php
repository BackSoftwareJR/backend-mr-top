<?php

declare(strict_types=1);

namespace App\Services\Search;

final class SearchOrchestratorFallback
{
    /** @var list<string> */
    private const LOCATION_HINTS = [
        'milano',
        'monza',
        'torino',
        'roma',
        'bergamo',
        'brescia',
        'lombardia',
        'piemonte',
    ];

    /** @var list<string> */
    private const PATH_LABELS = [
        'Scelta consigliata',
        'Alternativa valida',
        'Da valutare',
    ];

    /**
     * @param  array<string, mixed>  $selections
     * @param  list<array<string, mixed>>  $refinementHistory
     * @return array{
     *   pageTitle: string,
     *   supported: bool,
     *   paths: list<array<string, mixed>>,
     *   editorial: list<array<string, mixed>>,
     *   nando: array<string, mixed>
     * }
     */
    public static function orchestrate(
        string $query,
        array $selections,
        ?string $customNotes,
        array $refinementHistory,
    ): array {
        unset($customNotes, $refinementHistory);

        $pageTitle = 'Risultati per '.$query;
        $paths = self::buildPaths($query, $selections);
        $question = self::nextQuestion($query, $selections);

        return [
            'pageTitle' => $pageTitle,
            'supported' => true,
            'paths' => $paths,
            'editorial' => self::defaultEditorial(),
            'nando' => [
                'microPrompt' => 'Affina la ricerca con Nando',
                'actions' => [
                    ['id' => 'explain_why', 'label' => 'Scopri perché ti abbiamo consigliato'],
                ],
                'question' => $question,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $selections
     * @return list<array<string, mixed>>
     */
    private static function buildPaths(string $query, array $selections): array
    {
        $budget = $selections['refinement_budget'] ?? null;
        $care = $selections['refinement_care'] ?? null;
        $normalized = mb_strtolower(trim($query));

        $scored = [
            ['key' => 'rsa', 'score' => 70],
            ['key' => 'home_care', 'score' => 65],
            ['key' => 'community', 'score' => 60],
            ['key' => 'day_center', 'score' => 55],
            ['key' => 'agency', 'score' => 50],
        ];

        if (str_contains($normalized, 'rsa') || str_contains($normalized, 'casa di riposo') || str_contains($normalized, 'struttura')) {
            self::bump($scored, 'rsa', 15);
        }

        if (str_contains($normalized, 'badante') || str_contains($normalized, 'domiciliare') || str_contains($normalized, 'casa')) {
            self::bump($scored, 'home_care', 18);
            self::bump($scored, 'agency', 12);
        }

        if (str_contains($normalized, 'demenza') || str_contains($normalized, 'alzheimer')) {
            self::bump($scored, 'rsa', 20);
            self::bump($scored, 'community', 10);
        }

        if ($budget === 'under1500') {
            self::bump($scored, 'home_care', 25);
            self::bump($scored, 'day_center', 20);
            self::bump($scored, 'agency', 15);
            self::bump($scored, 'rsa', -15);
        } elseif ($budget === 'high') {
            self::bump($scored, 'rsa', 20);
            self::bump($scored, 'community', 18);
        } elseif ($budget === 'mid') {
            self::bump($scored, 'rsa', 10);
            self::bump($scored, 'home_care', 10);
        }

        if ($care === 'partial') {
            self::bump($scored, 'home_care', 22);
            self::bump($scored, 'day_center', 18);
            self::bump($scored, 'agency', 12);
        } elseif ($care === 'moderate') {
            self::bump($scored, 'day_center', 15);
            self::bump($scored, 'community', 12);
            self::bump($scored, 'home_care', 8);
        } elseif ($care === 'intensive') {
            self::bump($scored, 'rsa', 22);
            self::bump($scored, 'community', 10);
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $topKeys = array_slice(array_column($scored, 'key'), 0, 3);

        $refinementNeeded = self::refinementNeeded($query, $selections);

        $paths = [];
        foreach ($topKeys as $index => $key) {
            $template = self::pathTemplate($key);
            $paths[] = [
                'id' => 'path_'.$key,
                'type' => $template['type'],
                'rank' => $index + 1,
                'label' => self::PATH_LABELS[$index] ?? 'Da valutare',
                'title' => $template['title'],
                'subtitle' => $template['subtitle'],
                'summary' => $template['summary'],
                'whyRecommended' => $template['whyRecommended'],
                'refinementNeeded' => $refinementNeeded,
                'imageHint' => $template['imageHint'],
            ];
        }

        return $paths;
    }

    /**
     * @param  list<array{key: string, score: int}>  $scored
     */
    private static function bump(array &$scored, string $key, int $delta): void
    {
        foreach ($scored as &$item) {
            if ($item['key'] === $key) {
                $item['score'] += $delta;

                return;
            }
        }
    }

    /**
     * @return array{type: string, title: string, subtitle: string, summary: string, whyRecommended: string, imageHint: string}
     */
    private static function pathTemplate(string $key): array
    {
        return match ($key) {
            'rsa' => [
                'type' => 'structure',
                'title' => 'Residenza sanitaria assistenziale (RSA)',
                'subtitle' => 'Percorso · Struttura residenziale',
                'summary' => 'Assistenza continuativa in struttura, con equipe infermieristica e percorsi personalizzati.',
                'whyRecommended' => 'Indicato quando serve assistenza intensiva e presenza h24, con supervisione medica costante.',
                'imageHint' => 'rsa',
            ],
            'home_care' => [
                'type' => 'service',
                'title' => 'Assistenza domiciliare',
                'subtitle' => 'Percorso · Restare a casa',
                'summary' => 'Badante o infermiere a domicilio, con agenzia certificata e contratto chiaro.',
                'whyRecommended' => 'Mantiene la familiarità dell’ambiente domestico quando l’autonomia è parziale o moderata.',
                'imageHint' => 'home_care',
            ],
            'community' => [
                'type' => 'structure',
                'title' => 'Casa famiglia o comunità alloggio',
                'subtitle' => 'Percorso · Ambiente familiare',
                'summary' => 'Comunità ristretta con rapporto umano costante, ideale per chi preferisce un contesto più intimo.',
                'whyRecommended' => 'Equilibrio tra assistenza e vita sociale, con gruppi ridotti e attenzione personalizzata.',
                'imageHint' => 'community',
            ],
            'day_center' => [
                'type' => 'service',
                'title' => 'Centro diurno',
                'subtitle' => 'Percorso · Equilibrio giorno-sera',
                'summary' => 'Assistenza di giorno con attività e supervisione, rientro a casa la sera.',
                'whyRecommended' => 'Soluzione intermedia quando serve supporto nelle ore centrali ma si vuole tornare a casa.',
                'imageHint' => 'community',
            ],
            'agency' => [
                'type' => 'service',
                'title' => 'Badante via agenzia certificata',
                'subtitle' => 'Percorso · Sicurezza e tutela',
                'summary' => 'Contratti verificati, sostituti garantiti e documentazione in regola per evitare rischi.',
                'whyRecommended' => 'Riduce il rischio di truffe e offre un referente in caso di assenze o problemi.',
                'imageHint' => 'home_care',
            ],
            default => [
                'type' => 'service',
                'title' => 'Percorso assistenziale personalizzato',
                'subtitle' => 'Percorso · Da approfondire',
                'summary' => 'Valutiamo insieme le opzioni più adatte alla vostra situazione.',
                'whyRecommended' => 'Partiamo dalle vostre esigenze per orientarvi verso la soluzione più equilibrata.',
                'imageHint' => 'home_care',
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $selections
     * @return list<string>
     */
    private static function refinementNeeded(string $query, array $selections): array
    {
        $needed = [];

        if (! isset($selections['refinement_zone']) && ! self::queryHasLocationHint($query)) {
            $needed[] = 'zone';
        }

        if (! isset($selections['refinement_budget'])) {
            $needed[] = 'budget';
        }

        if (! isset($selections['refinement_care'])) {
            $needed[] = 'autonomy';
        }

        return $needed;
    }

    /**
     * @param  array<string, mixed>  $selections
     * @return ?array{id: string, question: string, hint: string, options: list<array{id: string, label: string}>}
     */
    private static function nextQuestion(string $query, array $selections): ?array
    {
        if (! isset($selections['refinement_zone']) && ! self::queryHasLocationHint($query)) {
            return [
                'id' => 'refinement_zone',
                'question' => 'In quale zona stai cercando?',
                'hint' => 'Un dettaglio in più ci aiuta a restringere le proposte.',
                'options' => [
                    ['id' => 'milano', 'label' => 'Milano e hinterland'],
                    ['id' => 'lombardia', 'label' => 'Altra zona in Lombardia'],
                    ['id' => 'altra', 'label' => 'Altra città o regione'],
                ],
            ];
        }

        if (! isset($selections['refinement_budget'])) {
            return [
                'id' => 'refinement_budget',
                'question' => 'Quale budget mensile avete in mente?',
                'hint' => 'Solo orientativo — nessun impegno.',
                'options' => [
                    ['id' => 'under1500', 'label' => 'Fino a 1.500 €'],
                    ['id' => 'mid', 'label' => '1.500 – 2.500 €'],
                    ['id' => 'high', 'label' => 'Oltre 2.500 €'],
                ],
            ];
        }

        if (! isset($selections['refinement_care'])) {
            return [
                'id' => 'refinement_care',
                'question' => 'Di che livello di assistenza ha bisogno?',
                'hint' => 'Non serve essere precisi al millimetro.',
                'options' => [
                    ['id' => 'partial', 'label' => 'Parziale — cammina da solo/a'],
                    ['id' => 'moderate', 'label' => 'Moderato — aiuto quotidiano'],
                    ['id' => 'intensive', 'label' => 'Intensivo — non autosufficiente'],
                ],
            ];
        }

        return null;
    }

    private static function queryHasLocationHint(string $query): bool
    {
        $normalized = mb_strtolower(trim($query));

        foreach (self::LOCATION_HINTS as $hint) {
            if (str_contains($normalized, $hint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{id: string, title: string, summary: string, url: string, relevanceReason: string}>
     */
    private static function defaultEditorial(): array
    {
        return [
            [
                'id' => 'art-002',
                'title' => 'RSA vs assistenza domiciliare: quale scegliere',
                'summary' => 'Confronto onesto tra costi, qualità della vita e carico familiare.',
                'url' => '/blog/rsa-vs-assistenza-domiciliare',
                'relevanceReason' => 'Utile per capire quale percorso valutare per primo.',
            ],
            [
                'id' => 'art-001',
                'title' => 'Come riconoscere un’agenzia per badanti affidabile',
                'summary' => 'Segnali positivi, red flags e domande da fare prima di firmare.',
                'url' => '/blog/agenzia-badanti-affidabile',
                'relevanceReason' => 'Protegge dalle truffe se state valutando l’assistenza a domicilio.',
            ],
            [
                'id' => 'art-004',
                'title' => 'Checklist prima di visitare una RSA',
                'summary' => 'Cosa osservare, domande da fare e documenti da richiedere.',
                'url' => '/blog/checklist-visita-rsa',
                'relevanceReason' => 'Vi prepara alle visite quando esplorate strutture residenziali.',
            ],
        ];
    }
}
