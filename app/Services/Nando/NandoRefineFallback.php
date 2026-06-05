<?php

declare(strict_types=1);

namespace App\Services\Nando;

final class NandoRefineFallback
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

    /**
     * @param  array<string, mixed>  $selections
     * @param  list<array<string, mixed>>  $refinementHistory
     * @return array{pageTitle: string, supported: bool, question: ?array<string, mixed>, complete: bool}
     */
    public static function refine(
        string $query,
        array $selections,
        ?string $customNotes,
        array $refinementHistory,
    ): array {
        unset($customNotes, $refinementHistory);

        $question = self::nextQuestion($query, $selections);

        return [
            'pageTitle' => 'Risultati per '.$query,
            'supported' => true,
            'question' => $question,
            'complete' => $question === null,
        ];
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
}
