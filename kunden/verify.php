<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? ''));
if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    kunden_json(['ok' => false, 'error' => 'Ungültiger Bestätigungslink.']);
}

$pdo = kunden_db();
kunden_ensure_schema($pdo);

$stmt = $pdo->prepare('SELECT id, name, verification_expires FROM customers WHERE verification_token = ?');
$stmt->execute([$token]);
$row = $stmt->fetch();

if (!$row) {
    kunden_json(['ok' => false, 'error' => 'Der Bestätigungslink ist ungültig oder wurde bereits verwendet.']);
}

$expires = new DateTimeImmutable((string) $row['verification_expires']);
if ($expires < new DateTimeImmutable()) {
    kunden_json(['ok' => false, 'error' => 'Der Bestätigungslink ist abgelaufen. Bitte registrieren Sie sich erneut.']);
}

$upd = $pdo->prepare('UPDATE customers SET verified = 1 WHERE id = ?');
$upd->execute([$row['id']]);

kunden_json(['ok' => true, 'name' => $row['name'], 'token' => $token]);
