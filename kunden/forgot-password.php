<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    kunden_json(['ok' => false, 'error' => 'Ungültige Anfrage.'], 405);
}

$in = kunden_read_json_or_post();

// Immer dieselbe Erfolgsmeldung zurückgeben, unabhängig davon, ob die
// E-Mail-Adresse existiert – so lässt sich nicht ausspähen, wer registriert ist.
$genericMessage = 'Falls ein Konto mit dieser E-Mail-Adresse besteht, haben wir Ihnen eine E-Mail mit einem Link zum Zurücksetzen des Passworts gesendet.';

if (!empty($in['website'])) {
    kunden_json(['ok' => true, 'message' => $genericMessage]);
}

$email = trim(mb_strtolower((string) ($in['email'] ?? ''), 'UTF-8'));
if (!kunden_valid_email($email)) {
    kunden_json(['ok' => false, 'error' => 'Bitte geben Sie eine gültige E-Mail-Adresse an.']);
}

$pdo = kunden_db();
kunden_ensure_schema($pdo);

$stmt = $pdo->prepare('SELECT id, name, password_hash, verified FROM customers WHERE email = ?');
$stmt->execute([$email]);
$row = $stmt->fetch();

if ($row && (int) $row['verified'] === 1 && $row['password_hash']) {
    $token = bin2hex(random_bytes(32));
    $expires = (new DateTimeImmutable('+30 minutes'))->format('Y-m-d H:i:s');
    $upd = $pdo->prepare('UPDATE customers SET reset_token = ?, reset_expires = ? WHERE id = ?');
    $upd->execute([$token, $expires, $row['id']]);
    kunden_send_password_reset_mail($email, $row['name'], $token);
}

kunden_json(['ok' => true, 'message' => $genericMessage]);
