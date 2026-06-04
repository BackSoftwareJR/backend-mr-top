<?php

declare(strict_types=1);

namespace App\Support;

final readonly class ParsedItalianLocation
{
    public function __construct(
        public string $city,
        public ?string $province,
        public ?string $region,
    ) {}

    public static function empty(): self
    {
        return new self('', null, null);
    }

    public function isEmpty(): bool
    {
        return $this->city === '' && $this->province === null && $this->region === null;
    }
}
