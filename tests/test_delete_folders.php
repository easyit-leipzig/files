<?php
declare(strict_types=1);
require_once __DIR__.'/../assets/php/classes/Files.php';
$root=sys_get_temp_dir().'/files-delete-test-'.bin2hex(random_bytes(6));
mkdir($root); mkdir($root.'/a'); mkdir($root.'/a/sub');
file_put_contents($root.'/a/sub/one.txt','hello'); file_put_contents($root.'/a/two.txt','hey');
file_put_contents($root.'/keep.txt','safe');
try {
  $f=new Files(['test'=>$root]);
  $preview=$f->deleteEntries('test','',['a','a/sub/one.txt'],false);
  if(count($preview)!==1 || $preview[0]['files']!==2 || $preview[0]['directories']!==2) throw new RuntimeException('Preview incorrect: '.json_encode($preview));
  try {$f->deleteEntries('test','',['a'],true,false);throw new RuntimeException('Missing confirmation accepted');} catch(InvalidArgumentException $e) {}
  try {$f->deleteEntries('test','',['..'],false);throw new RuntimeException('Traversal accepted');} catch(InvalidArgumentException $e) {}
  try {$f->deleteEntries('test','',['.'],false);throw new RuntimeException('Root accepted');} catch(InvalidArgumentException $e) {}
  $result=$f->deleteEntries('test','',['a'],true,true);
  if(count($result)!==1 || !$result[0]['ok'] || is_dir($root.'/a') || !is_file($root.'/keep.txt')) throw new RuntimeException('Deletion result incorrect');
  echo "PASS recursive folder deletion, preview, confirmation, traversal blocking, non-selected file retained\n";
} finally { @unlink($root.'/keep.txt'); @rmdir($root); }
