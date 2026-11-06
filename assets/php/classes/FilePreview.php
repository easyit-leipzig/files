<?php
declare(strict_types=1);
/** Read-only, size-limited preview constrained to explicitly authorized roots. */
final class FilePreview {
    public function __construct(private array $roots) {}
    public function read(string $drive,string $relative): array {
        if(!isset($this->roots[$drive])) throw new InvalidArgumentException('Laufwerk nicht freigegeben');
        $root=realpath($this->roots[$drive]);
        if($root===false) throw new RuntimeException('Laufwerk nicht verfügbar');
        $relative=str_replace('\\','/',$relative);
        if($relative==='' || str_starts_with($relative,'/') || preg_match('/^[A-Za-z]:/',$relative))throw new InvalidArgumentException('Ungültiger Dateipfad');
        foreach(explode('/',$relative) as $segment){if($segment===''||$segment==='.'||$segment==='..')throw new InvalidArgumentException('Ungültiger Dateipfad');}
        $candidate=$root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative);
        $real=realpath($candidate);
        if($real===false || !is_file($real) || is_link($candidate))throw new InvalidArgumentException('Datei nicht verfügbar');
        $base=rtrim($root,'/\\');
        $prefix=$base.DIRECTORY_SEPARATOR;
        $match=PHP_OS_FAMILY==='Windows' ? strncasecmp($real,$prefix,strlen($prefix))===0 : strncmp($real,$prefix,strlen($prefix))===0;
        if(!$match)throw new InvalidArgumentException('Datei außerhalb des freigegebenen Verzeichnisses');
        if(!is_readable($real))throw new InvalidArgumentException('Datei ist nicht lesbar');
        $size=filesize($real);
        if($size===false)throw new RuntimeException('Dateigröße unbekannt');
        $ext=strtolower(pathinfo($real,PATHINFO_EXTENSION));
        $textExtensions=['txt','md','csv','json','xml','log','ini','yaml','yml','html','htm','css','js','php','sql','py','java','c','cpp','h','sh','bat','ps1'];
        $imageMimes=['image/png','image/jpeg','image/gif','image/webp'];
        if(in_array($ext,$textExtensions,true)){
            $bytes=@file_get_contents($real,false,null,0,131073);
            if($bytes===false)throw new RuntimeException('Datei kann nicht gelesen werden');
            if(str_contains($bytes,"\0"))throw new InvalidArgumentException('Binärdatei wird nicht als Text angezeigt');
            $text=preg_match('//u',$bytes)===1?$bytes:'[Textvorschau enthält ungültige UTF-8-Zeichen]';
            return ['kind'=>'text','name'=>basename($real),'size'=>$size,'truncated'=>$size>131072,'content'=>substr($text,0,131072)];
        }
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($real);
        if(in_array($mime,$imageMimes,true) && $size<=2097152){
            $bytes=@file_get_contents($real);
            if($bytes===false)throw new RuntimeException('Bild kann nicht gelesen werden');
            return ['kind'=>'image','name'=>basename($real),'size'=>$size,'mime'=>$mime,'base64'=>base64_encode($bytes)];
        }
        return ['kind'=>'metadata','name'=>basename($real),'size'=>$size,'mime'=>$mime?:'application/octet-stream','message'=>'Keine Inhaltsvorschau für diesen Dateityp oder Bild größer als 2 MiB'];
    }
}
