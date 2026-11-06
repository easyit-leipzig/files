<?php
declare(strict_types=1);
require_once __DIR__.'/../assets/php/classes/Files.php';
$base=sys_get_temp_dir().'/files_transfer_test_'.bin2hex(random_bytes(5));
mkdir($base);mkdir($base.'/source');mkdir($base.'/target');
try {
 $f=new Files(['S'=>$base.'/source','T'=>$base.'/target']);
 $put=function($dir,$name,$text,$time) use ($base) {file_put_contents($base.'/'.$dir.'/'.$name,$text);touch($base.'/'.$dir.'/'.$name,$time);};
 $check=function($ok,$message){if(!$ok)throw new Exception('FAIL: '.$message); echo 'PASS: '.$message."\n";};
 $put('source','same.txt','SRC',1100);$put('target','same.txt','TARGET',1000);
 $r=$f->transfer('copy','S',['same.txt'],'T','','newer',false);$check($r[0]['decision']==='replace','newer preview');
 $r=$f->transfer('copy','S',['same.txt'],'T','','newer',true);$check($r[0]['ok']&&file_get_contents($base.'/target/same.txt')==='SRC','newer copy replaces');
 $put('source','same.txt','S2',900);
 $r=$f->transfer('move','S',['same.txt'],'T','','newer',true);$check($r[0]['decision']==='skip'&&file_exists($base.'/source/same.txt'),'older source remains on move skip');
 $r=$f->transfer('copy','S',['same.txt'],'T','','larger',true);$check($r[0]['decision']==='skip','larger remains');
 $r=$f->transfer('copy','S',['same.txt'],'T','','rename',true);$check($r[0]['decision']==='rename'&&file_exists($base.'/target/same (1).txt'),'rename collision');
 $put('source','new.txt','NEW',1500);
 $r=$f->transfer('move','S',['new.txt'],'T','','source',true);$check($r[0]['ok']&&file_exists($base.'/target/new.txt')&&!file_exists($base.'/source/new.txt'),'move new file');
 $put('source','same.txt','SRC',1200);
 $r=$f->transfer('copy','S',['same.txt'],'T','','target',true);$check($r[0]['decision']==='skip'&&file_get_contents($base.'/target/same.txt')==='SRC','keep target');
 $put('source','same.txt','SRC',1200);
 $r=$f->transfer('copy','S',['same.txt'],'T','','identical',true);$check($r[0]['decision']==='skip','identical hash skip');
 echo "TESTS OK\n";
} finally {foreach(['source','target'] as $dir){foreach(glob($base.'/'.$dir.'/*') as $file)unlink($file);rmdir($base.'/'.$dir);}rmdir($base);}
