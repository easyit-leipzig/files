<?php
declare(strict_types=1);
require_once __DIR__.'/../assets/php/classes/Files.php';
$base=sys_get_temp_dir().'/files_picker_'.bin2hex(random_bytes(5));
mkdir($base);mkdir($base.'/empty');mkdir($base.'/child');file_put_contents($base.'/example.txt','x');
try {
    $app=new Files(['test'=>$base]);
    $root=$app->folders('test','');
    assert(count($root['directories'])===2);
    assert(count($root['files'])===1);
    assert($root['files'][0]['name']==='example.txt');
    assert($root['isEmpty']===false);
    $empty=$app->folders('test','empty');
    assert($empty['isEmpty']===true);
    assert(count($empty['files'])===0);
    echo "Folder picker tests passed\n";
} finally {unlink($base.'/example.txt');rmdir($base.'/empty');rmdir($base.'/child');rmdir($base);}
