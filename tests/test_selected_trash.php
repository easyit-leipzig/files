<?php
declare(strict_types=1);
require_once __DIR__.'/../assets/php/classes/TrashManager.php';
require_once __DIR__.'/../assets/php/classes/Files.php';
$base=sys_get_temp_dir().'/files_selected_'.bin2hex(random_bytes(5));
$drive=$base.'/drive';$private=$base.'/private';
mkdir($drive.'/music',0777,true);
file_put_contents($drive.'/music/selected.mp3','selected');
file_put_contents($drive.'/music/keep.mp3','keep');
try {
  $drives=['C:'=>$drive];
  $files=new Files($drives);
  $preview=$files->deleteEntries('C:','music',['music/selected.mp3']);
  if(count($preview)!==1 || $preview[0]['path']!=='music/selected.mp3') throw new Exception('Falsche Vorschau');
  $trash=new TrashManager($drives,$private);
  $results=$trash->put('C:','music',['music/selected.mp3'],true);
  if(count($results)!==1 || !$results[0]['ok']) throw new Exception('Löschen fehlgeschlagen');
  if(file_exists($drive.'/music/selected.mp3')) throw new Exception('Markierte Datei noch vorhanden');
  if(file_get_contents($drive.'/music/keep.mp3')!=='keep') throw new Exception('Nicht markierte Datei verändert');
  if(count($trash->entries())!==1) throw new Exception('Papierkorb nicht korrekt aktualisiert');
  $trash->restore($results[0]['id']);
  if(file_get_contents($drive.'/music/selected.mp3')!=='selected') throw new Exception('Wiederherstellung fehlgeschlagen');
  echo "PASS: Nur markierte Datei in Papierkorb, andere Datei bleibt bestehen, Restore erfolgreich\n";
} finally {
  $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
  foreach($it as $item) {if($item->isDir()&&!$item->isLink())rmdir($item->getPathname());else unlink($item->getPathname());}
  rmdir($base);
}
