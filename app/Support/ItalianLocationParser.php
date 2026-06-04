<?php

declare(strict_types=1);

namespace App\Support;

final class ItalianLocationParser
{
    /** @var array<string, string> */
    private const PROVINCE_TO_REGION = [
        'AG' => 'sicilia', 'AL' => 'piemonte', 'AN' => 'marche', 'AO' => 'valle-d-aosta',
        'AP' => 'marche', 'AQ' => 'abruzzo', 'AR' => 'toscana', 'AT' => 'piemonte',
        'AV' => 'campania', 'BA' => 'puglia', 'BG' => 'lombardia', 'BI' => 'piemonte',
        'BL' => 'veneto', 'BN' => 'campania', 'BO' => 'emilia-romagna', 'BR' => 'puglia',
        'BS' => 'lombardia', 'BT' => 'puglia', 'BZ' => 'trentino-alto-adige', 'CA' => 'sardegna',
        'CB' => 'molise', 'CE' => 'campania', 'CH' => 'abruzzo', 'CL' => 'sicilia',
        'CN' => 'piemonte', 'CO' => 'lombardia', 'CR' => 'lombardia', 'CS' => 'calabria',
        'CT' => 'sicilia', 'CZ' => 'calabria', 'EN' => 'sicilia', 'FC' => 'emilia-romagna',
        'FE' => 'emilia-romagna', 'FG' => 'puglia', 'FI' => 'toscana', 'FM' => 'marche',
        'FR' => 'lazio', 'GE' => 'liguria', 'GO' => 'friuli-venezia-giulia', 'GR' => 'toscana',
        'IM' => 'liguria', 'IS' => 'molise', 'KR' => 'calabria', 'LC' => 'lombardia',
        'LE' => 'puglia', 'LI' => 'toscana', 'LO' => 'lombardia', 'LT' => 'lazio',
        'LU' => 'toscana', 'MB' => 'lombardia', 'MC' => 'marche', 'ME' => 'sicilia',
        'MI' => 'lombardia', 'MN' => 'lombardia', 'MO' => 'emilia-romagna', 'MS' => 'toscana',
        'MT' => 'basilicata', 'NA' => 'campania', 'NO' => 'piemonte', 'NU' => 'sardegna',
        'OR' => 'sardegna', 'PA' => 'sicilia', 'PC' => 'emilia-romagna', 'PD' => 'veneto',
        'PE' => 'abruzzo', 'PG' => 'umbria', 'PI' => 'toscana', 'PN' => 'friuli-venezia-giulia',
        'PO' => 'toscana', 'PR' => 'emilia-romagna', 'PT' => 'toscana', 'PU' => 'marche',
        'PV' => 'lombardia', 'PZ' => 'basilicata', 'RA' => 'emilia-romagna', 'RC' => 'calabria',
        'RE' => 'emilia-romagna', 'RG' => 'sicilia', 'RI' => 'lazio', 'RM' => 'lazio',
        'RN' => 'emilia-romagna', 'RO' => 'veneto', 'SA' => 'campania', 'SI' => 'toscana',
        'SO' => 'lombardia', 'SP' => 'liguria', 'SR' => 'sicilia', 'SS' => 'sardegna',
        'SU' => 'sardegna', 'SV' => 'liguria', 'TA' => 'puglia', 'TE' => 'abruzzo',
        'TN' => 'trentino-alto-adige', 'TO' => 'piemonte', 'TP' => 'sicilia', 'TR' => 'umbria',
        'TS' => 'friuli-venezia-giulia', 'TV' => 'veneto', 'UD' => 'friuli-venezia-giulia',
        'VA' => 'lombardia', 'VB' => 'piemonte', 'VC' => 'piemonte', 'VE' => 'veneto',
        'VI' => 'veneto', 'VR' => 'veneto', 'VT' => 'lazio', 'VV' => 'calabria',
    ];

    /** @var array<string, string> normalized city name => province code */
    private const CITY_TO_PROVINCE = [
        'bergamo' => 'BG',
        'bologna' => 'BO',
        'brescia' => 'BS',
        'firenze' => 'FI',
        'milano' => 'MI',
        'monza' => 'MB',
        'napoli' => 'NA',
        'roma' => 'RM',
        'sesto san giovanni' => 'MI',
        'torino' => 'TO',
    ];

    public function parse(?string $label): ParsedItalianLocation
    {
        if ($label === null || trim($label) === '') {
            return ParsedItalianLocation::empty();
        }

        $city = $this->extractCity($label);
        $province = $this->extractProvinceCode($label);

        if ($province === null && $city !== '') {
            $province = self::CITY_TO_PROVINCE[$city] ?? null;
        }

        $region = $province !== null ? (self::PROVINCE_TO_REGION[$province] ?? null) : null;

        return new ParsedItalianLocation($city, $province, $region);
    }

    public function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return $text;
    }

    public function geoScore(ParsedItalianLocation $lead, ParsedItalianLocation $company): int
    {
        if ($lead->isEmpty() || $company->isEmpty()) {
            return 0;
        }

        if ($lead->city !== '' && $company->city !== '') {
            if ($lead->city === $company->city
                || str_contains($lead->city, $company->city)
                || str_contains($company->city, $lead->city)) {
                return 100;
            }
        }

        if ($lead->province !== null && $company->province !== null && $lead->province === $company->province) {
            return 80;
        }

        if ($lead->region !== null && $company->region !== null && $lead->region === $company->region) {
            return 50;
        }

        return 0;
    }

    /**
     * @param  list<string|null>  $companyLabels
     */
    public function bestGeoScore(?string $leadLabel, array $companyLabels): int
    {
        $lead = $this->parse($leadLabel);
        $best = 0;

        foreach ($companyLabels as $label) {
            if ($label === null || trim($label) === '') {
                continue;
            }

            $score = $this->geoScore($lead, $this->parse($label));
            $best = max($best, $score);

            if ($best === 100) {
                break;
            }
        }

        return $best;
    }

    private function extractCity(string $label): string
    {
        $parts = explode(',', $label);
        $first = trim($parts[0]);
        $first = preg_replace('/\s*\([A-Za-z]{2}\)\s*$/', '', $first) ?? $first;

        return $this->normalize($first);
    }

    private function extractProvinceCode(string $label): ?string
    {
        if (preg_match('/\(([A-Za-z]{2})\)/', $label, $matches) !== 1) {
            return null;
        }

        return strtoupper($matches[1]);
    }
}
