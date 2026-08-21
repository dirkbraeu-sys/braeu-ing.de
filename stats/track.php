<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('{}');
}

// Do-Not-Track respektieren.
if (($_SERVER['HTTP_DNT'] ?? '') === '1') {
    echo '{}';
    exit;
}

$raw = file_get_contents('php://input');
$in = json_decode((string) $raw, true);
if (!is_array($in)) {
    echo '{}';
    exit;
}

$path = trim((string) ($in['path'] ?? ''));
if ($path === '' || mb_strlen($path) > 255) {
    echo '{}';
    exit;
}

// Referrer nur als Host speichern (kein voller Link mit ggf. sensiblen Query-Parametern).
$referrerHost = null;
$ref = trim((string) ($in['ref'] ?? ''));
if ($ref !== '') {
    $host = parse_url($ref, PHP_URL_HOST);
    if ($host && $host !== 'braeu-ing.de' && $host !== 'www.braeu-ing.de') {
        $referrerHost = mb_substr($host, 0, 255);
    }
}

// Besucher-ID: zufällig, nur 1 Tag gültig (kein Langzeit-Profil), rein Cookie-basiert.
$visitorId = $_COOKIE['stats_vid'] ?? '';
if (!preg_match('/^[a-f0-9]{32}$/', $visitorId)) {
    $visitorId = bin2hex(random_bytes(16));
}
setcookie('stats_vid', $visitorId, [
    'expires' => time() + 86400,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);

$ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

$pdo = kunden_db();
stats_ensure_schema($pdo);

$stmt = $pdo->prepare('INSERT INTO pageviews (visited_at, visitor_id, path, referrer_host, device, browser) VALUES (NOW(), ?, ?, ?, ?, ?)');
$stmt->execute([
    $visitorId,
    mb_substr($path, 0, 255),
    $referrerHost,
    stats_detect_device($ua),
    stats_detect_browser($ua),
]);

echo '{}';
