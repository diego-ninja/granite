<?php
// tests/bootstrap.php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Setup timezone for consistent DateTime testing
date_default_timezone_set('UTC');

// Define test constants
define('GRANITE_TEST_FIXTURES_PATH', __DIR__ . '/Fixtures');
define('GRANITE_TEST_DATA_PATH', __DIR__ . '/Data');

// Ensure test directories exist
$directories = [
    __DIR__ . '/Unit',
    __DIR__ . '/Integration',
    __DIR__ . '/Fixtures',
    __DIR__ . '/Data',
    __DIR__ . '/_reports',
    __DIR__ . '/../coverage',
];

foreach ($directories as $directory) {
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
}

// Load test helpers
require_once __DIR__ . '/Helpers/TestCase.php';

// Initialize Mockery
if (class_exists('Mockery')) {
    register_shutdown_function(function () {
        if (class_exists('Mockery')) {
            Mockery::close();
        }
    });
}