<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Contracts\Payments\StripePaymentGateway as StripePaymentGatewayContract;
use Stripe\PaymentIntent;
use Stripe\Stripe;

final class StripePaymentGateway implements StripePaymentGatewayContract
{
    public function __construct(
        private readonly ?string $secretKey,
    ) {}

    public function isConfigured(): bool
    {
        return is_string($this->secretKey) && $this->secretKey !== '';
    }

    /**
     * @param  array<string, string>  $metadata
     * @return array{id: string, client_secret: string}
     */
    public function createPaymentIntent(int $amountCents, string $currency, array $metadata): array
    {
        Stripe::setApiKey($this->secretKey);

        $intent = PaymentIntent::create([
            'amount' => $amountCents,
            'currency' => strtolower($currency),
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => $metadata,
        ]);

        $clientSecret = $intent->client_secret;

        if (! is_string($clientSecret) || $clientSecret === '') {
            throw new \RuntimeException('Stripe PaymentIntent missing client_secret.');
        }

        return [
            'id' => $intent->id,
            'client_secret' => $clientSecret,
        ];
    }
}
