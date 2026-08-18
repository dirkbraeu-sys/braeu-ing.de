<?php
header('Content-Type: text/plain; charset=utf-8');
echo "PHP: " . phpversion() . "\n";
echo "__DIR__: " . __DIR__ . "\n";
echo "parent: " . dirname(__DIR__) . "\n";
echo "\n--- contents of __DIR__ ---\n";
foreach (scandir(__DIR__) as $f) echo $f . "\n";
echo "\n--- contents of parent ---\n";
$p = dirname(__DIR__);
if (is_readable($p)) {
    foreach (scandir($p) as $f) echo $f . "\n";
} else {
    echo "(not readable)\n";
}
echo "\n--- send-message.php head ---\n";
$sm = __DIR__ . '/send-message.php';
if (file_exists($sm)) {
    $lines = file($sm);
    echo implode('', array_slice($lines, 0, 40));
} else {
    echo "(not found)\n";
}
echo "\n--- extensions ---\n";
echo "pdo_mysql: " . (extension_loaded('pdo_mysql') ? 'yes' : 'no') . "\n";
echo "mysqli: " . (extension_loaded('mysqli') ? 'yes' : 'no') . "\n";
echo "mail() exists: " . (function_exists('mail') ? 'yes' : 'no') . "\n";
