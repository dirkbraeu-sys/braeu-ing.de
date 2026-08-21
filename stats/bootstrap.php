<?php
declare(strict_types=1);

// Eigenes, schlankes Statistik-Tool. Erfasst keine IP-Adressen, keine
// personenbezogenen Daten – nur eine zufällige, täglich wechselnde
// Besucher-ID (Cookie), aufgerufene Seite, Referrer-Host und Gerätetyp.

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('braeuing_stats_admin');
    session_start();
}

// Nutzt dieselbe Datenbank/Zugangsdaten wie der Kundenlogin.
$dbConfigFile = __DIR__ . '/../kunden/db-config.php';
if (!file_exists($dbConfigFile)) {
    http_response_code(503);
    exit('Statistik momentan nicht verfügbar.');
}
require_once $dbConfigFile;

function stats_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pageviews (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            visited_at DATETIME NOT NULL,
            visitor_id CHAR(32) NOT NULL,
            path VARCHAR(255) NOT NULL,
            referrer_host VARCHAR(255) NULL,
            device VARCHAR(20) NOT NULL,
            browser VARCHAR(30) NOT NULL,
            KEY idx_visited_at (visited_at),
            KEY idx_path (path),
            KEY idx_visitor (visitor_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $done = true;
}

function stats_detect_device(string $ua): string
{
    if (preg_match('/tablet|ipad/i', $ua)) {
        return 'tablet';
    }
    if (preg_match('/mobile|android|iphone/i', $ua)) {
        return 'mobile';
    }
    return 'desktop';
}

function stats_detect_browser(string $ua): string
{
    if (stripos($ua, 'Edg/') !== false) return 'Edge';
    if (stripos($ua, 'Chrome/') !== false && stripos($ua, 'Chromium') === false) return 'Chrome';
    if (stripos($ua, 'Firefox/') !== false) return 'Firefox';
    if (stripos($ua, 'Safari/') !== false && stripos($ua, 'Chrome') === false) return 'Safari';
    return 'Sonstige';
}
