<?php
declare(strict_types=1);
require_once __DIR__.'/../assets/php/classes/Files.php';
$root=sys_get_temp_dir().'/files_folderrows_'.bin2hex(random_bytes(6));
mkdir($root);mkdir($root.'/Unterordner');mkdir($root.'/Unterordner/Tiefer');
file_put_contents($root.'/a.txt','a');file_put_contents($root.'/Unterordner/b.md','b');
try {
    $files=new Files(['T'=>$root]);
    $test=function(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";};
    $rows=$files->list('T','','','',false,[],5000,'*.md');
    $test(count($rows)===1 && $rows[0]['isDirectory'] && $rows[0]['name']==='Unterordner','Ordner trotz nicht passendem Dateifilter sichtbar');
    $rows=$files->list('T','','','',true,[],5000,'*.md');
    $test(count(array_filter($rows,fn($r)=>$r['isDirectory']))===2,'Rekursive Ordner sichtbar');
    $test(count(array_filter($rows,fn($r)=>!$r['isDirectory']))===1,'Rekursive Dateifilterung funktioniert');
    $rows=$files->list('T','','','',false,[],5000,'*.md',true);
    $test(count($rows)===2 && count(array_filter($rows,fn($r)=>$r['isDirectory']))===1,'Invertierter Dateifilter lässt Ordner sichtbar');
    $pairs=$files->duplicates('T','T','','',true);
    $test(count($pairs)===2,'Dublettenvergleich ignoriert Ordner');
}finally{unlink($root.'/a.txt');unlink($root.'/Unterordner/b.md');rmdir($root.'/Unterordner/Tiefer');rmdir($root.'/Unterordner');rmdir($root);}
