<?php
declare(strict_types=1);
require_once __DIR__.'/../assets/php/classes/FilePreview.php';
function test(bool $result,string $name): void {if(!$result)throw new RuntimeException($name);}
$base=sys_get_temp_dir().'/files-v4-'.bin2hex(random_bytes(6));mkdir($base);mkdir($base.'/safe');mkdir($base.'/other');
try {
    file_put_contents($base.'/safe/notes.txt','Ein Test');
    file_put_contents($base.'/safe/long.md',str_repeat('x',140000));
    file_put_contents($base.'/other/private.txt','secret');
    file_put_contents($base.'/safe/test.bin',"\x00\x01\x02");
    $viewer=new FilePreview(['safe'=>$base.'/safe']);
    test($viewer->read('safe','notes.txt')['content']==='Ein Test','Textvorschau');
    $long=$viewer->read('safe','long.md');test($long['truncated']===true&&strlen($long['content'])===131072,'Limit 128 KiB');
    test($viewer->read('safe','test.bin')['kind']==='metadata','Binärdatei nur Metadaten');
    foreach(['../other/private.txt','/etc/passwd','C:/private.txt','', 'sub/../notes.txt'] as $path){
        try{$viewer->read('safe',$path);throw new RuntimeException('Unsicherer Pfad akzeptiert: '.$path);}catch(InvalidArgumentException $expected){}
    }
    try{$viewer->read('other','private.txt');throw new RuntimeException('Nicht erlaubtes Laufwerk');}catch(InvalidArgumentException $expected){}
    if(function_exists('symlink') && @symlink($base.'/other/private.txt',$base.'/safe/link.txt')){
        try{$viewer->read('safe','link.txt');throw new RuntimeException('Symlink-Ziel akzeptiert');}catch(InvalidArgumentException $expected){}
    }
    echo "V4 preview/path validation: PASS\n";
} finally {
    foreach(['safe','other'] as $dir){foreach(glob($base.'/'.$dir.'/*') as $f)unlink($f);rmdir($base.'/'.$dir);}rmdir($base);
}
