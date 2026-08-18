<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    kunden_json(['ok' => false, 'error' => 'Ungültige Anfrage.'], 405);
}

$in = kunden_read_json_or_post();

// Honeypot: Bots füllen unsichtbare Felder aus, echte Nutzer nicht.
if (!empty($in['website'])) {
    kunden_json(['ok' => true]);
}

$name = trim((string) ($in['name'] ?? ''));
$email = trim(mb_strtolower((string) ($in['email'] ?? ''), 'UTF-8'));

if ($name === '' || mb_strlen($name) > 190) {
    kunden_json(['ok' => false, 'error' => 'Bitte geben Sie Ihren Namen an.']);
}
if (!kunden_valid_email($email)) {
    kunden_json(['ok' => false, 'error' => 'Bitte geben Sie eine gültige E-Mail-Adresse an.']);
}

$pdo = kunden_db();
kunden_ensure_schema($pdo);

$stmt = $pdo->prepare('SELECT id, verified, password_hash FROM customers WHERE email = ?');
$stmt->execute([$email]);
$existing = $stmt->fetch();

$token = bin2hex(random_bytes(32));
$expires = (new DateTimeImmutable('+60 minutes'))->format('Y-m-d H:i:s');

if ($existing) {
    if ((int) $existing['verified'] === 1 && $existing['password_hash']) {
        // Bereits vollständig registriert – aus Datenschutzgründen keine Bestätigung, ob die
        // E-Mail existiert, aber ein hilfreicher Hinweis ohne Details preiszugeben.
        kunden_json(['ok' => false, 'error' => 'Für diese E-Mail-Adresse besteht bereits ein Konto. Bitte melden Sie sich an.']);
    }
    // Existiert, aber noch nicht abgeschlossen: neuen Token vergeben und erneut zusenden.
    $upd = $pdo->prepare('UPDATE customers SET name = ?, verification_token = ?, verification_expires = ? WHERE id = ?');
    $upd->execute([$name, $token, $expires, $existing['id']]);
} else {
    $ins = $pdo->prepare('INSERT INTO customers (name, email, verified, verification_token, verification_expires) VALUES (?, ?, 0, ?, ?)');
    $ins->execute([$name, $email, $token, $expires]);
}

kunden_send_verification_mail($email, $name, $token);

kunden_json(['ok' => true, 'message' => 'Vielen Dank! Wir haben Ihnen eine E-Mail mit einem Bestätigungslink gesendet.']);
