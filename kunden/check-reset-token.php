<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? ''));
if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    kunden_json(['ok' => false, 'error' => 'Ungültiger Link.']);
}

$pdo = kunden_db();
kunden_ensure_schema($pdo);

$stmt = $pdo->prepare('SELECT id, name, reset_expires FROM customers WHERE reset_token = ?');
$stmt->execute([$token]);
$row = $stmt->fetch();

if (!$row) {
    kunden_json(['ok' => false, 'error' => 'Der Link ist ungültig oder wurde bereits verwendet.']);
}

$expires = new DateTimeImmutable((string) $row['reset_expires']);
if ($expires < new DateTimeImmutable()) {
    kunden_json(['ok' => false, 'error' => 'Der Link ist abgelaufen. Bitte fordern Sie einen neuen an.']);
}

kunden_json(['ok' => true, 'name' => $row['name'], 'token' => $token]);
