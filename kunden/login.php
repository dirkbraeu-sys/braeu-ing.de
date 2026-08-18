<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    kunden_json(['ok' => false, 'error' => 'Ungültige Anfrage.'], 405);
}

$in = kunden_read_json_or_post();

if (!empty($in['website'])) {
    // Honeypot ausgelöst: Bot vorgeben, dass es geklappt hat, aber nichts tun.
    kunden_json(['ok' => false, 'error' => 'E-Mail/Name oder Passwort ist falsch.']);
}

$identifier = trim((string) ($in['identifier'] ?? ''));
$password = (string) ($in['password'] ?? '');

if ($identifier === '' || $password === '') {
    kunden_json(['ok' => false, 'error' => 'Bitte E-Mail/Name und Passwort angeben.']);
}

$pdo = kunden_db();
kunden_ensure_schema($pdo);

$stmt = $pdo->prepare('SELECT id, name, email, password_hash, verified FROM customers WHERE email = ? OR name = ? LIMIT 1');
$stmt->execute([mb_strtolower($identifier, 'UTF-8'), $identifier]);
$row = $stmt->fetch();

$genericError = 'E-Mail/Name oder Passwort ist falsch.';

if (!$row || (int) $row['verified'] !== 1 || !$row['password_hash']) {
    usleep(300000);
    kunden_json(['ok' => false, 'error' => $genericError]);
}

if (!password_verify($password, $row['password_hash'])) {
    usleep(300000);
    kunden_json(['ok' => false, 'error' => $genericError]);
}

session_regenerate_id(true);
$_SESSION['customer_id'] = (int) $row['id'];
$_SESSION['customer_name'] = $row['name'];

$upd = $pdo->prepare('UPDATE customers SET last_login = NOW() WHERE id = ?');
$upd->execute([$row['id']]);

kunden_json(['ok' => true]);
