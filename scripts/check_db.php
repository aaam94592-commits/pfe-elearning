<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $stmt = $pdo->query('SELECT 1 AS ok');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "DB_STATUS=OK\n";
    echo "DB_HOST=" . getenv('DB_HOST') . "\n";
    echo "DB_PORT=" . getenv('DB_PORT') . "\n";
    echo "DB_NAME=" . getenv('DB_NAME') . "\n";
    echo "DB_USER=" . getenv('DB_USER') . "\n";
    echo "DB_DRIVER=mysql\n";
    echo "QUERY_RESULT=" . ($result['ok'] ?? 'unknown') . "\n";
} catch (Throwable $e) {
    echo "DB_STATUS=ERROR\n";
    echo "ERROR_MESSAGE=" . $e->getMessage() . "\n";
    echo "DB_HOST=" . (getenv('DB_HOST') ?: 'not set') . "\n";
    echo "DB_PORT=" . (getenv('DB_PORT') ?: 'not set') . "\n";
    echo "DB_NAME=" . (getenv('DB_NAME') ?: 'not set') . "\n";
    echo "DB_USER=" . (getenv('DB_USER') ?: 'not set') . "\n";
}
