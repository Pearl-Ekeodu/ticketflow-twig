<?php

return [
    'database' => [
        'driver' => 'sqlite',
        'database' => __DIR__ . '/../database/tickets.db',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ],
    'app' => [
        'name' => 'TicketFlow',
        'url' => 'http://localhost:8000',
        'timezone' => 'UTC',
        'debug' => true,
    ],
    'session' => [
        'name' => 'ticketapp_session',
        'lifetime' => 7200, // 2 hours
        'secure' => false, // Set to true in production with HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ],
    'security' => [
        'password_min_length' => 6,
        'csrf_token_name' => '_token',
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 minutes
    ]
];
