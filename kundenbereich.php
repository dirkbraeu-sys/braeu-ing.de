<?php
declare(strict_types=1);
require __DIR__ . '/kunden/bootstrap.php';

if (empty($_SESSION['customer_id'])) {
    header('Location: /kundenlogin.html');
    exit;
}

$name = htmlspecialchars((string) ($_SESSION['customer_name'] ?? 'Kunde/Kundin'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<title>Kundenbereich – Ingenieurbüro Bräu</title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/img/favicon-50x50.jpg">
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="site-header">
  <div class="wrap">
    <a href="index.html" class="brand">
      <img class="mark" src="assets/img/favicon-50x50.jpg" alt="Ingenieurbüro Bräu Logo">
      <span>Ingenieurbüro Bräu<small>Energieberatung &amp; Energieaudit</small></span>
    </a>
    <nav class="main-nav">
      <a href="index.html">Leistungen</a>
      <a href="din-en-16247-1.html">DIN EN 16247-1</a>
      <a href="ueber-uns.html">Über uns</a>
      <a href="vorgehensweise.html">Vorgehensweise</a>
      <a href="kontakt.html">Kontakt</a>
      <a href="index.html#faq">FAQ</a>
      <a href="kunden/logout.php" class="nav-cta">Abmelden</a>
    </nav>
    <button class="nav-toggle" aria-label="Menü öffnen" aria-expanded="false">☰</button>
  </div>
</header>

<main>
  <section class="hero" style="padding-bottom:40px;">
    <div class="wrap" style="grid-template-columns:1fr;">
      <div>
        <span class="eyebrow">Kundenbereich</span>
        <h1>Willkommen, <span class="accent"><?= $name ?></span></h1>
        <p class="lead">Sie sind erfolgreich angemeldet.</p>
      </div>
    </div>
  </section>

  <section style="padding-top:0;">
    <div class="wrap">
      <div class="card" style="max-width:640px;">
        <h3>Ihr Kundenbereich befindet sich im Aufbau</h3>
        <p>Hier werden künftig Unterlagen, Auswertungen und Termine zu Ihrem Energieaudit für Sie bereitstehen. Bei Fragen erreichen Sie uns jederzeit unter <a href="mailto:info@braeu-ing.de">info@braeu-ing.de</a>.</p>
      </div>
    </div>
  </section>
</main>

<footer class="site-footer">
  <div class="wrap">
    <div>
      <h4>Ingenieurbüro Bräu</h4>
      <p style="color:#8b968f; font-size:0.88rem; max-width:32ch;">Energieberatung und Energieaudit für Unternehmen in München und Bayern. Seit 1999.</p>
    </div>
    <div>
      <h4>Navigation</h4>
      <ul>
        <li><a href="index.html">Leistungen</a></li>
        <li><a href="kontakt.html">Kontakt/Anfrage</a></li>
        <li><a href="kunden/logout.php">Abmelden</a></li>
      </ul>
    </div>
    <div>
      <h4>Kontakt</h4>
      <ul>
        <li>Leostrasse 7, 81375 München</li>
        <li>+49 160 930 974 81</li>
        <li>info@braeu-ing.de</li>
      </ul>
    </div>
  </div>
  <div class="wrap bottom">
    <span>© Ingenieurbüro Bräu · Dirk Bräu</span>
    <span><a href="impressum.html">Impressum / Datenschutz</a></span>
  </div>
</footer>
<script src="assets/js/main.js"></script>
</body>
</html>
