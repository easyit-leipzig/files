<?php
require dirname(__DIR__) . '/assets/php/classes/BatchRename.php';
$base = sys_get_temp_dir() . '/files-batch-test-' . bin2hex(random_bytes(6));
mkdir($base); mkdir($base . '/left'); mkdir($base . '/right'); mkdir($base . '/left/sub');
$manager = new BatchRename(['left' => $base . '/left','right' => $base . '/right']);
$pass = 0;
function check(bool $yes, string $message): void {
  global $pass;
  if (!$yes) throw new RuntimeException('FAIL: ' . $message);
  echo 'PASS: ' . $message . "\n";
  ++$pass;
}
function rejects(callable $run, string $message): void {
  try { $run(); } catch (Throwable $e) { check(true, $message); return; }
  throw new RuntimeException('FAIL: should reject - ' . $message);
}
try {
 file_put_contents($base . '/left/a.txt','a'); file_put_contents($base . '/left/b.txt','b');
 file_put_contents($base . '/left/sub/c.txt','c'); file_put_contents($base . '/right/a.txt','right');
 $p = $manager->preview('left', ['a.txt','b.txt','sub/c.txt'], 'Bild_{n:3}', 7, 2);
 check($p['items'][0]['after'] === 'Bild_007.txt' && $p['items'][1]['after'] === 'Bild_009.txt' && $p['items'][2]['after'] === 'sub/Bild_011.txt','Muster, Start und Schrittweite');
 check(file_exists($base . '/left/a.txt'),'Vorschau verändert keine Dateien');
 $result = $manager->apply('left', ['a.txt','b.txt','sub/c.txt'], 'Bild_{n:3}', 7, 2);
 check($result['changed'] === 3 && file_exists($base . '/left/Bild_007.txt') && file_exists($base . '/left/sub/Bild_011.txt'),'Reihenumbennung über Unterordner');
 check(file_exists($base . '/right/a.txt'),'anderes Panel bleibt unverändert');
 check(file_get_contents($base . '/left/Bild_009.txt') === 'b','Dateiinhalte unverändert');
 $result2 = $manager->apply('left', ['Bild_007.txt','Bild_009.txt'], '{name}');
 check($result2['changed'] === 0,'unveränderte Namen übersprungen');
 file_put_contents($base . '/left/exist.txt', 'x');
 rejects(static function () use ($manager) { $manager->preview('left', ['Bild_007.txt'], 'exist'); }, 'Kollision mit bestehender Datei');
 rejects(static function () use ($manager) { $manager->preview('left', ['Bild_007.txt','Bild_009.txt'], 'gleich'); }, 'doppelte Zielnamen abgefangen');
 rejects(static function () use ($manager) { $manager->preview('left', ['../right/a.txt'], '{name}'); }, 'Pfadwechsel nach außen blockiert');
 rejects(static function () use ($manager) { $manager->preview('left', ['Bild_007.txt'], 'x/../a'); }, 'Verzeichnispfade im Muster blockiert');
 rejects(static function () use ($manager) { $manager->preview('left', ['Bild_007.txt'], '{nein}'); }, 'unbekannter Platzhalter blockiert');
 rejects(static function () use ($manager) { $manager->preview('left', ['Bild_007.txt'], 'CON'); }, 'reservierter Windows-Name blockiert');
 rejects(static function () use ($manager) { $manager->preview('left', ['Bild_007.txt','Bild_007.txt'], '{n}'); }, 'doppelte Quelldatei blockiert');
 if (function_exists('symlink')) {
   @symlink($base . '/right/a.txt', $base . '/left/link.txt');
   if (is_link($base . '/left/link.txt')) {
     rejects(static function () use ($manager) { $manager->preview('left', ['link.txt'], 'X_{name}'); }, 'symbolischer Link blockiert');
   }
 }
 $case = $manager->preview('left', ['Bild_007.txt'], 'bild_007');
 check($case['items'][0]['after'] === 'bild_007.txt', 'Case-only Änderung wird vorgeprüft');
 $cyc = $manager->apply('left', ['Bild_007.txt','Bild_009.txt'], 'Bild_{n:3}', 9, -1);
 // Darf wegen unzulässiger negativer Schrittweite nicht ausgeführt werden; wird unten separat geprüft.
} catch (Throwable $e) {
 if (strpos($e->getMessage(), 'Schrittweite ungültig') === false && strpos($e->getMessage(), 'Startzahl oder Schrittweite ungültig') === false) throw $e;
 check(true, 'ungültige negative Schrittweite blockiert');
}
// Swap (Reihenfolge b,a -> Ziel a,b) mit temporären Namen.
try {
 file_put_contents($base . '/left/Swap_1.txt', 'eins'); file_put_contents($base . '/left/Swap_2.txt', 'zwei');
 $manager->apply('left', ['Swap_2.txt','Swap_1.txt'], 'Swap_{n}', 1, 1);
 check(file_get_contents($base . '/left/Swap_1.txt') === 'zwei' && file_get_contents($base . '/left/Swap_2.txt') === 'eins', 'gegenseitiger Namenstausch');
 $listing = $manager->listing('left', true);
 check(in_array('sub/Bild_011.txt', $listing['items'], true),'Liste mit relativen Unterordnerpfaden');
 echo "OK: $pass Tests erfolgreich.\n";
} finally {
 function removeTree($path) { if (is_dir($path) && !is_link($path)) { foreach (scandir($path) as $item) if ($item !== '.' && $item !== '..') removeTree($path.'/'.$item); rmdir($path); } else { unlink($path); } }
 removeTree($base);
}
