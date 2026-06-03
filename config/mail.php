<?php

/**
 * Resolve SMTP scheme for Laravel 13 (Symfony Mailer DSN).
 * Prefer MAIL_SCHEME; fall back to legacy MAIL_ENCRYPTION for Hostinger docs/templates.
 */
$mailScheme = env('MAIL_SCHEME');
if ($mailScheme === null || $mailScheme === '') {
    $mailScheme = match (strtolower((string) env('MAIL_ENCRYPTION', ''))) {
        'ssl', 'smtps' => 'smtps',
        'tls', 'starttls' => 'smtp',
        default => null,
    };
}

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => $mailScheme,
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => (int) env('MAIL_PORT', 587),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => (int) env('MAIL_TIMEOUT', 30),
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | Use the same mailbox as MAIL_USERNAME (hola@wenando.com) for SPF/DKIM alignment.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hola@wenando.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Wenando')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reply-To (transactional + support)
    |--------------------------------------------------------------------------
    */

    'reply_to' => [
        'address' => env('MAIL_REPLY_TO_ADDRESS', env('MAIL_FROM_ADDRESS', 'hola@wenando.com')),
        'name' => env('MAIL_REPLY_TO_NAME', env('MAIL_FROM_NAME', env('APP_NAME', 'Wenando'))),
    ],

];
