<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (empty($_SESSION['stats_authed'])) {
    http_response_code(403);
    exit('Nicht angemeldet.');
}

$range = (int) ($_GET['range'] ?? 30);
if (!in_array($range, [7, 30, 90], true)) {
    $range = 30;
}

$pdo = kunden_db();
stats_ensure_schema($pdo);

$stmt = $pdo->prepare("
    SELECT visited_at, path, referrer_host, device, browser
    FROM pageviews
    WHERE visited_at >= DATE_SUB(NOW(), INTERVAL :r DAY)
    ORDER BY visited_at ASC
");
$stmt->execute(['r' => $range]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="statistik-braeu-ing-' . $range . 'tage.csv"');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // BOM für korrekte Umlaute in Excel
fputcsv($out, ['Zeitpunkt', 'Seite', 'Referrer', 'Geraet', 'Browser'], ';');
while ($row = $stmt->fetch()) {
    fputcsv($out, [
        $row['visited_at'],
        $row['path'],
        $row['referrer_host'] ?? '',
        $row['device'],
        $row['browser'],
    ], ';');
}
fclose($out);
