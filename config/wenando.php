<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Current legal document versions (semver)
    |--------------------------------------------------------------------------
    |
    | Align with footer legal docs and consent_logs.policy_version.
    |
    */

    'privacy_policy_version' => env('WENANDO_PRIVACY_POLICY_VERSION', '1.0.0'),

    'terms_b2b_version' => env('WENANDO_TERMS_B2B_VERSION', '1.0.0'),

    'cookie_policy_version' => env('WENANDO_COOKIE_POLICY_VERSION', '1.0.0'),

    /*
    |--------------------------------------------------------------------------
    | Consent log retention (years)
    |--------------------------------------------------------------------------
    |
    | After this period, IP, user agent, and session_id are anonymized on
    | consent_logs while consent_given and consent_text_hash are preserved.
    |
    */

    'consent_log_retention_years' => (int) env('WENANDO_CONSENT_LOG_RETENTION_YEARS', 5),

    /*
    |--------------------------------------------------------------------------
    | Stale lead anonymization (days)
    |--------------------------------------------------------------------------
    |
    | Leads in terminal statuses with no activity since this threshold are
    | anonymized by leads:anonymize-stale (default 730 = 24 months).
    |
    */

    'lead_anonymize_days' => (int) env('LEAD_ANONYMIZE_DAYS', 730),

    /*
    |--------------------------------------------------------------------------
    | Privacy / DSAR contact
    |--------------------------------------------------------------------------
    |
    | Inbox for erasure requests and optional export notifications.
    |
    */

    'privacy_contact_email' => env('WENANDO_PRIVACY_CONTACT_EMAIL', 'hola@wenando.com'),

    'privacy_export_notify' => filter_var(
        env('WENANDO_PRIVACY_EXPORT_NOTIFY', false),
        FILTER_VALIDATE_BOOL,
    ),

    /*
    |--------------------------------------------------------------------------
    | Wallet recharge (dev bypass)
    |--------------------------------------------------------------------------
    |
    | When true, POST /b2b/wallet/recharge credits the wallet immediately
    | (legacy mock). Production should leave false and confirm via webhook.
    |
    */

    'wallet_instant_recharge' => filter_var(
        env('WENANDO_WALLET_INSTANT_RECHARGE', false),
        FILTER_VALIDATE_BOOL,
    ),

    /*
    |--------------------------------------------------------------------------
    | Payment webhooks
    |--------------------------------------------------------------------------
    |
    | Shared secret sent in X-Wenando-Webhook-Secret on POST /webhooks/payments/*
    |
    */

    'webhook_secret' => env('WENANDO_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Bank transfer (B2B wallet recharge)
    |--------------------------------------------------------------------------
    |
    | Shown on GET /b2b/wallet/recharge/{id} for transfer payment intents.
    |
    */

    'bank_iban' => env('WENANDO_BANK_IBAN', 'IT00W0000000000000000000000'),

    'bank_beneficiary' => env('WENANDO_BANK_BENEFICIARY', 'Julian Rovera — Wenando'),

];
