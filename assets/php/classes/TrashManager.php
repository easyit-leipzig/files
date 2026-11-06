<?php
declare(strict_types=1);
/** Files V2: private application trash and append-only audit log. No permanent delete endpoint. */
final class TrashManager {
    private string $base;
    private string $items;
    private string $audit;
    public function __construct(private array $drives, ?string $privatePath=null) {
        $configured=$privatePath ?? getenv('FILES_PRIVATE_STORAGE');
        // The actual HTTP document root is authoritative. dirname(__DIR__,4)
        // was one level TOO HIGH (the parent of the Files application), causing
        // valid sibling private stores to be rejected with HTTP 500.
        $documentRoot=(string)($_SERVER['DOCUMENT_ROOT']??'');
        $webRoot=realpath($documentRoot!=='' ? $documentRoot : dirname(__DIR__,3));
        // Use a persistent writable directory outside the web root. In typical
        // Windows XAMPP setups the PHP worker cannot write beside htdocs.
        $candidates=($configured!==false && $configured!=='' && $configured!==null)
            ? [(string)$configured]
            : [dirname(__DIR__,5).'/.files-private-v2',
               rtrim(sys_get_temp_dir(),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'easyit-files-private-v2'];
        $reasons=[];$chosen=null;
        $createdForApp=false;
        foreach ($candidates as $candidate) {
            $didExist=is_dir($candidate);
            if (!$didExist && !@mkdir($candidate,0700,true) && !is_dir($candidate)) {
                $reasons[]=$candidate.' (Verzeichnis nicht erstellbar)';continue;
            }
            $real=realpath($candidate);
            if (!$real) {$reasons[]=$candidate.' (ungültiger Pfad)';continue;}
            if ($webRoot && ($real===$webRoot || $this->inside($webRoot,$real) || $this->inside($real,$webRoot))) {
                $reasons[]=$real.' (Webverzeichnis oder dessen übergeordneter Ordner)';continue;
            }
            if (!is_writable($real)) {$reasons[]=$real.' (keine Schreibrechte)';continue;}
            $probe=$real.DIRECTORY_SEPARATOR.'.write-test-'.bin2hex(random_bytes(6));
            if (@file_put_contents($probe,'ok',LOCK_EX)===false) {
                $reasons[]=$real.' (Schreibtest fehlgeschlagen)';continue;
            }
            @unlink($probe);$chosen=$real;$createdForApp=!$didExist;break;
        }
        if ($chosen===null) throw new RuntimeException('Privater Papierkorb nicht beschreibbar: '.implode('; ',$reasons).'. FILES_PRIVATE_STORAGE auf einen beschreibbaren Ordner außerhalb des Webverzeichnisses setzen.');
        $this->base=$chosen;
        if($createdForApp)@chmod($this->base,0700);
        // Restrict accidental HTTP access should the location be changed later.
        @file_put_contents($this->base.'/.htaccess',"Require all denied\nDeny from all\n");
        @file_put_contents($this->base.'/web.config','<?xml version="1.0"?><configuration><system.webServer><security><authorization><remove users="*" roles="" verbs=""/><add accessType="Deny" users="*"/></authorization></security></system.webServer></configuration>');
        $this->items=$this->base.'/items';
        if (!is_dir($this->items) && !@mkdir($this->items,0700,true)) throw new RuntimeException('Papierkorb-Speicher nicht erstellbar: '.$this->items);
        if(!is_writable($this->items)) throw new RuntimeException('Keine Schreibrechte im Papierkorb: '.$this->items);
        $this->audit=$this->base.'/audit.jsonl';
    }
    private function inside(string $root,string $path): bool {
        $r=rtrim(str_replace('\\','/',$root),'/');$p=str_replace('\\','/',$path);
        return PHP_OS_FAMILY==='Windows' ? str_starts_with(strtolower($p),strtolower($r.'/')) : str_starts_with($p,$r.'/');
    }
    private function relative(string $relative,bool $allowEmpty=false): string {
        $rel=str_replace('\\','/',$relative);
        if($allowEmpty && $rel==='')return '';
        if($rel===''||str_starts_with($rel,'/')||str_contains($rel,"\0")||preg_match('/^[A-Za-z]:/',$rel)||strlen($rel)>4096||array_intersect(explode('/',$rel),['.','..','']))
            throw new InvalidArgumentException('Ungültiger Pfad');
        return $rel;
    }
    /** Reject symlinks in EVERY component, not merely the realpath destination. */
    private function noSymlinkComponents(string $root,string $relative): void {
        // Avoid doubled separators on Windows volume roots (C:\).
        $cursor=rtrim($root,DIRECTORY_SEPARATOR);
        foreach(explode('/',$relative) as $part){
            if($part==='')continue;
            $cursor.=DIRECTORY_SEPARATOR.$part;
            if(is_link($cursor))throw new InvalidArgumentException('Symbolische Verknüpfung in der Auswahl ist nicht zulässig');
        }
    }
    private function resolve(string $drive,string $relative): string {
        if (!isset($this->drives[$drive])) throw new InvalidArgumentException('Unbekanntes Laufwerk');
        $root=realpath((string)$this->drives[$drive]);
        if (!$root) throw new RuntimeException('Laufwerk nicht erreichbar');
        $rel=$this->relative($relative);
        $this->noSymlinkComponents($root,$rel);
        $path=realpath($root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$rel));
        if (!$path||!$this->inside($root,$path)||$path===$this->base||$this->inside($this->base,$path)||$this->inside($path,$this->base)||(!is_file($path)&&!is_dir($path))) throw new InvalidArgumentException('Datei/Ordner nicht zulässig');
        return $path;
    }
    /** Move files and directory trees even if the private trash is on another volume.
     * Copy to an unpublished .part name, verify each file, then remove originals.
     * A failed removal keeps the complete copy AND its metadata for recovery.
     */
    private function relocate(string $source,string $target): void {
        if(@rename($source,$target))return;
        if(file_exists($target)||is_link($target))throw new RuntimeException('Ziel im Papierkorb existiert bereits');
        $staging=$target.'.part';
        if(file_exists($staging)||is_link($staging))throw new RuntimeException('Temporärer Papierkorbpfad belegt');
        try {
            $this->verifiedCopy($source,$staging);
            if(!@rename($staging,$target))throw new RuntimeException('Kopierte Daten konnten nicht in den Papierkorb übernommen werden');
        } catch(Throwable $e) {
            $this->cleanupStaging($staging);
            throw $e;
        }
        try { $this->removeOriginal($source); }
        catch(Throwable $e) {
            throw new RuntimeException('Vollständige Kopie im Papierkorb vorhanden, Quelle konnte nicht vollständig entfernt werden: '.$e->getMessage(),0,$e);
        }
    }
    private function verifiedCopy(string $source,string $target): void {
        if(is_link($source))throw new RuntimeException('Verknüpfungen können nicht sicher verschoben werden');
        if(is_file($source)){
            if(!@copy($source,$target))throw new RuntimeException('Kopieren in privaten Papierkorb fehlgeschlagen');
            if(filesize($source)!==filesize($target)||hash_file('sha256',$source)!==hash_file('sha256',$target))
                throw new RuntimeException('Prüfsummenfehler: Quelle bleibt erhalten');
            @touch($target,(int)filemtime($source));
            return;
        }
        if(!is_dir($source)||!@mkdir($target,0700))throw new RuntimeException('Ordnerkopie in den Papierkorb fehlgeschlagen');
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
        $count=0;
        foreach($iterator as $entry){
            if(++$count>200000)throw new RuntimeException('Ordner enthält zu viele Einträge');
            if($entry->isLink())throw new RuntimeException('Ordner enthält symbolische Verknüpfung; Quelle bleibt erhalten');
            $relative=substr($entry->getPathname(),strlen($source)+1);
            $dest=$target.DIRECTORY_SEPARATOR.$relative;
            if($entry->isDir()){
                if(!@mkdir($dest,0700))throw new RuntimeException('Unterordner konnte nicht gesichert werden');
            } elseif($entry->isFile()){
                if(!@copy($entry->getPathname(),$dest)||filesize($entry->getPathname())!==filesize($dest)||hash_file('sha256',$entry->getPathname())!==hash_file('sha256',$dest))
                    throw new RuntimeException('Sicherung unvollständig oder Prüfsummenfehler: '.$relative);
                @touch($dest,$entry->getMTime());
            } else throw new RuntimeException('Nicht unterstützter Dateityp in Ordner: '.$relative);
        }
    }
    private function removeOriginal(string $source): void {
        if(is_link($source))throw new RuntimeException('Verknüpfung statt Quelldatei');
        if(is_file($source)){
            if(!@unlink($source))throw new RuntimeException('Quelldatei konnte nicht entfernt werden');
            return;
        }
        if(!is_dir($source))throw new RuntimeException('Quellordner fehlt');
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($iterator as $entry){
            if($entry->isLink())throw new RuntimeException('Verknüpfung im Quellordner: '.$entry->getPathname());
            $ok=$entry->isDir()?@rmdir($entry->getPathname()):@unlink($entry->getPathname());
            if(!$ok)throw new RuntimeException('Quelle konnte nicht entfernt werden: '.$entry->getPathname());
        }
        if(!@rmdir($source))throw new RuntimeException('Quellordner konnte nicht entfernt werden');
    }
    private function cleanupStaging(string $path): void {
        if(!file_exists($path)&&!is_link($path))return;
        if(is_dir($path)&&!is_link($path)){
            $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
            foreach($it as $entry){if($entry->isDir()&&!$entry->isLink())@rmdir($entry->getPathname());else @unlink($entry->getPathname());}
            @rmdir($path);
        }else @unlink($path);
    }
    private function metadata(string $id): string {return $this->items.'/'.$id.'.json';}
    private function contents(string $id): string {return $this->items.'/'.$id.'.data';}
    private function validId(string $id): void {if (!preg_match('/^[a-f0-9]{32}$/D',$id))throw new InvalidArgumentException('Ungültige Papierkorb-ID');}
    public function log(string $event,array $context=[]): void {
        $line=json_encode(['at'=>gmdate('c'),'event'=>$event,'context'=>$context],JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_UNICODE)."\n";
        if (@file_put_contents($this->audit,$line,FILE_APPEND|LOCK_EX)===false) throw new RuntimeException('Aktionsprotokoll konnte nicht geschrieben werden');
    }
    public function put(string $drive,string $folder,array $paths,bool $confirmed): array {
        if (!$confirmed) throw new InvalidArgumentException('Bestätigung fehlt');
        if (!$paths||count($paths)>500||!array_is_list($paths)) throw new InvalidArgumentException('1 bis 500 Einträge erforderlich');
        if(!isset($this->drives[$drive]))throw new InvalidArgumentException('Unbekanntes Laufwerk');
        $root=realpath((string)$this->drives[$drive]);
        $folder=$this->relative($folder,true);
        if($folder!=='')$this->noSymlinkComponents($root,$folder);
        $panel=$folder===''?$root:realpath($root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$folder));
        if(!$panel||!is_dir($panel)||($panel!==$root&&!$this->inside($root,$panel)))throw new InvalidArgumentException('Ungültiger Panelordner');
        $resolved=[];
        foreach($paths as $rel){
            if(!is_string($rel))throw new InvalidArgumentException('Ungültige Auswahl');
            $abs=$this->resolve($drive,$rel);
            if($abs===$panel||!$this->inside($panel,$abs))throw new InvalidArgumentException('Auswahl liegt nicht unter dem Panelordner');
            $resolved[$abs]=$rel;
        }
        uksort($resolved,fn($a,$b)=>strlen($a)<=>strlen($b));
        $unique=[];
        foreach($resolved as $abs=>$rel){$covered=false;foreach($unique as $ancestor=>$x){if(is_dir($ancestor)&&$this->inside($ancestor,$abs)){$covered=true;break;}}if(!$covered)$unique[$abs]=$rel;}
        $results=[];
        foreach($unique as $abs=>$rel){
            $id=bin2hex(random_bytes(16));$destination=$this->contents($id);
            $row=['id'=>$id,'drive'=>$drive,'path'=>$rel,'isDirectory'=>is_dir($abs),'deletedAt'=>gmdate('c')];
            try {
                // Writing metadata first permits recovery after a failed audit write.
                $meta=$this->metadata($id);$temp=$meta.'.tmp';
                if(file_put_contents($temp,json_encode($row,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE),LOCK_EX)===false||!rename($temp,$meta))throw new RuntimeException('Metadaten konnten nicht gespeichert werden');
                try {$this->relocate($abs,$destination);}
                catch(Throwable $moveError){
                    // Preserve metadata whenever the verified recovery copy exists.
                    if(!file_exists($destination))@unlink($meta);
                    throw $moveError;
                }
                $result=['path'=>$rel,'ok'=>true,'id'=>$id];
                // A logging failure must not disguise an already completed move.
                try {$this->log('trash.put',['id'=>$id,'drive'=>$drive,'path'=>$rel]);}
                catch(Throwable $auditError){$result['warning']='Verschoben, aber Protokollierung fehlgeschlagen: '.$auditError->getMessage();}
                $results[]=$result;
            }catch(Throwable $e){$results[]=['path'=>$rel,'ok'=>false,'error'=>$e->getMessage()];}
        }
        return $results;
    }
    public function entries(): array {
        $rows=[];
        foreach(glob($this->items.'/*.json')?:[] as $file){
            $row=json_decode((string)@file_get_contents($file),true);
            if(is_array($row)&&isset($row['id'])&&file_exists($this->contents((string)$row['id'])))$rows[]=$row;
        }
        usort($rows,fn($a,$b)=>strcmp($b['deletedAt'],$a['deletedAt']));return array_slice($rows,0,1000);
    }
    public function restore(string $id): array {
        $this->validId($id);
        $meta=$this->metadata($id);$data=$this->contents($id);
        $entry=json_decode((string)@file_get_contents($meta),true);
        if(!is_array($entry)||!file_exists($data))throw new InvalidArgumentException('Eintrag nicht mehr vorhanden');
        $drive=$entry['drive']??'';$rel=$entry['path']??'';
        if(!is_string($drive)||!is_string($rel)||!isset($this->drives[$drive]))throw new InvalidArgumentException('Laufwerk nicht mehr freigegeben');
        $root=realpath((string)$this->drives[$drive]);
        if(!$root)throw new RuntimeException('Laufwerk nicht erreichbar');
        $normalized=str_replace('\\','/',$rel);
        if($normalized===''||str_starts_with($normalized,'/')||preg_match('/^[A-Za-z]:/',$normalized)||array_intersect(explode('/',$normalized),['.','..','']))throw new InvalidArgumentException('Ungültiger Wiederherstellungspfad');
        $target=$root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$normalized);
        $parent=realpath(dirname($target));
        if(!$parent||($parent!==$root&&!$this->inside($root,$parent)))throw new RuntimeException('Übergeordneter Zielordner existiert nicht');
        if(file_exists($target)||is_link($target))throw new RuntimeException('Ziel existiert bereits. Keine Überschreibung.');
        $this->relocate($data,$target);
        @unlink($meta);
        $result=['id'=>$id,'drive'=>$drive,'path'=>$rel,'ok'=>true];
        try {$this->log('trash.restore',['id'=>$id,'drive'=>$drive,'path'=>$rel]);}
        catch(Throwable $auditError){$result['warning']='Wiederhergestellt, aber Protokollierung fehlgeschlagen: '.$auditError->getMessage();}
        return $result;
    }
    public function recentLog(): array {
        if(!is_file($this->audit))return [];
        $last=[];$file=new SplFileObject($this->audit,'r');
        while(!$file->eof()) {
            $row=json_decode(trim($file->fgets()),true);
            if(is_array($row)) {$last[]=$row;if(count($last)>100)array_shift($last);}
        }
        return array_reverse($last);
    }
}
