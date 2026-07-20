<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from_number' => env('TWILIO_FROM_NUMBER'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
    ],

    // MaxMind GeoLite2 - geolocalizacion via DB local (no API externa).
    // Genera tu license key en:
    //   https://www.maxmind.com/en/accounts/current/license-key
    // marcando "Will this key be used for GeoIP Update? YES".
    'maxmind' => [
        'account_id' => env('MAXMIND_ACCOUNT_ID'),
        'license_key' => env('MAXMIND_LICENSE_KEY'),
    ],

    // Google reCAPTCHA v3 (invisible, score-based) para proteger endpoints
    // sensibles como /login. Si `secret_key` esta vacio, la validacion se
    // skipea (default seguro para local/testing). Generar keys en:
    //   https://www.google.com/recaptcha/admin/create
    // Tipo: reCAPTCHA v3. Dominios: agregar lendus.app y *.lendus.app.
    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
        // Threshold de score: 1.0 = humano confiable, 0.0 = bot.
        // 0.5 es el default recomendado por Google. Subir a 0.7 para
        // mayor friccion; bajar a 0.3 si tus usuarios reales saca scores
        // bajos por razones legitimas (mobile, VPNs corporativos, etc.).
        'min_score' => (float) env('RECAPTCHA_MIN_SCORE', 0.5),
        // Si true, valida que la accion ejecutada por el frontend coincida
        // con la esperada por el backend (evita reuse de tokens entre paths).
        'verify_action' => filter_var(env('RECAPTCHA_VERIFY_ACTION', true), FILTER_VALIDATE_BOOLEAN),
    ],

    // Cuenta de PRUEBA para revisión de tienda (Google Play / App Store): un
    // teléfono fijo con OTP fijo, SOLO para que el personal de revisión pueda
    // entrar sin recibir SMS real. Acotado a un tenant (slug) y a ese número.
    // NO envía SMS y NO expone el código; al revisor se le dan las credenciales
    // aparte. Poner STORE_REVIEW_ENABLED=false para desactivarlo por completo.
    'store_review' => [
        'enabled' => filter_var(env('STORE_REVIEW_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'tenant_slug' => env('STORE_REVIEW_TENANT', 'moneycapital'),
        // Solo dígitos; se compara normalizando (sin +52 ni espacios).
        'phone' => env('STORE_REVIEW_PHONE', '9615000000'),
        'otp' => env('STORE_REVIEW_OTP', '321987'),
    ],

    // Catálogo SEPOMEX (postal_codes). La descarga del archivo oficial de
    // Correos de México es MANUAL (requiere registro) y se importa con
    // `postal-codes:import`. El comando `postal-codes:check-freshness` avisa si
    // la tabla está vacía o si la última importación superó esta ventana de
    // vigencia (días). Ajustable por env sin re-desplegar.
    'postal_codes' => [
        'max_age_days' => (int) env('POSTAL_CODES_MAX_AGE_DAYS', 180),
    ],

];
