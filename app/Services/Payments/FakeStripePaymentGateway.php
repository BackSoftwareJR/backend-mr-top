<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Contracts\Payments\StripePaymentGateway;

/**
 * Test double — no Stripe API calls.
 */
final class FakeStripePaymentGateway implements StripePaymentGateway
{
    /** @var array{amount_cents: int, currency: string, metadata: array<string, string>}|null */
    public static ?array $lastCreate = null;

    public static string $paymentIntentId = 'pi_fake_test_123';

    public static string $clientSecret = 'pi_fake_test_123_secret_xyz';

    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * @param  array<string, string>  $metadata
     * @return array{id: string, client_secret: string}
     */
    public function createPaymentIntent(int $amountCents, string $currency, array $metadata): array
    {
        self::$lastCreate = [
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'metadata' => $metadata,
        ];

        return [
            'id' => self::$paymentIntentId,
            'client_secret' => self::$clientSecret,
        ];
    }

    public static function reset(): void
    {
        self::$lastCreate = null;
        self::$paymentIntentId = 'pi_fake_test_123';
        self::$clientSecret = 'pi_fake_test_123_secret_xyz';
    }
}
