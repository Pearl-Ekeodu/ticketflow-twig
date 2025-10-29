<?php
// Vercel PHP entry point - wrapper for index.php
// This ensures Vercel executes PHP correctly

$_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/public';
chdir(__DIR__ . '/public');

require __DIR__ . '/public/index.php';

