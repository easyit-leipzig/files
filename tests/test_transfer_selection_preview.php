<?php
declare(strict_types=1);
require_once __DIR__.'/../assets/php/classes/Files.php';
require_once __DIR__.'/../assets/php/classes/FolderOperations.php';
$base=sys_get_temp_dir().'/files-fast-preview-'.bin2hex(random_bytes(5));
mkdir($base);mkdir($base.'/C');mkdir($base.'/E');
function rrfast(string $p):void {if(is_dir($p)&&!is_link($p)){foreach(new FilesystemIterator($p) as $item)rrfast($item->getPathname());rmdir($p);}elseif(file_exists($p)||is_link($p))unlink($p);}
function chkfast(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException('FAIL '.$msg);echo "PASS $msg\n";}
try {
 $roots=['C'=>$base.'/C','E'=>$base.'/E'];$files=new Files($roots);$m=new FolderOperations($roots,$files);
 mkdir($base.'/C/Downloads');mkdir($base.'/E/Music');mkdir($base.'/E/Music/Alben');mkdir($base.'/E/Music/Alben/Mflex',0775,true);
 mkdir($base.'/C/Downloads/folderA');mkdir($base.'/C/Downloads/folderA/deep');
 file_put_contents($base.'/C/Downloads/folderA/deep/song.mp3','A-song');
 for($i=1;$i<=6;$i++)file_put_contents($base.'/C/Downloads/song'.$i.'.mp3','content'.$i);
 $paths=['Downloads/folderA'];for($i=1;$i<=6;$i++)$paths[]='Downloads/song'.$i.'.mp3';
 $args=['copy','C',$paths,'E','Music/Alben/Mflex','newer'];
 $preview=$m->previewSelection(...$args);
 chkfast(count($preview)===7,'Seven selected entries previewed');
 chkfast($preview[0]['isDirectory']&&$preview[0]['decision']==='copy','Folder summarized without recursive scan');
 chkfast(count(array_filter($preview,fn($r)=>$r['decision']==='error'))===0,'No false preview failures');
 chkfast(!is_dir($base.'/E/Music/Alben/Mflex/folderA'),'Preview has no writes');
 foreach($paths as $p){
  $action=is_dir($base.'/C/'.$p)?$m->transfer('copy','C',[$p],'E','Music/Alben/Mflex','newer',true):$files->transfer('copy','C',[$p],'E','Music/Alben/Mflex','newer',true);
  chkfast(count($action)>=1&&!count(array_filter($action,fn($r)=>empty($r['ok']))),'Apply '.basename($p));
 }
 chkfast(file_get_contents($base.'/E/Music/Alben/Mflex/folderA/deep/song.mp3')==='A-song','Cross-drive directory structure preserved');
 chkfast(is_file($base.'/C/Downloads/song1.mp3'),'Copy preserves source');
 $preview=$m->previewSelection('move','C',['Downloads/folderA','Downloads/song1.mp3'],'E','Music/Alben/Mflex','target');
 chkfast($preview[0]['decision']==='merge'&&$preview[1]['decision']==='skip','Preview supports folder merge and conflict skip');
 $skip=$files->transfer('move','C',['Downloads/song1.mp3'],'E','Music/Alben/Mflex','target',true);
 chkfast($skip[0]['decision']==='skip'&&is_file($base.'/C/Downloads/song1.mp3'),'Skipped move leaves source');
 $bad=$m->previewSelection('copy','C',['Downloads/folderA'],'C','Downloads/folderA/deep','newer');
 chkfast($bad[0]['decision']==='error','Self-copy blocked in quick preview');
 echo "TRANSFER SELECTION TESTS OK\n";
} finally {rrfast($base);}
