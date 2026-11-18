<?php

declare(strict_types=1);

function loadEnv(string $path): void
{
    $envFile = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';

    if (!is_readable($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || substr($trimmed, 0, 1) === '#') {
            continue;
        }

        [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
        $name = trim($name);
        $value = trim(trim($value), "\"'");

        if ($name === '') {
            continue;
        }

        if (!array_key_exists($name, $_ENV) && !array_key_exists($name, $_SERVER)) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

function env(string $key, $default = null)
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return $value;
}

function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    loadEnv(dirname(__DIR__));

    $host = (string) env('DB_HOST', '127.0.0.1');
    $db   = (string) env('DB_NAME', 'pfe_elearning');
    $user = (string) env('DB_USER', 'root');
    $pass = (string) env('DB_PASS', '');
    $port = (int) env('DB_PORT', 3309);
    $charset = 'utf8mb4';

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $db, $charset);
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        error_log('Database connection error: ' . $e->getMessage());

        if (env('APP_ENV', 'production') === 'development') {
            die('Connection failed: ' . $e->getMessage());
        }

        die('Database connection error. Please try again later.');
    }

    return $pdo;
}

$pdo = getDbConnection();

