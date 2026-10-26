<?php

return [
    'login_attempts' => 5,
    'login_lock_seconds' => 300,
    'password_confirmation_seconds' => 300,
    'super_admin_totp' => (bool) env('SUPER_ADMIN_TOTP_ENABLED', false),
    'totp_secrets' => json_decode(env('SUPER_ADMIN_TOTP_SECRETS', '{}'), true) ?: [],
];
