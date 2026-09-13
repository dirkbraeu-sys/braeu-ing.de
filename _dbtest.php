<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
require __DIR__ . '/kunden/db-config.php';
try {
    $pdo = kunden_db();
    $stmt = $pdo->prepare('SELECT id, name, verified, password_hash IS NOT NULL AS has_pw, reset_token, reset_expires FROM customers WHERE email = ?');
    $stmt->execute(['dirk.braeu@web.de']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
} catch (Throwable $e) {
    echo "FEHLER: " . $e->getMessage() . "\n";
}
