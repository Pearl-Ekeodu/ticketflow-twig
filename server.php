<?php
// Simple PHP development server for Twig version
// Run with: php server.php

$host = 'localhost';
$port = 8000;

echo "Starting TicketFlow Twig server on http://{$host}:{$port}\n";
echo "Press Ctrl+C to stop the server\n\n";

// Change to the public directory
chdir(__DIR__ . '/public');

// Start the built-in PHP server
$command = "php -S {$host}:{$port}";
passthru($command);
