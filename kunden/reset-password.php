<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    kunden_json(['ok' => false, 'error' => 'Ungültige Anfrage.'], 405);
}

$in = kunden_read_json_or_post();
$token = trim((string) ($in['token'] ?? ''));
$password = (string) ($in['password'] ?? '');
$password2 = (string) ($in['password2'] ?? '');

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    kunden_json(['ok' => false, 'error' => 'Ungültiger oder abgelaufener Link. Bitte fordern Sie einen neuen an.']);
}
if (mb_strlen($password) < 8) {
    kunden_json(['ok' => false, 'error' => 'Das Passwort muss mindestens 8 Zeichen lang sein.']);
}
if ($password !== $password2) {
    kunden_json(['ok' => false, 'error' => 'Die Passwörter stimmen nicht überein.']);
}

$pdo = kunden_db();
kunden_ensure_schema($pdo);

$stmt = $pdo->prepare('SELECT id, name, reset_expires FROM customers WHERE reset_token = ?');
$stmt->execute([$token]);
$row = $stmt->fetch();

if (!$row) {
    kunden_json(['ok' => false, 'error' => 'Ungültiger oder abgelaufener Link. Bitte fordern Sie einen neuen an.']);
}

$expires = new DateTimeImmutable((string) $row['reset_expires']);
if ($expires < new DateTimeImmutable()) {
    kunden_json(['ok' => false, 'error' => 'Der Link ist abgelaufen. Bitte fordern Sie einen neuen an.']);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$upd = $pdo->prepare('UPDATE customers SET password_hash = ?, reset_token = NULL, reset_expires = NULL, last_login = NOW() WHERE id = ?');
$upd->execute([$hash, $row['id']]);

session_regenerate_id(true);
$_SESSION['customer_id'] = (int) $row['id'];
$_SESSION['customer_name'] = $row['name'];

kunden_json(['ok' => true, 'message' => 'Neues Passwort gespeichert. Sie sind jetzt angemeldet.']);
