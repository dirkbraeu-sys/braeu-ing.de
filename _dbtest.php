<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
require __DIR__ . '/kunden/db-config.php';
try {
    $pdo = kunden_db();
    $pdo->exec("ALTER TABLE customers ADD COLUMN IF NOT EXISTS reset_token CHAR(64) NULL");
    echo "col1 ok\n";
    $pdo->exec("ALTER TABLE customers ADD COLUMN IF NOT EXISTS reset_expires DATETIME NULL");
    echo "col2 ok\n";
    $cols = $pdo->query("SHOW COLUMNS FROM customers")->fetchAll(PDO::FETCH_COLUMN);
    echo "Spalten: " . implode(', ', $cols) . "\n";
} catch (Throwable $e) {
    echo "FEHLER: " . get_class($e) . ": " . $e->getMessage() . "\n";
}
