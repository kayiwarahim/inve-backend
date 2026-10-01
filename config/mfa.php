<?php

return [
    'issuer' => env('MFA_ISSUER', env('APP_NAME', 'Inventory')),
    'required_roles' => array_values(array_filter(array_map('trim', explode(',', env('MFA_REQUIRED_ROLES', ''))))),
    'enrollment_minutes' => 10,
    'challenge_minutes' => 5,
    'challenge_attempts' => 5,
    'recovery_code_count' => 10,
    'token_days' => 7,
];
