<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'username' => 'email',
    'email' => 'email',
    'views' => false,
    'features' => [
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
            'window' => 1,
        ]),
    ],
];
