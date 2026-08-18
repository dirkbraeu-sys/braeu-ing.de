<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
$cfg = __DIR__ . '/kunden/db-config.php';
if (!file_exists($cfg)) {
    echo "db-config.php fehlt\n";
    exit;
}
require $cfg;
try {
    $pdo = kunden_db();
    $v = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "OK, MySQL-Version: $v\n";
} catch (Throwable $e) {
    echo "FEHLER: " . get_class($e) . ": " . $e->getMessage() . "\n";
}
