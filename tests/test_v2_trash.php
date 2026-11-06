<?php
declare(strict_types=1);
require_once __DIR__.'/../assets/php/classes/TrashManager.php';
$root=sys_get_temp_dir().'/files_v2_test_'.bin2hex(random_bytes(5));
$private=$root.'/private';$drive=$root.'/drive';
mkdir($drive.'/album',0777,true);mkdir($private,0700,true);
file_put_contents($drive.'/album/song.txt','original');
mkdir($drive.'/album/empty');
$mgr=new TrashManager(['T:'=>$drive],$private);
function check(bool $cond,string $message): void{if(!$cond)throw new Exception('FAIL: '.$message);echo "PASS: $message\n";}
try {
    $deleted=$mgr->put('T:','',['album'],true);
    check($deleted[0]['ok']===true,'Ordner rekursiv verschoben');
    check(!file_exists($drive.'/album'),'Quelle nicht mehr vorhanden');
    check(count($mgr->entries())===1,'Eintrag in Papierkorbliste');
    mkdir($drive.'/album');
    try{$mgr->restore($deleted[0]['id']);throw new Exception('Überschreiben wurde nicht blockiert');}catch(RuntimeException $e){check(str_contains($e->getMessage(),'existiert'),'Überschreibschutz');}
    rmdir($drive.'/album');
    $mgr->restore($deleted[0]['id']);
    check(file_get_contents($drive.'/album/song.txt')==='original','Datei wiederhergestellt');
    check(is_dir($drive.'/album/empty'),'Leerer Unterordner wiederhergestellt');
    check(count($mgr->entries())===0,'Papierkorb nach Restore leer');
    check(count($mgr->recentLog())===2,'Zwei Auditereignisse');
    try{$mgr->put('T:','',['../escape'],true);throw new Exception('Path traversal akzeptiert');}catch(InvalidArgumentException $e){check(true,'Pfad-Traversal blockiert');}
}finally {
    $iter=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iter as $item){if($item->isDir())rmdir($item->getPathname());else unlink($item->getPathname());}rmdir($root);
}
