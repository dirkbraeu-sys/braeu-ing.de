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
    $count = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    echo "customers_count=$count\n";
    $stmt = $pdo->query('SELECT id, name, email, verified FROM customers');
    foreach ($stmt->fetchAll() as $row) {
        echo json_encode($row) . "\n";
    }
} catch (Throwable $e) {
    echo "FEHLER: " . get_class($e) . ": " . $e->getMessage() . "\n";
}
