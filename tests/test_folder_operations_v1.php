<?php
declare(strict_types=1);
require_once __DIR__.'/../assets/php/classes/Files.php';
require_once __DIR__.'/../assets/php/classes/FolderOperations.php';
$base=sys_get_temp_dir().'/files-v1-'.bin2hex(random_bytes(5));
mkdir($base);mkdir($base.'/left');mkdir($base.'/right');
$roots=['left'=>$base.'/left','right'=>$base.'/right'];
$f=new Files($roots);$m=new FolderOperations($roots,$f);
function check(bool $ok,string $msg):void {if(!$ok) throw new RuntimeException('FAIL: '.$msg);echo "PASS: $msg\n";}
function rr(string $p):void {if(is_dir($p)&&!is_link($p)){foreach(new FilesystemIterator($p) as $item)rr($item->getPathname());rmdir($p);}else if(file_exists($p)||is_link($p))unlink($p);}
try {
  $m->create('left','','A');$m->create('left','A','nested');$m->create('left','A','empty');
  file_put_contents($base.'/left/A/nested/data.txt','source-v1');
  file_put_contents($base.'/left/A/root.txt','root');
  check(is_dir($base.'/left/A/empty'),'create nested empty folder');
  $m->rename('left','A/empty','vacant');
  check(is_dir($base.'/left/A/vacant'),'rename folder');
  $preview=$m->transfer('copy','left',['A'],'right','','source');
  check(count($preview)===2 && !is_dir($base.'/right/A'),'preview has no side effects');
  $result=$m->transfer('copy','left',['A'],'right','','source',true);
  check(count($result)===2 && is_file($base.'/right/A/nested/data.txt'),'recursive copy');
  check(is_dir($base.'/right/A/vacant'),'empty directory preserved');
  check(file_exists($base.'/left/A/root.txt'),'copy retains source');
  file_put_contents($base.'/right/A/nested/data.txt','target-is-here');
  unlink($base.'/right/A/root.txt');
  $skip=$m->transfer('move','left',['A'],'right','','target',true);
  check(count(array_filter($skip,fn($x)=>$x['decision']==='skip'))>=1,'keep target policy');
  check(file_exists($base.'/left/A/nested/data.txt'),'skipped move source preserved');
  check(!file_exists($base.'/left/A/root.txt'),'moved nonconflicting file removed');
  file_put_contents($base.'/left/other.txt','OTHER');
  $m->transfer('copy','left',['other.txt'],'right','','source',true);
  check(file_get_contents($base.'/right/other.txt')==='OTHER','copy standalone file');
  try {$m->transfer('copy','left',['A'],'left','A','source');throw new RuntimeException('Missing containment guard');}
  catch (RuntimeException $e) {check(str_contains($e->getMessage(),'darf nicht'),'reject copy into source subtree');}
  try {$m->rename('left','A','..');throw new RuntimeException('Missing name guard');}
  catch (InvalidArgumentException $e) {check(true,'invalid folder name refused');}
  try {$m->create('left','','A');throw new RuntimeException('Missing collision guard');}
  catch (RuntimeException $e) {check(true,'existing folder not overwritten');}
  echo "ALL V1 TESTS PASSED\n";
} finally {rr($base);}
