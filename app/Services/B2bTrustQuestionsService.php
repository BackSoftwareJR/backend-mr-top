<?php

declare(strict_types=1);

namespace App\Services;

class B2bTrustQuestionsService
{
    /** @var list<string> */
    private const VALID_SECTORS = ['rsa', 'adi', 'centro', 'clinica'];

    /**
     * @return list<array{id: string, title: string, prompt: string, type: string, options: list<array{value: string, label: string}>}>
     */
    public function forSector(?string $sectorSlug): array
    {
        $slug = $this->normalizeSector($sectorSlug);
        $all = config('wenando.trust_questions', []);

        /** @var list<array<string, mixed>> $questions */
        $questions = $all[$slug] ?? $all['rsa'] ?? [];

        return array_values($questions);
    }

    /**
     * @return list<string>
     */
    public function questionIdsForSector(?string $sectorSlug): array
    {
        return array_map(
            static fn (array $question): string => (string) $question['id'],
            $this->forSector($sectorSlug),
        );
    }

    public function normalizeSector(?string $sectorSlug): string
    {
        $slug = strtolower(trim((string) $sectorSlug));

        if ($slug === '' || ! in_array($slug, self::VALID_SECTORS, true)) {
            return 'rsa';
        }

        return $slug;
    }
}
