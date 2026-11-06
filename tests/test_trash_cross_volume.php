<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/assets/php/classes/Files.php';
require_once dirname(__DIR__).'/assets/php/classes/TrashManager.php';
$local=sys_get_temp_dir().'/easyit-files-regr-'.bin2hex(random_bytes(5));
$other=PHP_OS_FAMILY==='Linux' && is_dir('/dev/shm') && is_writable('/dev/shm') ? '/dev/shm' : sys_get_temp_dir();
$private=$other.'/easyit-files-trash-regr-'.bin2hex(random_bytes(5));
mkdir($local);mkdir($local.'/drive');mkdir($local.'/drive/dir');mkdir($private);
$root=$local.'/drive';
function checkTrash(bool $ok,string $msg): void {if(!$ok)throw new RuntimeException('FAIL: '.$msg);echo 'PASS: '.$msg."\n";}
function cleanTrash(string $directory): void {
    if(!is_dir($directory))return;
    foreach(new FilesystemIterator($directory,FilesystemIterator::SKIP_DOTS) as $file){
        $p=$file->getPathname();if($file->isDir()&&!$file->isLink())cleanTrash($p);else unlink($p);
    }
    rmdir($directory);
}
try{
    $files=new Files(['T:'=>$root]);$trash=new TrashManager(['T:'=>$root],$private);
    file_put_contents($root.'/keep.txt','KEPT');file_put_contents($root.'/selected.txt','SELECTED');
    if(function_exists('symlink')){
        @symlink($root.'/keep.txt',$root.'/alias.txt');
        if(is_link($root.'/alias.txt')){
            try{$files->deleteEntries('T:','',['alias.txt']);throw new RuntimeException('Symlink in Vorschau akzeptiert');}catch(InvalidArgumentException $e){}
            try{$trash->put('T:','',['alias.txt'],true);throw new RuntimeException('Symlink verschoben');}catch(InvalidArgumentException $e){}
            checkTrash(file_get_contents($root.'/keep.txt')==='KEPT','Symlink kann ausgewählte Quelldatei nicht umleiten');
            unlink($root.'/alias.txt');
        }
    }
    $deleted=$trash->put('T:','',['selected.txt'],true);
    checkTrash(($deleted[0]['ok']??false)&&!file_exists($root.'/selected.txt')&&file_get_contents($root.'/keep.txt')==='KEPT','Nur markierte Datei in Papierkorb');
    $trash->restore($deleted[0]['id']);
    checkTrash(file_get_contents($root.'/selected.txt')==='SELECTED','Dateiwiederherstellung möglich');
    file_put_contents($root.'/dir/nested.txt','NESTED');mkdir($root.'/dir/empty');
    $folder=$trash->put('T:','',['dir'],true);
    checkTrash(($folder[0]['ok']??false)&&!is_dir($root.'/dir'),'Ordner inklusive Unterordner verschoben');
    $trash->restore($folder[0]['id']);
    checkTrash(is_dir($root.'/dir/empty')&&file_get_contents($root.'/dir/nested.txt')==='NESTED','Ordner und leeres Unterverzeichnis wiederhergestellt');
    if(stat($local)['dev']!==stat($private)['dev'])echo "PASS: Laufwerksübergreifende Kopie und Wiederherstellung getestet\n";
    else echo "INFO: Kein separates Testlaufwerk – normales Verschieben getestet\n";
} finally {cleanTrash($local);cleanTrash($private);}
