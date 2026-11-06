<?php
declare(strict_types=1);
require_once __DIR__.'/../assets/php/classes/ArchiveManager.php';
$root=sys_get_temp_dir().'/files_v5_test_'.bin2hex(random_bytes(5));
mkdir($root);mkdir($root.'/sub');file_put_contents($root.'/a.txt','1234');file_put_contents($root.'/sub/b.md','123');
try {
 $manager=new ArchiveManager(['test'=>$root]);
 $flat=$manager->statistics('test','',false);
 $deep=$manager->statistics('test','',true);
 if($flat['files']!==1||$flat['directories']!==1||$flat['bytes']!==4)throw new RuntimeException('Statistik ohne Unterordner falsch');
 if($deep['files']!==2||$deep['directories']!==1||$deep['bytes']!==7)throw new RuntimeException('Statistik mit Unterordnern falsch');
 foreach(['../escape','/tmp','C:/Windows'] as $unsafe){try{$manager->statistics('test',$unsafe,true);throw new RuntimeException('Unsicherer Pfad akzeptiert');}catch(InvalidArgumentException|RuntimeException $e){if($e->getMessage()==='Unsicherer Pfad akzeptiert')throw $e;}}
 echo "V5 statistics & traversal tests: PASS\n";
}finally{unlink($root.'/sub/b.md');rmdir($root.'/sub');unlink($root.'/a.txt');rmdir($root);}
