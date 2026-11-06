<?php
declare(strict_types=1);
/** Folder-capable actions for Files V1; no symlink traversal, protected roots, per-file conflicts. */
final class FolderOperations {
    private array $roots=[];
    private Files $files;
    public function __construct(array $roots, Files $files) {
        foreach ($roots as $id=>$root) {
            $resolved=realpath($root);
            if ($resolved!==false && is_dir($resolved)) $this->roots[$id]=rtrim($resolved,DIRECTORY_SEPARATOR) ?: DIRECTORY_SEPARATOR;
        }
        $this->files=$files;
    }
    private function root(string $drive): string {
        if (!isset($this->roots[$drive])) throw new InvalidArgumentException('Unbekanntes Laufwerk');
        return $this->roots[$drive];
    }
    private function inside(string $root,string $p): bool {
        return $p===$root || str_starts_with($p,rtrim($root,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
    }
    private function safeRelative(string $path): string {
        $p=str_replace('\\','/',$path);
        if ($p==='' || str_starts_with($p,'/') || preg_match('/^[a-zA-Z]:/',$p) || str_contains($p,"\0") || strlen($p)>4096) throw new InvalidArgumentException('Ungültiger Pfad');
        foreach (explode('/',$p) as $part) if ($part==='' || $part==='.' || $part==='..') throw new InvalidArgumentException('Ungültige Pfadkomponente');
        return $p;
    }
    private function basenameValid(string $name): bool {
        return $name!=='' && $name!=='.' && $name!=='..' && strlen($name)<=255 && !preg_match('~[\\/\x00-\x1f:*?"<>|]~',$name) && !preg_match('/[. ]$/',$name);
    }
    private function existing(string $drive,string $relative): string {
        $root=$this->root($drive);
        $relative=$this->safeRelative($relative);
        $path=$root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative);
        if (is_link($path)) throw new RuntimeException('Verknüpfungen sind nicht zulässig');
        $real=realpath($path);
        if ($real===false || !$this->inside($root,$real) || $real===$root || (!is_dir($real) && !is_file($real))) throw new RuntimeException('Eintrag nicht freigegeben: '.$relative);
        return $real;
    }
    private function directory(string $drive,string $relative): string {
        $root=$this->root($drive);
        if ($relative==='') return $root;
        $path=$this->existing($drive,$relative);
        if (!is_dir($path)) throw new InvalidArgumentException('Kein Ordner');
        return $path;
    }
    private function relative(string $root,string $p): string {
        return trim(str_replace('\\','/',substr($p,strlen($root))),'/');
    }
    public function create(string $drive,string $folder,string $name): array {
        if (!$this->basenameValid($name)) throw new InvalidArgumentException('Ungültiger Ordnername');
        $parent=$this->directory($drive,$folder);
        $target=$parent.DIRECTORY_SEPARATOR.$name;
        if (file_exists($target) || is_link($target)) throw new RuntimeException('Dieser Name ist bereits vergeben');
        if (!@mkdir($target,0775)) throw new RuntimeException('Ordner konnte nicht erstellt werden');
        return ['path'=>$this->relative($this->root($drive),$target),'ok'=>true];
    }
    public function rename(string $drive,string $relative,string $newName): array {
        if (!$this->basenameValid($newName)) throw new InvalidArgumentException('Ungültiger neuer Name');
        $source=$this->existing($drive,$relative);
        $dest=dirname($source).DIRECTORY_SEPARATOR.$newName;
        if ($source===$dest) throw new InvalidArgumentException('Name unverändert');
        if (file_exists($dest) || is_link($dest)) throw new RuntimeException('Der Zielname existiert bereits');
        if (!@rename($source,$dest)) throw new RuntimeException('Umbenennung fehlgeschlagen');
        return ['path'=>$relative,'newName'=>$newName,'ok'=>true];
    }
    /** Fast, bounded preview: inspect selected top-level entries only.
     * A full recursive scan of large directories would block the dialog for minutes.
     * During execution the selected conflict rule is applied to every nested file.
     */
    public function previewSelection(string $operation,string $drive,array $paths,string $targetDrive,string $targetFolder,string $policy): array {
        if (!in_array($operation,['copy','move'],true) ||
            !in_array($policy,['source','target','newer','older','larger','smaller','rename','identical'],true)) {
            throw new InvalidArgumentException('Ungültige Übertragung oder Konfliktregel');
        }
        if (!$paths || count($paths)>500 || !array_is_list($paths)) throw new InvalidArgumentException('1 bis 500 Einträge auswählen');
        $target=$this->directory($targetDrive,$targetFolder);
        $destRoot=$this->root($targetDrive);
        $seen=[];$rows=[];
        foreach ($paths as $path) {
            $display=is_string($path)?$path:'';
            try {
                if (!is_string($path)) throw new InvalidArgumentException('Ungültiger Dateipfad');
                $src=$this->existing($drive,$path);
                $name=basename($src);
                $destination=$target.DIRECTORY_SEPARATOR.$name;
                $key=PHP_OS_FAMILY==='Windows'?strtolower($destination):$destination;
                if (isset($seen[$key])) throw new RuntimeException('Mehrere markierte Einträge haben denselben Zielnamen: '.$name);
                $seen[$key]=true;
                if (is_dir($src)) {
                    if ($this->inside($src,$target) || $src===$destination ||
                        (file_exists($destination) && realpath($destination)===realpath($src))) {
                        throw new RuntimeException('Ordner darf nicht in sich selbst kopiert/verschoben werden');
                    }
                    if (is_link($destination) || (file_exists($destination) && !is_dir($destination))) {
                        throw new RuntimeException('Zielordnername durch Datei/Verknüpfung blockiert: '.$name);
                    }
                    $rows[]=['path'=>$path,'targetPath'=>$this->relative($destRoot,$destination),
                        'isDirectory'=>true,'decision'=>is_dir($destination)?'merge':'copy',
                        'newName'=>'Inhalt und Konflikte werden beim Start rekursiv geprüft',
                        'sourceSize'=>null,'targetSize'=>null,'ok'=>true];
                } else {
                    $files=$this->files->transfer($operation,$drive,[$path],$targetDrive,$targetFolder,$policy,false);
                    $one=$files[0]??[];
                    $one['isDirectory']=false;
                    $one['targetPath']=$this->relative($destRoot,$destination);
                    $rows[]=$one;
                }
            } catch (Throwable $e) {
                $rows[]=['path'=>$display,'isDirectory'=>false,'decision'=>'error',
                    'error'=>$e->getMessage(),'targetSize'=>null,'ok'=>false];
            }
        }
        return $rows;
    }

    /** Build an immutable transfer plan. Folders are merged; all file conflicts use the chosen policy. */
    private function plan(string $operation,string $drive,array $paths,string $targetDrive,string $targetFolder,string $policy): array {
        if (!in_array($operation,['copy','move'],true)) throw new InvalidArgumentException('Ungültige Übertragung');
        if (!in_array($policy,['source','target','newer','older','larger','smaller','rename','identical'],true)) throw new InvalidArgumentException('Ungültige Konfliktregel');
        if (!$paths || count($paths)>500 || !array_is_list($paths)) throw new InvalidArgumentException('1 bis 500 Einträge markieren');
        $root=$this->root($drive);$destRoot=$this->root($targetDrive);
        $destination=$this->directory($targetDrive,$targetFolder);
        $items=[];$dirs=[];$selected=[];$allSources=[];
        foreach($paths as $relative) {
            if (!is_string($relative)) throw new InvalidArgumentException('Ungültiger Auswahlpfad');
            $src=$this->existing($drive,$relative);
            $selected[$src]=$relative;
        }
        // Parent selection subsumes nested selected entries.
        uksort($selected,fn($a,$b)=>strlen($a)<=>strlen($b));
        foreach($selected as $src=>$relative) {
            $covered=false;
            foreach($allSources as $parent) if(is_dir($parent) && $this->inside($parent,$src)) {$covered=true;break;}
            if ($covered) continue;
            $allSources[]=$src;
            $topDest=$destination.DIRECTORY_SEPARATOR.basename($src);
            if (is_dir($src)) {
                if ($this->inside($src,$destination) || $src===$topDest) throw new RuntimeException('Ein Ordner darf nicht in sich selbst oder seine Unterordner kopiert/verschoben werden');
                if (is_link($topDest) || (file_exists($topDest) && !is_dir($topDest))) throw new RuntimeException('Zielordnername durch Datei/Link belegt: '.basename($src));
                $dirs[$topDest]=true;
                $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
                foreach($it as $entry) {
                    if ($entry->isLink()) throw new RuntimeException('Symbolische Verknüpfung in Ordner: '.$entry->getPathname());
                    $sub=substr($entry->getPathname(),strlen($src)+1);
                    $dest=$topDest.DIRECTORY_SEPARATOR.$sub;
                    if ($entry->isDir()) {
                        if (file_exists($dest) && !is_dir($dest) || is_link($dest)) throw new RuntimeException('Ordnerkonflikt: '.$sub);
                        $dirs[$dest]=true;
                    } elseif ($entry->isFile()) $items[]=[$entry->getPathname(),$dest,$relative];
                    else throw new RuntimeException('Nicht unterstützter Dateityp');
                    if (count($items)+count($dirs)>200000) throw new RuntimeException('Zu viele Einträge in markierten Ordnern');
                }
            } else $items[]=[$src,$topDest,$relative];
        }
        $rows=[];$seen=[];
        foreach($items as [$src,$dest,$origin]) {
            if (!$this->inside($root,$src) || !$this->inside($destRoot,$dest)) throw new RuntimeException('Pfad außerhalb freigegebener Laufwerke');
            $key=PHP_OS_FAMILY==='Windows'?strtolower($dest):$dest;
            if (isset($seen[$key])) throw new RuntimeException('Mehrere Quelldateien würden dasselbe Ziel belegen');
            $seen[$key]=true;
            if ($src===$dest || (file_exists($dest) && realpath($src)===realpath($dest))) throw new RuntimeException('Quelle und Ziel identisch');
            if (is_link($dest) || (file_exists($dest) && !is_file($dest))) throw new RuntimeException('Ziel ist keine reguläre Datei');
            // Protect existing parent directories; never follow symlink ancestors.
            $walk=dirname($dest);
            while ($walk!==$destRoot && $this->inside($destRoot,$walk)) {
                if (is_link($walk) || (file_exists($walk) && !is_dir($walk))) throw new RuntimeException('Ungültiger Zielordner');
                $walk=dirname($walk);
            }
            $size=filesize($src);$modified=filemtime($src);
            $exists=is_file($dest);$targetSize=$exists?filesize($dest):null;$targetModified=$exists?filemtime($dest):null;
            $decision='copy';
            if ($exists) $decision=match($policy) {
                'source'=>'replace','target'=>'skip',
                'newer'=>$modified>$targetModified?'replace':'skip',
                'older'=>$modified<$targetModified?'replace':'skip',
                'larger'=>$size>$targetSize?'replace':'skip',
                'smaller'=>$size<$targetSize?'replace':'skip',
                'rename'=>'rename',
                'identical'=>hash_file('sha256',$src)===hash_file('sha256',$dest)?'skip':'replace'
            };
            $rows[]=['path'=>$this->relative($root,$src),'targetPath'=>$this->relative($destRoot,$dest),'decision'=>$decision,'sourceSize'=>$size,'sourceModified'=>$modified,'targetSize'=>$targetSize,'targetModified'=>$targetModified,'ok'=>true,'_src'=>$src,'_dest'=>$dest];
        }
        return [$rows,array_keys($dirs),$allSources,$destRoot];
    }
    public function transfer(string $operation,string $drive,array $paths,string $targetDrive,string $targetFolder,string $policy,bool $apply=false): array {
        [$rows,$dirs,$sources,$destRoot]=$this->plan($operation,$drive,$paths,$targetDrive,$targetFolder,$policy);
        if (!$apply) return array_map(fn($r)=>array_diff_key($r,array_flip(['_src','_dest'])),$rows);
        // Never create directories before the preview has succeeded.
        usort($dirs,fn($a,$b)=>strlen($a)<=>strlen($b));
        foreach ($dirs as $folder) {
            if (is_link($folder) || (file_exists($folder) && !is_dir($folder))) throw new RuntimeException('Zielordner blockiert');
            if (!is_dir($folder) && !@mkdir($folder,0775,true)) throw new RuntimeException('Zielordner konnte nicht erstellt werden: '.$folder);
        }
        $out=[];
        foreach($rows as $row) {
            $src=$row['_src'];$target=$row['_dest'];
            unset($row['_src'],$row['_dest']);
            try {
                if ($row['decision']!=='skip') {
                    $parent=dirname($target);
                    if (!is_dir($parent) && !@mkdir($parent,0775,true)) throw new RuntimeException('Zielverzeichnis konnte nicht angelegt werden');
                    $actual=$this->files->transfer($operation,$drive,[$row['path']],$targetDrive,$this->relative($destRoot,$parent),$policy,true);
                    $one=$actual[0]??[];
                    if (empty($one['ok'])) throw new RuntimeException($one['error']??'Übertragung fehlgeschlagen');
                    $row['decision']=$one['decision'];
                    if(isset($one['newName'])) $row['newName']=$one['newName'];
                }
            } catch (Throwable $e) {$row['ok']=false;$row['decision']='error';$row['error']=$e->getMessage();}
            $out[]=$row;
        }
        // Remove only EMPTY original directories on move; skipped/failed files survive.
        if ($operation==='move') foreach ($sources as $source) if(is_dir($source)) {
            $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $entry) if ($entry->isDir() && !$entry->isLink()) @rmdir($entry->getPathname());
            @rmdir($source);
        }
        return $out;
    }
}
