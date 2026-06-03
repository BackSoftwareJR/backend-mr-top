<?php

declare(strict_types=1);

namespace App\Support;

final class PiiObfuscator
{
    public static function name(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return collect($parts)
            ->map(static function (string $part): string {
                $length = mb_strlen($part);

                if ($length <= 1) {
                    return $part.'*';
                }

                return mb_substr($part, 0, 1).str_repeat('*', min(3, $length - 1));
            })
            ->implode(' ');
    }

    public static function phone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) < 4) {
            return '***';
        }

        $prefix = substr($digits, 0, 3);
        $suffix = substr($digits, -2);

        return '+'.$prefix.' *** **'.$suffix;
    }

    public static function email(?string $email): ?string
    {
        if ($email === null || $email === '') {
            return null;
        }

        $parts = explode('@', $email, 2);

        if (count($parts) !== 2) {
            return '***@***';
        }

        [$local, $domain] = $parts;
        $visible = mb_substr($local, 0, 1);

        return $visible.'***@'.$domain;
    }
}
