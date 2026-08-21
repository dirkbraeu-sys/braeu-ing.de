<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// bcrypt-Hash des Dashboard-Passworts. Der Hash selbst ist kein Geheimnis
// (das ist der Sinn von password_hash) – das Passwort wurde dem Betreiber
// separat mitgeteilt.
const STATS_PASSWORD_HASH = '$2b$10$Q.4SsOPCL66z0WgFf9gVduIFNd.dmOeMHp0/bOEkq1J8kQSQcNvAC';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['password'])) {
    if (password_verify((string) $_POST['password'], STATS_PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['stats_authed'] = true;
    } else {
        usleep(400000);
        $loginError = 'Falsches Passwort.';
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['stats_authed']);
    session_regenerate_id(true);
}

if (empty($_SESSION['stats_authed'])) {
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Statistik – Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<style>
body { font-family: system-ui, sans-serif; background:#f5f7f2; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
.box { background:#fff; padding:36px 32px; border-radius:14px; box-shadow:0 10px 30px -12px rgba(28,35,33,0.18); width:100%; max-width:340px; }
h1 { font-size:1.1rem; margin:0 0 20px; }
input { width:100%; padding:11px 13px; border:1px solid #e2e7dd; border-radius:8px; font-size:0.95rem; box-sizing:border-box; margin-bottom:14px; }
button { width:100%; padding:12px; border:none; border-radius:8px; background:#6aa421; color:#fff; font-weight:700; cursor:pointer; font-size:0.95rem; }
button:hover { background:#4d7a17; }
.err { color:#a33; font-size:0.85rem; margin:-6px 0 14px; }
</style>
</head>
<body>
<form class="box" method="POST">
  <h1>Statistik-Dashboard</h1>
  <?php if (!empty($loginError)): ?><p class="err"><?= htmlspecialchars($loginError) ?></p><?php endif; ?>
  <input type="password" name="password" placeholder="Passwort" autofocus required>
  <button type="submit">Anmelden</button>
</form>
</body>
</html>
    <?php
    exit;
}

$pdo = kunden_db();
stats_ensure_schema($pdo);

// Zeitraum: 7 / 30 / 90 Tage, Standard 30.
$range = (int) ($_GET['range'] ?? 30);
if (!in_array($range, [7, 30, 90], true)) {
    $range = 30;
}

function stats_count(PDO $pdo, string $where, array $params = []): array
{
    $stmt = $pdo->prepare("SELECT COUNT(*) AS views, COUNT(DISTINCT visitor_id) AS visitors FROM pageviews WHERE $where");
    $stmt->execute($params);
    return $stmt->fetch();
}

$today = stats_count($pdo, 'visited_at >= CURDATE()');
$last7 = stats_count($pdo, 'visited_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
$last30 = stats_count($pdo, 'visited_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
$total = stats_count($pdo, '1=1');

$rangeStmt = $pdo->prepare('SELECT COUNT(*) AS views, COUNT(DISTINCT visitor_id) AS visitors FROM pageviews WHERE visited_at >= DATE_SUB(NOW(), INTERVAL :r DAY)');
$rangeStmt->execute(['r' => $range]);
$rangeTotals = $rangeStmt->fetch();

$topPages = $pdo->prepare("
    SELECT path, COUNT(*) AS views FROM pageviews
    WHERE visited_at >= DATE_SUB(NOW(), INTERVAL :r DAY)
    GROUP BY path ORDER BY views DESC LIMIT 10
");
$topPages->execute(['r' => $range]);
$topPages = $topPages->fetchAll();

$topReferrers = $pdo->prepare("
    SELECT referrer_host, COUNT(*) AS views FROM pageviews
    WHERE visited_at >= DATE_SUB(NOW(), INTERVAL :r DAY) AND referrer_host IS NOT NULL
    GROUP BY referrer_host ORDER BY views DESC LIMIT 10
");
$topReferrers->execute(['r' => $range]);
$topReferrers = $topReferrers->fetchAll();

$devices = $pdo->prepare("
    SELECT device, COUNT(*) AS views FROM pageviews
    WHERE visited_at >= DATE_SUB(NOW(), INTERVAL :r DAY)
    GROUP BY device ORDER BY views DESC
");
$devices->execute(['r' => $range]);
$devices = $devices->fetchAll();

$browsers = $pdo->prepare("
    SELECT browser, COUNT(*) AS views FROM pageviews
    WHERE visited_at >= DATE_SUB(NOW(), INTERVAL :r DAY)
    GROUP BY browser ORDER BY views DESC
");
$browsers->execute(['r' => $range]);
$browsers = $browsers->fetchAll();

$daily = $pdo->prepare("
    SELECT DATE(visited_at) AS d, COUNT(*) AS views, COUNT(DISTINCT visitor_id) AS visitors
    FROM pageviews
    WHERE visited_at >= DATE_SUB(NOW(), INTERVAL :r DAY)
    GROUP BY DATE(visited_at) ORDER BY d ASC
");
$daily->execute(['r' => $range]);
$daily = $daily->fetchAll();
$maxDaily = 1;
foreach ($daily as $d) { $maxDaily = max($maxDaily, (int) $d['views']); }

// Uhrzeit-Verteilung (0–23 Uhr).
$hourly = $pdo->prepare("
    SELECT HOUR(visited_at) AS h, COUNT(*) AS views
    FROM pageviews WHERE visited_at >= DATE_SUB(NOW(), INTERVAL :r DAY)
    GROUP BY HOUR(visited_at)
");
$hourly->execute(['r' => $range]);
$hourlyMap = array_fill(0, 24, 0);
foreach ($hourly->fetchAll() as $row) { $hourlyMap[(int) $row['h']] = (int) $row['views']; }
$maxHourly = max(1, max($hourlyMap));

// Wochentag-Verteilung (1=Montag … 7=Sonntag).
$weekdayNames = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
$weekday = $pdo->prepare("
    SELECT WEEKDAY(visited_at) AS wd, COUNT(*) AS views
    FROM pageviews WHERE visited_at >= DATE_SUB(NOW(), INTERVAL :r DAY)
    GROUP BY WEEKDAY(visited_at)
");
$weekday->execute(['r' => $range]);
$weekdayMap = array_fill(1, 7, 0);
foreach ($weekday->fetchAll() as $row) { $weekdayMap[(int) $row['wd'] + 1] = (int) $row['views']; }
$maxWeekday = max(1, max($weekdayMap));

// Einstiegsseiten: erste Seite je Besucher-ID und Tag im gewählten Zeitraum.
$entryPages = $pdo->prepare("
    SELECT path, COUNT(*) AS entries FROM (
        SELECT path, ROW_NUMBER() OVER (PARTITION BY visitor_id, DATE(visited_at) ORDER BY visited_at) AS rn
        FROM pageviews
        WHERE visited_at >= DATE_SUB(NOW(), INTERVAL :r DAY)
    ) t WHERE rn = 1
    GROUP BY path ORDER BY entries DESC LIMIT 10
");
$entryPages->execute(['r' => $range]);
$entryPages = $entryPages->fetchAll();

// Ø Seiten pro Besuch: ein "Besuch" = Besucher-ID + Tag (Cookie gilt 1 Tag).
$sessionsStmt = $pdo->prepare("
    SELECT COUNT(*) AS views, COUNT(DISTINCT CONCAT(visitor_id, '-', DATE(visited_at))) AS sessions
    FROM pageviews WHERE visited_at >= DATE_SUB(NOW(), INTERVAL :r DAY)
");
$sessionsStmt->execute(['r' => $range]);
$sessionsRow = $sessionsStmt->fetch();
$avgPagesPerVisit = ((int) $sessionsRow['sessions']) > 0
    ? round(((int) $sessionsRow['views']) / ((int) $sessionsRow['sessions']), 1)
    : 0;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Statistik-Dashboard – braeu-ing.de</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<style>
:root { --green:#6aa421; --green-dark:#4d7a17; --ink:#1c2321; --ink-soft:#4a534f; --paper-tint:#f5f7f2; --line:#e2e7dd; }
* { box-sizing:border-box; }
body { font-family: system-ui, sans-serif; background:var(--paper-tint); margin:0; color:var(--ink); }
header { background:#fff; border-bottom:1px solid var(--line); padding:18px 28px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
header h1 { font-size:1.05rem; margin:0; }
header a { color:var(--ink-soft); font-size:0.85rem; text-decoration:none; }
.header-actions { display:flex; align-items:center; gap:16px; }
main { max-width:1100px; margin:0 auto; padding:28px; }
.cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; margin-bottom:28px; }
.card { background:#fff; border:1px solid var(--line); border-radius:12px; padding:18px 20px; }
.card .num { font-size:1.7rem; font-weight:800; }
.card .lbl { color:var(--ink-soft); font-size:0.82rem; }
.card .sub { color:var(--ink-soft); font-size:0.78rem; margin-top:2px; }
.grid2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:28px; }
@media (max-width:800px) { .grid2 { grid-template-columns:1fr; } }
.panel { background:#fff; border:1px solid var(--line); border-radius:12px; padding:20px; }
.panel h2 { font-size:0.95rem; margin:0 0 14px; }
table { width:100%; border-collapse:collapse; font-size:0.86rem; }
td { padding:6px 0; border-bottom:1px solid var(--paper-tint); }
td:last-child { text-align:right; font-weight:700; color:var(--ink-soft); }
.chart { display:flex; align-items:flex-end; gap:3px; height:120px; }
.bar { flex:1; background:var(--green); border-radius:3px 3px 0 0; min-height:2px; position:relative; }
.bar:hover { background:var(--green-dark); }
.bar.alt { background:#9cc25a; }
.bar.alt:hover { background:var(--green-dark); }
.chart-wrap { margin-bottom:28px; }
.chart-labels { display:flex; justify-content:space-between; font-size:0.72rem; color:var(--ink-soft); margin-top:6px; }
.range-switch { display:flex; gap:6px; }
.range-switch a {
  padding:7px 14px; border-radius:8px; border:1px solid var(--line); font-size:0.82rem;
  color:var(--ink-soft); text-decoration:none; font-weight:600;
}
.range-switch a.active { background:var(--green); border-color:var(--green); color:#fff; }
.export-link { display:inline-flex; align-items:center; gap:6px; }
</style>
</head>
<body>
<header>
  <h1>📊 Statistik – braeu-ing.de</h1>
  <div class="header-actions">
    <div class="range-switch">
      <a href="?range=7" class="<?= $range === 7 ? 'active' : '' ?>">7 Tage</a>
      <a href="?range=30" class="<?= $range === 30 ? 'active' : '' ?>">30 Tage</a>
      <a href="?range=90" class="<?= $range === 90 ? 'active' : '' ?>">90 Tage</a>
    </div>
    <a class="export-link" href="export.php?range=<?= $range ?>">⬇ CSV-Export</a>
    <a href="?logout=1">Abmelden</a>
  </div>
</header>
<main>

  <div class="cards">
    <div class="card"><div class="num"><?= (int) $today['views'] ?></div><div class="lbl">Aufrufe heute</div><div class="sub"><?= (int) $today['visitors'] ?> Besucher</div></div>
    <div class="card"><div class="num"><?= (int) $last7['views'] ?></div><div class="lbl">Aufrufe 7 Tage</div><div class="sub"><?= (int) $last7['visitors'] ?> Besucher</div></div>
    <div class="card"><div class="num"><?= (int) $last30['views'] ?></div><div class="lbl">Aufrufe 30 Tage</div><div class="sub"><?= (int) $last30['visitors'] ?> Besucher</div></div>
    <div class="card"><div class="num"><?= (int) $total['views'] ?></div><div class="lbl">Aufrufe gesamt</div><div class="sub">seit Start der Messung</div></div>
    <div class="card"><div class="num"><?= $avgPagesPerVisit ?></div><div class="lbl">Ø Seiten / Besuch</div><div class="sub">im gewählten Zeitraum</div></div>
  </div>

  <div class="panel chart-wrap">
    <h2>Verlauf – letzte <?= $range ?> Tage (<?= (int) $rangeTotals['views'] ?> Aufrufe, <?= (int) $rangeTotals['visitors'] ?> Besucher)</h2>
    <div class="chart">
      <?php foreach ($daily as $d): $h = max(2, round(($d['views'] / $maxDaily) * 120)); ?>
        <div class="bar" style="height:<?= $h ?>px" title="<?= htmlspecialchars($d['d']) ?>: <?= (int) $d['views'] ?> Aufrufe, <?= (int) $d['visitors'] ?> Besucher"></div>
      <?php endforeach; ?>
      <?php if (!$daily): ?><p style="color:var(--ink-soft); font-size:0.85rem;">Noch keine Daten.</p><?php endif; ?>
    </div>
  </div>

  <div class="grid2">
    <div class="panel">
      <h2>Uhrzeit-Verteilung</h2>
      <div class="chart">
        <?php foreach ($hourlyMap as $hour => $v): $h = max(2, round(($v / $maxHourly) * 120)); ?>
          <div class="bar alt" style="height:<?= $h ?>px" title="<?= $hour ?>:00 Uhr: <?= $v ?> Aufrufe"></div>
        <?php endforeach; ?>
      </div>
      <div class="chart-labels"><span>0 Uhr</span><span>12 Uhr</span><span>23 Uhr</span></div>
    </div>
    <div class="panel">
      <h2>Wochentag-Verteilung</h2>
      <div class="chart">
        <?php foreach ($weekdayMap as $wd => $v): $h = max(2, round(($v / $maxWeekday) * 120)); ?>
          <div class="bar alt" style="height:<?= $h ?>px" title="<?= $weekdayNames[$wd] ?>: <?= $v ?> Aufrufe"></div>
        <?php endforeach; ?>
      </div>
      <div class="chart-labels">
        <?php foreach ($weekdayNames as $n): ?><span><?= $n ?></span><?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="grid2">
    <div class="panel">
      <h2>Beliebteste Seiten (<?= $range ?> Tage)</h2>
      <table>
        <?php foreach ($topPages as $p): ?>
          <tr><td><?= htmlspecialchars($p['path']) ?></td><td><?= (int) $p['views'] ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$topPages): ?><tr><td colspan="2">Noch keine Daten.</td></tr><?php endif; ?>
      </table>
    </div>
    <div class="panel">
      <h2>Einstiegsseiten (<?= $range ?> Tage)</h2>
      <table>
        <?php foreach ($entryPages as $p): ?>
          <tr><td><?= htmlspecialchars($p['path']) ?></td><td><?= (int) $p['entries'] ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$entryPages): ?><tr><td colspan="2">Noch keine Daten.</td></tr><?php endif; ?>
      </table>
    </div>
    <div class="panel">
      <h2>Herkunft / Referrer (<?= $range ?> Tage)</h2>
      <table>
        <?php foreach ($topReferrers as $r): ?>
          <tr><td><?= htmlspecialchars($r['referrer_host']) ?></td><td><?= (int) $r['views'] ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$topReferrers): ?><tr><td colspan="2">Direktaufrufe oder keine Daten.</td></tr><?php endif; ?>
      </table>
    </div>
    <div class="panel">
      <h2>Geräte (<?= $range ?> Tage)</h2>
      <table>
        <?php foreach ($devices as $d): ?>
          <tr><td><?= htmlspecialchars(ucfirst($d['device'])) ?></td><td><?= (int) $d['views'] ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>
    <div class="panel">
      <h2>Browser (<?= $range ?> Tage)</h2>
      <table>
        <?php foreach ($browsers as $b): ?>
          <tr><td><?= htmlspecialchars($b['browser']) ?></td><td><?= (int) $b['views'] ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

</main>
</body>
</html>
