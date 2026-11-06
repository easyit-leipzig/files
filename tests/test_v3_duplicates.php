<?php
declare(strict_types=1);
require_once __DIR__.'/../assets/php/classes/Files.php';
require_once __DIR__.'/../assets/php/classes/DuplicateManager.php';
function check(bool $v,string $msg):void{if(!$v)throw new RuntimeException($msg);}
$base=sys_get_temp_dir().'/files-v3-'.bin2hex(random_bytes(5));mkdir($base);mkdir($base.'/left');mkdir($base.'/right');
try{
    file_put_contents($base.'/left/a.txt','equal');file_put_contents($base.'/right/a.txt','equal');
    file_put_contents($base.'/left/b.txt','different1');file_put_contents($base.'/right/b.txt','different-too-long');
    file_put_contents($base.'/left/same.txt','abc');file_put_contents($base.'/right/different-name.txt','abc');
    $roots=['left'=>$base.'/left','right'=>$base.'/right'];$scanner=new DuplicateManager(new Files($roots),$roots);
    $baseCfg=['left'=>['drive'=>'left','folder'=>'','recursive'=>false,'pattern'=>'*','invert'=>false],'right'=>['drive'=>'right','folder'=>'','recursive'=>false,'pattern'=>'*','invert'=>false]];
    $a=$scanner->scan($baseCfg+['criteria'=>['name']]);check($a['groupCount']===2,'Name comparison');
    $b=$scanner->scan($baseCfg+['criteria'=>['name','sha256']]);check($b['groupCount']===1,'Name and hash');
    $c=$scanner->scan($baseCfg+['criteria'=>['size','sha256']]);check($c['groupCount']>=2,'Content comparison independent of filename');
    $d=$scanner->scan($baseCfg+['criteria'=>['name','size']]);check($d['groupCount']===1,'Name-size combination');
    $e=$scanner->scan(array_merge($baseCfg,['criteria'=>['name'], 'right'=>array_merge($baseCfg['right'],['pattern'=>'*.txt','invert'=>true])]));check($e['groupCount']===0,'Inverted wildcard filter');
    echo "V3 comparison tests: PASS\n";
} finally {foreach(['left','right'] as $side){foreach(glob($base.'/'.$side.'/*') as $f)unlink($f);rmdir($base.'/'.$side);}rmdir($base);}
