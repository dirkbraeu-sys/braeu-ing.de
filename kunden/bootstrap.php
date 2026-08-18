<?php
declare(strict_types=1);

// Gemeinsame Grundlage für den Kundenlogin-Bereich.
// Diese Datei ist nicht eigenständig aufrufbar sinnvoll (keine Ausgabe),
// sie wird von den einzelnen Endpunkten eingebunden.

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('braeuing_kunden');
    session_start();
}

$dbConfigFile = __DIR__ . '/db-config.php';
if (!file_exists($dbConfigFile)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Der Kundenbereich ist gerade nicht verfügbar. Bitte später erneut versuchen.']);
    exit;
}
require_once $dbConfigFile;

function kunden_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS customers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(190) NOT NULL,
            email VARCHAR(190) NOT NULL,
            password_hash VARCHAR(255) NULL,
            verified TINYINT(1) NOT NULL DEFAULT 0,
            verification_token CHAR(64) NULL,
            verification_expires DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME NULL,
            UNIQUE KEY uniq_email (email),
            KEY idx_token (verification_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    try {
        $pdo->exec("
            ALTER TABLE customers
                ADD COLUMN IF NOT EXISTS reset_token CHAR(64) NULL,
                ADD COLUMN IF NOT EXISTS reset_expires DATETIME NULL,
                ADD KEY IF NOT EXISTS idx_reset_token (reset_token)
        ");
    } catch (Throwable $e) {
        // Ältere MySQL-Versionen ohne "IF NOT EXISTS" bei ALTER: einfach ignorieren,
        // falls die Spalten schon existieren, schlägt es sonst hier fehl.
    }
    $done = true;
}

function kunden_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function kunden_read_json_or_post(): array
{
    if (!empty($_POST)) {
        return $_POST;
    }
    $raw = file_get_contents('php://input');
    $data = json_decode((string) $raw, true);
    return is_array($data) ? $data : [];
}

function kunden_send_verification_mail(string $toEmail, string $name, string $token): bool
{
    $link = 'https://braeu-ing.de/kundenlogin.html?verify=' . urlencode($token);
    $subject = 'Bitte bestätigen Sie Ihre E-Mail-Adresse – Ingenieurbüro Bräu';
    $safeName = $name !== '' ? $name : 'Kunde/Kundin';
    $body = "Hallo {$safeName},\n\n"
        . "vielen Dank für Ihre Registrierung im Kundenbereich von braeu-ing.de.\n"
        . "Bitte bestätigen Sie Ihre E-Mail-Adresse über folgenden Link (60 Minuten gültig):\n\n"
        . $link . "\n\n"
        . "Danach können Sie sich dort ein Passwort vergeben.\n\n"
        . "Falls Sie diese Registrierung nicht veranlasst haben, ignorieren Sie diese E-Mail einfach.\n\n"
        . "Ingenieurbüro Bräu\n"
        . "https://braeu-ing.de\n";

    $headers = "From: " . kunden_mail_from_header() . "\r\n"
        . "Reply-To: info@braeu-ing.de\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n";

    $ok = mail($toEmail, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    if (!$ok) {
        error_log('kunden_send_verification_mail: mail() lieferte false für ' . $toEmail);
    }
    return $ok;
}

function kunden_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strlen($email) <= 190;
}

function kunden_mail_from_header(): string
{
    return '=?UTF-8?B?' . base64_encode('Ingenieurbüro Bräu') . '?=' . ' <info@braeu-ing.de>';
}

function kunden_send_password_reset_mail(string $toEmail, string $name, string $token): bool
{
    $link = 'https://braeu-ing.de/kundenlogin.html?reset=' . urlencode($token);
    $subject = 'Passwort zurücksetzen – Ingenieurbüro Bräu';
    $safeName = $name !== '' ? $name : 'Kunde/Kundin';
    $body = "Hallo {$safeName},\n\n"
        . "für Ihr Konto im Kundenbereich von braeu-ing.de wurde ein neues Passwort angefordert.\n"
        . "Über folgenden Link können Sie ein neues Passwort vergeben (30 Minuten gültig):\n\n"
        . $link . "\n\n"
        . "Falls Sie das nicht angefordert haben, ignorieren Sie diese E-Mail einfach – Ihr Passwort bleibt unverändert.\n\n"
        . "Ingenieurbüro Bräu\n"
        . "https://braeu-ing.de\n";

    $headers = "From: " . kunden_mail_from_header() . "\r\n"
        . "Reply-To: info@braeu-ing.de\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n";

    $ok = mail($toEmail, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    if (!$ok) {
        error_log('kunden_send_password_reset_mail: mail() lieferte false für ' . $toEmail);
    }
    return $ok;
}
