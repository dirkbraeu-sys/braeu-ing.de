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

    // Read-only Blick auf den Test-Datensatz (nur zum Testen, keine echten Kundendaten).
    $stmt = $pdo->prepare('SELECT id, name, email, verified, verification_token, verification_expires FROM customers WHERE email = ?');
    $stmt->execute(['claude-test@braeu-ing.de']);
    $row = $stmt->fetch();
    echo "row=" . json_encode($row) . "\n";
} catch (Throwable $e) {
    echo "FEHLER: " . get_class($e) . ": " . $e->getMessage() . "\n";
}
