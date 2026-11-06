<?php
declare(strict_types=1);
/** Read-only inventory with sandboxed directory navigation and SHA-256 duplicate detection. */
final class Files {
    private array $roots = [];
    public const FIELDS = [
        'name'=>'Name', 'relativePath'=>'Relativer Pfad', 'extension'=>'Endung',
        'size'=>'Größe (Bytes)', 'modified'=>'Geändert', 'created'=>'Erstellt',
        'accessed'=>'Zugriff', 'type'=>'Typ', 'permissions'=>'Rechte',
        'readable'=>'Lesbar', 'writable'=>'Schreibbar', 'sha256'=>'SHA-256', 'id'=>'ID'
    ];
    public function __construct(array $roots) {
        foreach ($roots as $key=>$path) {
            $real=realpath($path);
            if ($real===false || !is_dir($real)) throw new InvalidArgumentException('Ungültiges Stammverzeichnis: '.$key);
            $this->roots[(string)$key]=$real;
        }
    }
    public function roots(): array { return array_keys($this->roots); }
    /** Authorized server drives with their Windows volume names (where available). */
    public function drives(): array {
        $volumeNames=$this->windowsVolumeNames();
        $result=[];
        foreach ($this->roots as $id=>$root) {
            $label=($id==='root'?'/':$id);
            // Display only: IDs and underlying paths remain unchanged.
            if (preg_match('/^[A-Za-z]:$/D',$id)) {
                $name=trim((string)($volumeNames[strtoupper($id[0])]??''));
                if ($name!=='') $label.=' ('.$name.')';
            }
            $result[]=['id'=>$id,'label'=>$label,'path'=>($id==='root'?'/':$id)];
        }
        return $result;
    }
    /** Read Windows volume labels; failure never prevents normal drive selection. */
    private function windowsVolumeNames(): array {
        if (PHP_OS_FAMILY!=='Windows') return [];
        $names=[];
        // Preferred method on Windows PHP with COM support; no child process.
        if (class_exists('COM')) {
            try {
                $fs=new COM('Scripting.FileSystemObject');
                foreach ($this->roots as $id=>$root) {
                    if (!preg_match('/^[A-Za-z]:$/D',$id)) continue;
                    try {
                        $drive=$fs->GetDrive(strtoupper($id));
                        if ($drive->IsReady) $names[strtoupper($id[0])]=(string)$drive->VolumeName;
                    } catch (\Throwable $ignored) { /* inaccessible media */ }
                }
            } catch (\Throwable $ignored) { /* COM disabled */ }
        }
        // PowerShell fallback for common PHP installs without the COM extension.
        if (count(array_filter($names))===0 && function_exists('shell_exec')) {
            try {
                $cmd='powershell.exe -NoProfile -NonInteractive -Command "Get-CimInstance Win32_LogicalDisk | Select-Object DeviceID,VolumeName | ConvertTo-Json -Compress"';
                $json=@shell_exec($cmd);
                $rows=is_string($json)?json_decode($json,true):null;
                if (isset($rows['DeviceID'])) $rows=[$rows];
                if (is_array($rows)) foreach ($rows as $row) {
                    if (!is_array($row) || !isset($row['DeviceID'])) continue;
                    $letter=strtoupper(substr((string)$row['DeviceID'],0,1));
                    if (preg_match('/^[A-Z]$/D',$letter) && is_string($row['VolumeName']??null)) {
                        $names[$letter]=$row['VolumeName'];
                    }
                }
            } catch (\Throwable $ignored) { /* subprocess disabled */ }
        }
        return $names;
    }
    public function fields(): array { return self::FIELDS; }
    /** Return navigable directories under the assigned server-side root. */
    public function folders(string $side, string $folder=''): array {
        $dir=$this->resolveFolder($side,$folder);
        $root=$this->roots[$side];
        $entries=[];
        $files=[];
        $it=new DirectoryIterator($dir);
        foreach ($it as $entry) {
            if ($entry->isDot() || $entry->isLink()) continue;
            $name=$entry->getFilename();
            if ($entry->isDir()) {
                $relative=($folder===''?'':trim(str_replace('\\','/',$folder),'/').'/').$name;
                $entries[]=['name'=>$name,'path'=>$relative];
            } elseif ($entry->isFile()) {
                // These are display-only entries. The picker cannot select a file.
                $files[]=['name'=>$name,'size'=>@$entry->getSize() ?: 0];
            }
        }
        usort($entries,fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));
        usort($files,fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));
        $current=ltrim(str_replace('\\','/',$folder),'/');
        $parent=$current===''?null:(str_contains($current,'/')?substr($current,0,strrpos($current,'/')):'');
        return ['side'=>$side,'folder'=>$current,'parent'=>$parent,
            'directories'=>$entries,'files'=>$files,
            'isEmpty'=>count($entries)===0 && count($files)===0,
            'rootLabel'=>($side==='root'?'/':$side)];
    }

    /** List files recursively or only in the selected folder, subject to a hard output cap. */
    public function list(string $side,string $name='',string $extension='',string $folder='',bool $recursive=true,array $columns=[],int $limit=5000,string $pattern='*',bool $invertPattern=false): array {
        $dir=$this->resolveFolder($side,$folder);
        $root=$this->roots[$side];
        $columns=$this->validatedColumns($columns);
        $limit=max(1,min(5000,$limit));
        $matcher=$this->compileFilenamePattern($pattern);
        $files=[];
        $directories=[];
        $iterator=$recursive
            ?new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST)
            :new FilesystemIterator($dir,FilesystemIterator::SKIP_DOTS);
        try { foreach ($iterator as $item) {
            if ($item->isLink() || (!$item->isFile() && !$item->isDir())) continue;
            $path=$item->getPathname();
            $real=realpath($path);
            if ($real===false || !$this->inside($root,$real)) continue;
            $filename=$item->getFilename(); $ext=strtolower($item->getExtension());
            $relative=ltrim(str_replace('\\','/',substr($real,strlen($root))),'/');
            // Directories are always visible for navigation, even if a filename filter
            // hides files. They can never be selected for destructive file operations.
            if ($item->isDir()) {
                $directories[]=[
                    'id'=>hash('sha256',$side.':dir:'.$relative), 'name'=>$filename,
                    'relativePath'=>$relative,'extension'=>'','size'=>null,
                    'modified'=>$item->getMTime(),'created'=>$item->getCTime(),
                    'accessed'=>$item->getATime(),'type'=>'dir','isDirectory'=>true,
                    'permissions'=>substr(sprintf('%o',$item->getPerms()),-4),
                    'readable'=>$item->isReadable(),'writable'=>$item->isWritable()
                ];
                if (count($directories)+count($files)>=$limit) break;
                continue;
            }
            if ((bool)preg_match($matcher,$filename) === $invertPattern) continue;
            if ($name!=='' && stripos($filename,$name)===false) continue;
            if ($extension!=='' && $ext!==strtolower(ltrim(trim($extension),'.'))) continue;
            $relative=ltrim(str_replace('\\','/',substr($real,strlen($root))),'/');
            $record=[
                'id'=>hash('sha256',$side.':'.$relative), 'name'=>$filename,
                'relativePath'=>$relative, 'extension'=>$ext, 'size'=>$item->getSize(),
                'modified'=>$item->getMTime(), 'created'=>$item->getCTime(),
                'accessed'=>$item->getATime(), 'type'=>$item->getType(), 'isDirectory'=>false,
                'permissions'=>substr(sprintf('%o',$item->getPerms()),-4),
                'readable'=>$item->isReadable(), 'writable'=>$item->isWritable()
            ];
            if (in_array('sha256',$columns,true)) $record['sha256']=hash_file('sha256',$real) ?: null;
            $files[]=$record;
            if (count($files)+count($directories)>=$limit) break;
        } } catch (UnexpectedValueException $e) { /* unreadable subdirectory: report already discovered files */ }
        usort($directories,fn($a,$b)=>strnatcasecmp($a['relativePath'],$b['relativePath']));
        usort($files,fn($a,$b)=>strnatcasecmp($a['relativePath'],$b['relativePath']));
        return array_merge($directories,$files);
    }
    /** Compare currently selected folders; hash only files with matching sizes. */
    public function duplicates(string $left='left',string $right='right',string $leftFolder='',string $rightFolder='',bool $recursive=true): array {
        $a=$this->list($left,'','',$leftFolder,$recursive);
        $b=$this->list($right,'','',$rightFolder,$recursive);
        $index=[];
        foreach ($b as $file) if (empty($file['isDirectory'])) $index[$file['size']][]=$file;
        $pairs=[];$cache=[];
        foreach ($a as $l) {
            if (!empty($l['isDirectory'])) continue;
            foreach ($index[$l['size']]??[] as $r) {
            $lp=$left.':'.$l['relativePath']; $rp=$right.':'.$r['relativePath'];
            $lh=$cache[$lp]??= $this->safeHash($left,$l['relativePath']);
            $rh=$cache[$rp]??= $this->safeHash($right,$r['relativePath']);
            if ($lh!==null && $rh!==null && hash_equals($lh,$rh)) $pairs[]=['left'=>$l,'right'=>$r,'sha256'=>$lh];
        }}
        return $pairs;
    }
    /** Mutating actions operate only on explicitly authorized files within the chosen drive. */
    public function perform(string $action,string $drive,array $paths,string $targetDrive='',string $targetFolder='',string $newName=''): array {
        if (!in_array($action,['delete','rename','copy','move'],true)) throw new InvalidArgumentException('Unbekannte Dateiaktion');
        if (!$paths || count($paths)>500) throw new InvalidArgumentException('1 bis 500 Dateien auswählen');
        if ($action==='rename' && count($paths)!==1) throw new InvalidArgumentException('Zum Umbenennen genau eine Datei auswählen');
        $destination= in_array($action,['copy','move'],true) ? $this->resolveFolder($targetDrive,$targetFolder) : '';
        if ($action==='rename' && (!$this->validBasename($newName))) throw new InvalidArgumentException('Ungültiger neuer Dateiname');
        $results=[];
        foreach ($paths as $relative) {
            try {
                if (!is_string($relative)) throw new RuntimeException('Ungültiger Pfad');
                $src=$this->resolveFile($drive,$relative);
                $target=($action==='rename') ? dirname($src).DIRECTORY_SEPARATOR.$newName : $destination.DIRECTORY_SEPARATOR.basename($src);
                if ($action==='delete') {
                    if (!@unlink($src)) throw new RuntimeException('Löschen fehlgeschlagen');
                } else {
                    if ($src===$target) throw new RuntimeException('Quelle und Ziel sind identisch');
                    if (file_exists($target) || is_link($target)) throw new RuntimeException('Zieldatei existiert bereits');
                    if ($action==='rename' || $action==='move') {
                        if (!@rename($src,$target)) throw new RuntimeException('Umbenennen/Verschieben fehlgeschlagen');
                    } else {
                        if (!@copy($src,$target)) throw new RuntimeException('Kopieren fehlgeschlagen');
                    }
                }
                $results[]=['path'=>$relative,'ok'=>true];
            } catch (Throwable $e) { $results[]=['path'=>is_string($relative)?$relative:'','ok'=>false,'error'=>$e->getMessage()]; }
        }
        return $results;
    }
    /** Preview/apply deletion, including directories, confined to the authorized drive and panel folder. */
    public function deleteEntries(string $drive,string $folder,array $paths,bool $apply=false,bool $confirmed=false): array {
        if (!$paths || count($paths)>500 || !array_is_list($paths)) throw new InvalidArgumentException('1 bis 500 Einträge auswählen');
        $panel=$this->resolveFolder($drive,$folder);
        $root=$this->roots[$drive]??null;
        if ($root===null) throw new InvalidArgumentException('Unbekanntes Laufwerk');
        $targets=[];
        foreach ($paths as $relative) {
            if (!is_string($relative) || $relative==='' || str_contains($relative,"\0") ||
                str_starts_with($relative,'/') || preg_match('/^[A-Za-z]:/',$relative) ||
                in_array('..',explode('/',str_replace('\\','/',$relative)),true) ||
                in_array('.',explode('/',str_replace('\\','/',$relative)),true)) throw new InvalidArgumentException('Ungültiger Auswahlpfad');
            // A realpath() result is NOT sufficient: for a selected symlink it
            // identifies the target and could trash the wrong (unselected) file.
            $normalized=str_replace('\\','/',$relative);
            $walk=$root;
            foreach(explode('/',$normalized) as $component){
                if($component==='')throw new InvalidArgumentException('Ungültiger Auswahlpfad');
                $walk.=DIRECTORY_SEPARATOR.$component;
                if(is_link($walk))throw new InvalidArgumentException('Symbolische Verknüpfung in der Auswahl ist nicht zulässig');
            }
            $path=realpath($root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$normalized));
            if ($path===false || !$this->inside($root,$path) || $path===$root || !$this->inside($panel,$path) || $path===$panel)
                throw new InvalidArgumentException('Ordner/Datei nicht erlaubt: '.$relative);
            if (!is_file($path) && !is_dir($path)) throw new InvalidArgumentException('Unbekannter Eintrag: '.$relative);
            $targets[$path]=['path'=>$relative,'isDirectory'=>is_dir($path)];
        }
        // Nested selections are processed just once by the outermost selected directory.
        uksort($targets,fn($a,$b)=>strlen($a)<=>strlen($b));
        $unique=[];
        foreach ($targets as $path=>$row) {
            $covered=false;
            foreach ($unique as $parent=>$parentRow) if($parentRow['isDirectory'] && $this->inside($parent,$path)) {$covered=true;break;}
            if (!$covered) $unique[$path]=$row;
        }
        $preview=[];
        foreach ($unique as $path=>$row) {
            $files=$row['isDirectory']?0:1;
            $dirs=$row['isDirectory']?1:0;
            if ($row['isDirectory']) {
                $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
                foreach ($it as $entry) {
                    // Do not traverse symlink targets, including outside the selected root.
                    if ($entry->isDir() && !$entry->isLink()) ++$dirs;
                    else ++$files;
                    if ($files+$dirs>200000) throw new RuntimeException('Zu viele Einträge (>200000) in einem Ordner');
                }
            }
            $preview[]=$row+['files'=>$files,'directories'=>$dirs];
        }
        if (!$apply) return $preview;
        if (!$confirmed) throw new InvalidArgumentException('Explizite Löschbestätigung fehlt');
        $results=[];
        foreach ($unique as $path=>$row) {
            try {
                if ($row['isDirectory']) {
                    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
                    foreach ($it as $entry) {
                        $item=$entry->getPathname();
                        if ($entry->isDir() && !$entry->isLink()) {
                            if (!@rmdir($item)) throw new RuntimeException('Unterordner konnte nicht gelöscht werden: '.$item);
                        } elseif (!@unlink($item)) throw new RuntimeException('Datei konnte nicht gelöscht werden: '.$item);
                    }
                    if (!@rmdir($path)) throw new RuntimeException('Ordner konnte nicht gelöscht werden');
                } elseif (!@unlink($path)) throw new RuntimeException('Datei konnte nicht gelöscht werden');
                $results[]=['path'=>$row['path'],'ok'=>true];
            } catch (Throwable $e) {$results[]=['path'=>$row['path'],'ok'=>false,'error'=>$e->getMessage()];}
        }
        return $results;
    }
    /** Conflict-aware transfer. Source files from subfolders are placed in the selected target folder by basename. */
    public function transfer(string $action,string $drive,array $paths,string $targetDrive,string $targetFolder,string $policy,bool $apply=false): array {
        $policies=['source','target','newer','older','larger','smaller','rename','identical'];
        if (!in_array($action,['copy','move'],true) || !in_array($policy,$policies,true)) throw new InvalidArgumentException('Ungültige Transferregel');
        if (!$paths || count($paths)>500 || !array_is_list($paths)) throw new InvalidArgumentException('1 bis 500 Dateien auswählen');
        $dir=$this->resolveFolder($targetDrive,$targetFolder);
        $seen=[];$result=[];
        foreach ($paths as $relative) {
            $entry=['path'=>is_string($relative)?$relative:'','ok'=>true,'decision'=>'','sourceSize'=>null,'targetSize'=>null,'sourceModified'=>null,'targetModified'=>null];
            try {
                if (!is_string($relative)) throw new RuntimeException('Ungültiger Pfad');
                $src=$this->resolveFile($drive,$relative);
                $name=basename($src);
                $target=$dir.DIRECTORY_SEPARATOR.$name;
                if (isset($seen[strtolower($name)])) throw new RuntimeException('Mehrere ausgewählte Quellen haben denselben Zieldateinamen');
                $seen[strtolower($name)]=true;
                if ($src===$target || (file_exists($target) && realpath($src)===realpath($target))) throw new RuntimeException('Quelle und Ziel sind identisch');
                if (is_link($target)) throw new RuntimeException('Symbolische Links im Ziel werden nicht überschrieben');
                if (file_exists($target) && !is_file($target)) throw new RuntimeException('Ziel ist keine reguläre Datei');
                $exists=is_file($target);
                clearstatcache(true,$src);clearstatcache(true,$target);
                $entry['sourceSize']=filesize($src);$entry['sourceModified']=filemtime($src);
                $entry['targetSize']=$exists?filesize($target):null;
                $entry['targetModified']=$exists?filemtime($target):null;
                $decision='copy';
                if ($exists) {
                    $decision=match($policy) {
                        'source'=>'replace',
                        'target'=>'skip',
                        'newer'=>($entry['sourceModified']>$entry['targetModified']?'replace':'skip'),
                        'older'=>($entry['sourceModified']<$entry['targetModified']?'replace':'skip'),
                        'larger'=>($entry['sourceSize']>$entry['targetSize']?'replace':'skip'),
                        'smaller'=>($entry['sourceSize']<$entry['targetSize']?'replace':'skip'),
                        'rename'=>'rename',
                        'identical'=>(hash_file('sha256',$src)===hash_file('sha256',$target)?'skip':'replace'),
                    };
                }
                if ($decision==='rename') {
                    $info=pathinfo($name);$stem=$info['filename']??$name;$ext=isset($info['extension'])?'.'.$info['extension']:'';
                    $found=false;
                    for ($i=1;$i<=10000;$i++) {
                        $alternative=$stem.' ('.$i.')'.$ext;
                        if (!file_exists($dir.DIRECTORY_SEPARATOR.$alternative) && !is_link($dir.DIRECTORY_SEPARATOR.$alternative)) {
                            $target=$dir.DIRECTORY_SEPARATOR.$alternative;$entry['newName']=$alternative;$found=true;break;
                        }
                    }
                    if (!$found) throw new RuntimeException('Kein freier Dateiname verfügbar');
                }
                $entry['decision']=$decision;
                if ($apply && $decision!=='skip') {
                    // Stage data first: failure during transfer does not damage the existing target.
                    $temp=tempnam($dir,'.files-transfer-');
                    if ($temp===false) throw new RuntimeException('Temporäre Zieldatei konnte nicht erstellt werden');
                    $backup=null;
                    try {
                        if (!@copy($src,$temp)) throw new RuntimeException('Kopieren in temporäre Datei fehlgeschlagen');
                        if (!@touch($temp,(int)$entry['sourceModified'])) throw new RuntimeException('Zeitstempel konnte nicht übertragen werden');
                        // Recheck target right before commit; never overwrite an unexpected new target.
                        if ($decision==='replace') {
                            if (!is_file($target) || is_link($target) || filesize($target)!==$entry['targetSize'] || filemtime($target)!==$entry['targetModified']) throw new RuntimeException('Zieldatei wurde zwischenzeitlich geändert');
                            $backup=tempnam($dir,'.files-backup-');
                            if ($backup===false) throw new RuntimeException('Sicherungsdatei konnte nicht erstellt werden');
                            @unlink($backup);
                            if (!@rename($target,$backup)) throw new RuntimeException('Zieldatei konnte nicht gesichert werden');
                        } elseif (file_exists($target) || is_link($target)) {
                            throw new RuntimeException('Zieldateiname ist inzwischen belegt');
                        }
                        if (!@rename($temp,$target)) throw new RuntimeException('Zieldatei konnte nicht erstellt werden');
                        $temp='';
                        if ($backup!==null) { @unlink($backup);$backup=null; }
                        if ($action==='move' && !@unlink($src)) {
                            throw new RuntimeException('Ziel erstellt, aber Quelle konnte nicht gelöscht werden; die Datei liegt an beiden Orten');
                        }
                    } catch (Throwable $e) {
                        if ($backup!==null && file_exists($backup)) {
                            if (file_exists($target)) @unlink($target);
                            @rename($backup,$target);
                        }
                        throw $e;
                    } finally {if ($temp!=='' && file_exists($temp)) @unlink($temp);}
                }
            } catch (Throwable $e) {$entry['ok']=false;$entry['error']=$e->getMessage();$entry['decision']='error';}
            $result[]=$entry;
        }
        return $result;
    }
    /** Remove a literal substring from selected basenames, without overwriting files. */
    public function removeFromNames(string $drive,array $paths,string $needle): array {
        if ($needle==='' || str_contains($needle,"\0")) throw new InvalidArgumentException('Suchtext darf nicht leer sein');
        if (count($paths)<1 || count($paths)>500) throw new InvalidArgumentException('1 bis 500 Dateien auswählen');
        if (strlen($needle)>255) throw new InvalidArgumentException('Suchtext zu lang');
        $plan=[]; $targets=[];
        foreach ($paths as $relative) {
            if (!is_string($relative)) throw new InvalidArgumentException('Ungültiger Pfad');
            $source=$this->resolveFile($drive,$relative);
            if (isset($plan[$source])) throw new InvalidArgumentException('Datei mehrfach ausgewählt');
            $old=basename($source);
            $new=str_replace($needle,'',$old);
            if ($new===$old) continue;
            if (!$this->validBasename($new) || (PHP_OS_FAMILY==='Windows' && (preg_match('/[<>"|?*]/',$new) || preg_match('/[. ]$/',$new)))) {
                throw new InvalidArgumentException('Ungültiger Ergebnisname: '.$new);
            }
            $target=dirname($source).DIRECTORY_SEPARATOR.$new;
            $key=PHP_OS_FAMILY==='Windows'?strtolower($target):$target;
            if (isset($targets[$key])) throw new InvalidArgumentException('Mehrere Dateien würden denselben Namen erhalten: '.$new);
            $targets[$key]=true;
            // Files which already exist are deliberately never replaced, including other selected files.
            if (file_exists($target) || is_link($target)) throw new InvalidArgumentException('Zieldatei existiert bereits: '.$new);
            $plan[$source]=['path'=>$relative,'oldName'=>$old,'newName'=>$new,'target'=>$target];
        }
        $results=[];
        foreach ($plan as $source=>$entry) {
            if (file_exists($entry['target']) || is_link($entry['target'])) throw new RuntimeException('Zieldatei existiert bereits: '.$entry['newName']);
            $ok=@rename($source,$entry['target']);
            $results[]=['path'=>$entry['path'],'oldName'=>$entry['oldName'],'newName'=>$entry['newName'],'ok'=>$ok];
            if (!$ok) throw new RuntimeException('Umbenennung fehlgeschlagen: '.$entry['oldName'].'; vorherige Umbenennungen wurden bereits ausgeführt');
        }
        return $results;
    }
    private function validBasename(string $name): bool {
        return $name!=='' && $name!=='.' && $name!=='..' && strlen($name)<=255 && !preg_match('~[\\/\x00-\x1f]~',$name) && !str_contains($name,':');
    }
    private function resolveFile(string $drive,string $relative): string {
        if (!isset($this->roots[$drive]) || str_contains($relative,"\0") || str_starts_with($relative,'/') || preg_match('/^[A-Za-z]:/',$relative)) throw new InvalidArgumentException('Ungültiger Dateipfad');
        $root=$this->roots[$drive];
        $candidate=$root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative);
        if (is_link($candidate)) throw new InvalidArgumentException('Symbolische Links nicht zulässig');
        $real=realpath($candidate);
        if ($real===false || !$this->inside($root,$real) || !is_file($real)) throw new InvalidArgumentException('Datei nicht gefunden oder nicht freigegeben');
        return $real;
    }
    /** Glob-style filename pattern: * matches any number of characters, ? exactly one. */
    private function compileFilenamePattern(string $pattern): string {
        $pattern=trim($pattern);
        if ($pattern==='') $pattern='*';
        if (strlen($pattern)>255 || str_contains($pattern, "\0")) throw new InvalidArgumentException('Ungültiger Dateifilter');
        $quoted=preg_quote($pattern,'~');
        return '~^'.str_replace(['\\*','\\?'],['.*','.'],$quoted).'$~iu';
    }
    private function validatedColumns(array $columns): array {
        if ($columns===[]) return [];
        foreach ($columns as $column) if (!is_string($column) || !array_key_exists($column,self::FIELDS)) throw new InvalidArgumentException('Unbekannte Eigenschaft');
        return array_values(array_unique($columns));
    }
    private function inside(string $root,string $path): bool {
        return $path===$root || str_starts_with($path,rtrim($root,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
    }
    private function resolveFolder(string $side,string $folder): string {
        if (!isset($this->roots[$side])) throw new InvalidArgumentException('Unbekanntes Panel');
        if (str_contains($folder,"\0") || str_starts_with($folder,'/') || preg_match('/^[A-Za-z]:/',$folder)) throw new InvalidArgumentException('Ungültiger Ordner');
        $root=$this->roots[$side];
        $path=realpath($root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$folder));
        if ($path===false || !$this->inside($root,$path) || !is_dir($path)) throw new InvalidArgumentException('Ordner nicht gefunden oder außerhalb des Stammverzeichnisses');
        return $path;
    }
    private function safeHash(string $side,string $relative): ?string {
        $root=$this->roots[$side];
        $path=realpath($root.DIRECTORY_SEPARATOR.$relative);
        if ($path===false || !$this->inside($root,$path) || !is_file($path)) return null;
        return hash_file('sha256',$path) ?: null;
    }
}
