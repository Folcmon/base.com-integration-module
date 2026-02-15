<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Ensure test env is set before loading .env files.
if (!isset($_SERVER['APP_ENV'])) {
    $_SERVER['APP_ENV'] = 'test';
}
if (!isset($_ENV['APP_ENV'])) {
    $_ENV['APP_ENV'] = 'test';
}

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// If running in test env, explicitly load .env.test so DATABASE_URL and other test-only vars are available.
if (($_SERVER['APP_ENV'] ?? getenv('APP_ENV')) === 'test' && file_exists(dirname(__DIR__).'/.env.test')) {
    (new Dotenv())->load(dirname(__DIR__).'/.env.test');
}

// Fallback: if critical env var still not present, try again using a direct load (defensive)
if ((getenv('BL_API_URL') === false || getenv('DATABASE_URL') === false) && file_exists(dirname(__DIR__).'/.env.test')) {
    (new Dotenv())->load(dirname(__DIR__).'/.env.test');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
