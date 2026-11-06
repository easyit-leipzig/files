<?php
declare(strict_types=1);
/** Restricted ZIP operations and bounded directory statistics for Files V5. */
final class ArchiveManager {
    private array $roots=[];
    private const MAX_ENTRIES=10000;
    private const MAX_BYTES=1073741824; // 1 GiB uncompressed total
    public function __construct(array $roots) {
        foreach($roots as $id=>$path) { $real=realpath($path);if($real!==false&&is_dir($real))$this->roots[(string)$id]=$real; }
    }
    private function root(string $drive): string {
        if(!array_key_exists($drive,$this->roots))throw new InvalidArgumentException('Unbekanntes Laufwerk');
        return $this->roots[$drive];
    }
    private function relative(string $path,bool $allowEmpty=false): string {
        $path=str_replace('\\','/',$path);
        if($path===''&&$allowEmpty)return '';
        if($path===''||str_starts_with($path,'/')||preg_match('/^[a-z]:/i',$path)||str_contains($path,"\0")||strlen($path)>4096)throw new InvalidArgumentException('Ungültiger relativer Pfad');
        foreach(explode('/',$path) as $part)if($part===''||$part==='.'||$part==='..')throw new InvalidArgumentException('Unsicherer Pfad');
        return $path;
    }
    private function inside(string $root,string $file): bool {
        return $file===$root || str_starts_with($file,rtrim($root,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
    }
    private function existing(string $drive,string $path,bool $folder=false): string {
        $root=$this->root($drive);$path=$this->relative($path,$folder);
        if($path==='')return $root;
        $candidate=$root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path);
        if(is_link($candidate))throw new RuntimeException('Verknüpfungen sind nicht erlaubt');
        $real=realpath($candidate);
        if(!$real||!$this->inside($root,$real)||(!is_file($real)&&!is_dir($real)))throw new RuntimeException('Pfad nicht freigegeben');
        if($folder&&!is_dir($real))throw new RuntimeException('Kein Ordner');
        return $real;
    }
    private function basename(string $name): string {
        if($name===''||strlen($name)>240||preg_match('~[\\/\x00-\x1f:*?"<>|]~',$name)||in_array($name,['.','..'],true)||preg_match('/[. ]$/',$name))throw new InvalidArgumentException('Ungültiger Name');
        return $name;
    }
    private function zipReady(): void {if(!class_exists('ZipArchive'))throw new RuntimeException('PHP-Erweiterung zip ist nicht installiert');}
    private function walk(array $paths,string $drive): array {
        if(!$paths||!array_is_list($paths)||count($paths)>500)throw new InvalidArgumentException('1 bis 500 Dateien/Ordner auswählen');
        $root=$this->root($drive);$selected=[];
        foreach($paths as $p){if(!is_string($p))throw new InvalidArgumentException('Ungültiger Auswahlpfad');$real=$this->existing($drive,$p);if($real===$root)throw new InvalidArgumentException('Laufwerkswurzel nicht archivieren');$selected[$real]=true;}
        $items=[];$bytes=0;
        foreach(array_keys($selected) as $real){
            $candidates=[$real];
            if(is_dir($real)){
                $iter=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
                foreach($iter as $entry){$candidates[]=$entry->getPathname();if(count($candidates)>self::MAX_ENTRIES)throw new RuntimeException('Archiv enthält zu viele Einträge');}
            }
            foreach($candidates as $source){
                if(is_link($source))throw new RuntimeException('Symbolische Links werden nicht archiviert');
                $canonical=realpath($source);if(!$canonical||!$this->inside($root,$canonical))throw new RuntimeException('Datei außerhalb der Freigabe');
                if(!is_dir($canonical)&&!is_file($canonical))throw new RuntimeException('Spezialdatei nicht unterstützt');
                $relative=ltrim(str_replace('\\','/',substr($canonical,strlen($root))),'/');
                if(!isset($items[$relative])){$size=is_file($canonical)?filesize($canonical):0;$bytes+=$size;if($bytes>self::MAX_BYTES)throw new RuntimeException('Archivgröße überschreitet 1 GiB');$items[$relative]=[$canonical,is_dir($canonical)];}
                if(count($items)>self::MAX_ENTRIES)throw new RuntimeException('Archiv enthält zu viele Einträge');
            }
        }
        return $items;
    }
    public function create(string $drive,string $folder,array $paths,string $name): array {
        $this->zipReady();$name=$this->basename($name);if(!preg_match('/\.zip$/i',$name))$name.='.zip';
        $dir=$this->existing($drive,$folder,true);$dest=$dir.DIRECTORY_SEPARATOR.$name;
        if(file_exists($dest)||is_link($dest))throw new RuntimeException('Zielarchiv existiert bereits');
        $items=$this->walk($paths,$drive);$temp=tempnam($dir,'.files-zip-');if($temp===false)throw new RuntimeException('Temporäre ZIP-Datei nicht erstellbar');
        $zip=new ZipArchive();$opened=false;
        try{
            if($zip->open($temp,ZipArchive::OVERWRITE)!==true)throw new RuntimeException('ZIP konnte nicht geöffnet werden');$opened=true;
            foreach($items as $entry=>[$path,$isDir]){
                if($isDir){if(!$zip->addEmptyDir($entry))throw new RuntimeException('Ordner nicht archivierbar');}
                elseif(!$zip->addFile($path,$entry))throw new RuntimeException('Datei nicht archivierbar');
            }
            if(!$zip->close())throw new RuntimeException('Archiv konnte nicht abgeschlossen werden');$opened=false;
            if(file_exists($dest)||!@rename($temp,$dest))throw new RuntimeException('Archiv konnte nicht sicher gespeichert werden');
            return ['name'=>$name,'entries'=>count($items),'bytes'=>filesize($dest)];
        }finally{if($opened)$zip->close();if(file_exists($temp))@unlink($temp);}
    }
    private function inspectZip(ZipArchive $zip): array {
        $entries=[];$total=0;
        if($zip->numFiles>self::MAX_ENTRIES)throw new RuntimeException('ZIP enthält zu viele Einträge');
        for($i=0;$i<$zip->numFiles;$i++){
            $stat=$zip->statIndex($i);if(!$stat)throw new RuntimeException('ZIP-Eintrag unlesbar');$name=str_replace('\\','/',$stat['name']);
            $dir=str_ends_with($name,'/');$safe=rtrim($name,'/');$this->relative($safe);
            if(isset($entries[$safe]))throw new RuntimeException('Doppelter Archivpfad');
            if($zip->getExternalAttributesIndex($i,$os,$attrs)){
                // Unix symlink bits: 0120000. ZIP symlinks are never extracted.
                if($os===ZipArchive::OPSYS_UNIX && (($attrs>>16)&0170000)===0120000)throw new RuntimeException('ZIP enthält symbolischen Link');
            }
            $size=(int)$stat['size'];$total+=$size;
            if($total>self::MAX_BYTES)throw new RuntimeException('Archiv entpackt über 1 GiB');
            $entries[$safe]=['index'=>$i,'directory'=>$dir,'size'=>$size];
        }
        return $entries;
    }
    public function extract(string $drive,string $archive,string $targetFolder): array {
        $this->zipReady();$source=$this->existing($drive,$archive);if(!is_file($source)||strtolower(pathinfo($source,PATHINFO_EXTENSION))!=='zip')throw new InvalidArgumentException('Eine ZIP-Datei auswählen');
        $target=$this->existing($drive,$targetFolder,true);
        $zip=new ZipArchive();if($zip->open($source)!==true)throw new RuntimeException('ZIP konnte nicht geöffnet werden');
        try{
            $entries=$this->inspectZip($zip);
            // Never replace files, traverse symlinks or merge into existing directories.
            foreach($entries as $relative=>$entry){
                $parts=explode('/',$relative);$check=$target;
                foreach($parts as $part){$check.=DIRECTORY_SEPARATOR.$part;if(file_exists($check)||is_link($check))throw new RuntimeException('Zielname bereits vorhanden: '.$relative);}
            }
            // Stage under target so final rename remains on the same filesystem.
            $stage=$target.DIRECTORY_SEPARATOR.'.files-extract-'.bin2hex(random_bytes(8));
            if(!mkdir($stage,0700))throw new RuntimeException('Temporäres Verzeichnis fehlt');
            try{
                foreach($entries as $relative=>$entry){
                    $out=$stage.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative);
                    if($entry['directory']){if(!is_dir($out)&&!mkdir($out,0700,true))throw new RuntimeException('Ordner konnte nicht entpackt werden');continue;}
                    if(!is_dir(dirname($out))&&!mkdir(dirname($out),0700,true))throw new RuntimeException('Verzeichnis konnte nicht erzeugt werden');
                    $in=$zip->getStream($zip->getNameIndex($entry['index']));if($in===false)throw new RuntimeException('Archivdatei unlesbar');
                    $dest=fopen($out,'xb');if($dest===false){fclose($in);throw new RuntimeException('Datei konnte nicht angelegt werden');}
                    $written=0;
                    while(!feof($in)){$chunk=fread($in,65536);if($chunk===false)throw new RuntimeException('Lesefehler');if($chunk==='')break;$written+=strlen($chunk);if($written>$entry['size']||fwrite($dest,$chunk)!==strlen($chunk))throw new RuntimeException('Entpacken fehlgeschlagen');}
                    fclose($dest);fclose($in);if($written!==$entry['size'])throw new RuntimeException('Unvollständiger Eintrag');
                }
                // Move only top-level elements; target is checked again before each move.
                $tops=[];foreach(array_keys($entries) as $name)$tops[explode('/',$name)[0]]=true;
                $moved=[];
                try {
                    foreach(array_keys($tops) as $top){
                        $from=$stage.DIRECTORY_SEPARATOR.$top;$to=$target.DIRECTORY_SEPARATOR.$top;
                        if(file_exists($to)||is_link($to))throw new RuntimeException('Zielkonflikt: '.$top);
                        if(!rename($from,$to))throw new RuntimeException('Entpackte Dateien konnten nicht übernommen werden');$moved[]=$top;
                    }
                } catch(Throwable $e) {
                    // Restore already moved top-level entries to staging on commit errors.
                    foreach(array_reverse($moved) as $top) @rename($target.DIRECTORY_SEPARATOR.$top,$stage.DIRECTORY_SEPARATOR.$top);
                    throw $e;
                }
                return ['entries'=>count($entries),'folder'=>$targetFolder,'topLevel'=>array_keys($tops)];
            }finally{$this->removeTree($stage);}
        }finally{$zip->close();}
    }
    private function removeTree(string $root): void {
        if(!is_dir($root))return;
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($it as $entry){if($entry->isDir()&&!$entry->isLink())@rmdir($entry->getPathname());else @unlink($entry->getPathname());}@rmdir($root);
    }
    public function statistics(string $drive,string $folder,bool $recursive): array {
        $dir=$this->existing($drive,$folder,true);$files=0;$dirs=0;$bytes=0;$extensions=[];$limit=100000;$truncated=false;
        $it=$recursive?new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST):new FilesystemIterator($dir,FilesystemIterator::SKIP_DOTS);
        foreach($it as $entry){if($entry->isLink())continue;
            if($entry->isDir())$dirs++;elseif($entry->isFile()){$files++;$bytes+=$entry->getSize();$ext=strtolower($entry->getExtension())?:'(ohne Endung)';$extensions[$ext]=($extensions[$ext]??0)+1;}
            if($files+$dirs>=$limit){$truncated=true;break;}
        }
        arsort($extensions);
        return ['files'=>$files,'directories'=>$dirs,'bytes'=>$bytes,'extensions'=>array_slice($extensions,0,12,true),'recursive'=>$recursive,'truncated'=>$truncated];
    }
}
