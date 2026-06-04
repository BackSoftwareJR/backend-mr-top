<?php

declare(strict_types=1);

namespace App\Contracts\Payments;

interface StripePaymentGateway
{
    public function isConfigured(): bool;

    /**
     * @param  array<string, string>  $metadata
     * @return array{id: string, client_secret: string}
     */
    public function createPaymentIntent(int $amountCents, string $currency, array $metadata): array;
}
