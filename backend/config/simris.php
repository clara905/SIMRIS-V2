<?php

return [
    'admin' => [
        'username' => env('SIMRIS_ADMIN_USERNAME'),
        'email' => env('SIMRIS_ADMIN_EMAIL'),
        'password' => env('SIMRIS_ADMIN_PASSWORD'),
        'nip' => env('SIMRIS_ADMIN_NIP'),
        'name' => env('SIMRIS_ADMIN_NAME'),
        'employment_type' => env('SIMRIS_ADMIN_EMPLOYMENT_TYPE', 'PNS'),
    ],
    'demo_password' => env('SIMRIS_DEMO_PASSWORD'),
    'aset_unit_bawaan_kode' => env('ASET_UNIT_BAWAAN_KODE', 'SATKER-DEMO'),
    'firebase' => [
        // Web API Key dari Firebase Console (Project settings > General). Bukan rahasia, tetapi tetap lewat .env.
        'api_key' => env('SIMRIS_FIREBASE_API_KEY', ''),
        // Tolak login bila email di Firebase belum terverifikasi.
        'wajib_email_terverifikasi' => (bool) env('SIMRIS_FIREBASE_WAJIB_EMAIL_TERVERIFIKASI', true),
    ],

    'captcha' => [
        'site_key' => env('SIMRIS_RECAPTCHA_SITE_KEY', ''),
        'secret' => env('SIMRIS_RECAPTCHA_SECRET_KEY', env('SIMRIS_CAPTCHA_SECRET', '')),
        'verify_url' => env('SIMRIS_CAPTCHA_VERIFY_URL', 'https://www.google.com/recaptcha/api/siteverify'),
        // Opsional: batasi ke hostname frontend (mis. simris.example.go.id).
        'hostname' => env('SIMRIS_CAPTCHA_HOSTNAME'),
        // Opsional: hanya untuk reCAPTCHA v3 (0.0 sampai 1.0).
        'min_score' => env('SIMRIS_CAPTCHA_MIN_SCORE'),
    ],
];
