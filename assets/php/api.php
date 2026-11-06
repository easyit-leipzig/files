<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/classes/Files.php';
require_once __DIR__.'/classes/FolderOperations.php';
require_once __DIR__.'/classes/TrashManager.php';
require_once __DIR__.'/classes/DuplicateManager.php';
require_once __DIR__.'/classes/FilePreview.php';
require_once __DIR__.'/classes/ArchiveManager.php';
require_once __DIR__.'/classes/BatchRename.php';
/**
 * IMPORTANT: Only expose drives that are authorized for this application.
 * Linux/Unix: '/' exposes all readable server files; restrict access to this app.
 * To limit the scope, replace '/' with a dedicated allowed directory.
 * Windows: accessible drive letters are listed (e.g. C:\\ and D:\\).
 */
$drives=[];
if (PHP_OS_FAMILY==='Windows') {
    foreach (range('A','Z') as $letter) {
        $root=$letter.':\\';
        if (@is_dir($root) && @is_readable($root)) $drives[$letter.':']=$root;
    }
} else {
    $drives['root']='/';
}
// Administrators can replace the detected map with an explicit allowlist:
// $drives = ['Data' => '/srv/data', 'Archiv' => '/mnt/archiv'];
// Recommended in production: FILES_ALLOWED_DRIVES_JSON={"Data":"D:\\Data"}.
$allowlist=getenv('FILES_ALLOWED_DRIVES_JSON');
if($allowlist!==false && trim($allowlist)!=='') {
    $allowed=json_decode($allowlist,true,512,JSON_THROW_ON_ERROR);
    if(!is_array($allowed)||!$allowed)throw new RuntimeException('FILES_ALLOWED_DRIVES_JSON muss eine nichtleere Objektzuordnung sein');
    $drives=[];
    foreach($allowed as $label=>$path) {
        if(!is_string($label)||!is_string($path)||$label===''||!is_dir($path))throw new RuntimeException('Ungültiger freigegebener Laufwerkspfad');
        $drives[$label]=realpath($path);
    }
}

try {
    $files=new Files($drives);
    $body=[];
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $body=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
        if (!is_array($body)) throw new InvalidArgumentException('Ungültiger JSON-Inhalt');
    }
    $action=(string)($body['action']??$_GET['action']??'list');
    $drive=(string)($_GET['drive']??array_key_first($drives)??'');
    $folder=(string)($_GET['folder']??'');
    $recursive=filter_var($_GET['recursive']??'0',FILTER_VALIDATE_BOOLEAN);
    $columns=isset($_GET['columns']) && (string)$_GET['columns']!=='' ? explode(',',(string)$_GET['columns']) : [];
    // V2: all mutations require the same CSRF token and same-origin protection.
    if (($_SERVER['REQUEST_METHOD']??'')==='POST') {
        if (session_status()!==PHP_SESSION_ACTIVE) session_start();
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
        if (!is_string($csrf)||!is_string($_SESSION['files_batch_rename_csrf']??null)||
            !hash_equals($_SESSION['files_batch_rename_csrf'],$csrf)) {
            http_response_code(403);throw new InvalidArgumentException('CSRF-Prüfung fehlgeschlagen');
        }
        $origin=$_SERVER['HTTP_ORIGIN']??'';
        if($origin!=='' && parse_url($origin,PHP_URL_HOST)!==parse_url('http://'.($_SERVER['HTTP_HOST']??''),PHP_URL_HOST)) {
            http_response_code(403);throw new InvalidArgumentException('Fremder Ursprung');
        }
    }
    if(in_array($action,['v5ZipCreate','v5ZipExtract','v5Statistics'],true)) {
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new InvalidArgumentException('POST erforderlich');
        // Validate the private audit store before changing user files.
        $audit=$action!=='v5Statistics'?new TrashManager($drives):null;
        $manager=new ArchiveManager($drives);$d=(string)($body['drive']??'');
        if($action==='v5ZipCreate')$data=$manager->create($d,(string)($body['folder']??''),(array)($body['paths']??[]),(string)($body['name']??''));
        elseif($action==='v5ZipExtract')$data=$manager->extract($d,(string)($body['path']??''),(string)($body['folder']??''));
        else $data=$manager->statistics($d,(string)($body['folder']??''),($body['recursive']??false)===true);
        if($audit!==null)$audit->log($action,['drive'=>$d,'result'=>$data]);
        echo json_encode(['ok'=>true,'data'=>$data],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);exit;
    }
    if($action==='filePreview') {
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new InvalidArgumentException('POST erforderlich');
        $data=(new FilePreview($drives))->read((string)($body['drive']??''),(string)($body['path']??''));
        echo json_encode(['ok'=>true,'data'=>$data],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);exit;
    }
    if($action==='duplicateScan') {
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new InvalidArgumentException('POST erforderlich');
        $data=(new DuplicateManager($files,$drives))->scan($body);
        echo json_encode(['ok'=>true,'data'=>$data],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);exit;
    }
    if (in_array($action,['trashList','trashRestore','auditRecent','undoLastTrash'],true)) {
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new InvalidArgumentException('POST erforderlich');
        $trash=new TrashManager($drives);
        if($action==='trashList')$data=$trash->entries();
        elseif($action==='auditRecent')$data=$trash->recentLog();
        elseif($action==='trashRestore')$data=$trash->restore((string)($body['id']??''));
        else {
            $entries=$trash->entries();
            if(!$entries)throw new InvalidArgumentException('Kein rückgängig machbarer Papierkorb-Eintrag vorhanden');
            $data=$trash->restore((string)$entries[0]['id']);
        }
        echo json_encode(['ok'=>true,'data'=>$data],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);exit;
    }
    // Reihenumbenennung nutzt genau dieselbe serverseitige Laufwerksfreigabe wie alle Dateiaktionen.
    if (in_array($action,['batchRenamePreview','batchRenameApply'],true)) {
        if ($_SERVER['REQUEST_METHOD']!=='POST') throw new InvalidArgumentException('POST erforderlich');
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
        if (!is_string($csrf) || !is_string($_SESSION['files_batch_rename_csrf']??null) ||
            !hash_equals($_SESSION['files_batch_rename_csrf'],$csrf)) {
            http_response_code(403);
            throw new InvalidArgumentException('CSRF-Prüfung fehlgeschlagen. Bitte Seite neu laden.');
        }
        $origin=$_SERVER['HTTP_ORIGIN']??'';
        if ($origin!=='' && parse_url($origin,PHP_URL_HOST)!==parse_url('http://'.($_SERVER['HTTP_HOST']??''),PHP_URL_HOST)) {
            http_response_code(403);
            throw new InvalidArgumentException('Fremder Ursprung');
        }
        $chosenDrive=$body['drive']??null;
        if (!is_string($chosenDrive) || !array_key_exists($chosenDrive,$drives)) throw new InvalidArgumentException('Unbekanntes Laufwerk');
        $chosenFolder=$body['folder']??'';
        $paths=$body['paths']??null;
        $pattern=$body['pattern']??null;
        $start=$body['start']??null;
        $step=$body['step']??null;
        $recursive=$body['recursive']??false;
        if (!is_string($chosenFolder) || !is_array($paths) || !array_is_list($paths) ||
            !is_string($pattern) || !is_int($start) || !is_int($step) || !is_bool($recursive)) {
            throw new InvalidArgumentException('Ungültige Musterdaten');
        }
        // Die Quellpfade müssen aus dem im Panel gewählten Ordner stammen.
        $folderNormalized=str_replace('\\','/',$chosenFolder);
        if ($folderNormalized!=='' && (str_starts_with($folderNormalized,'/') ||
            preg_match('/^[A-Za-z]:/',$folderNormalized) ||
            in_array('..',explode('/',$folderNormalized),true) ||
            in_array('.',explode('/',$folderNormalized),true))) {
            throw new InvalidArgumentException('Ungültiger Panelordner');
        }
        $folderNormalized=trim($folderNormalized,'/');
        foreach ($paths as $path) {
            if (!is_string($path)) throw new InvalidArgumentException('Ungültiger Dateipfad');
            $pathNormalized=str_replace('\\','/',$path);
            $parent=str_contains($pathNormalized,'/') ? substr($pathNormalized,0,strrpos($pathNormalized,'/')) : '';
            if ($recursive) {
                if ($folderNormalized!=='' && !str_starts_with($pathNormalized,$folderNormalized.'/')) {
                    throw new InvalidArgumentException('Datei liegt nicht im gewählten Panelordner');
                }
            } elseif ($parent!==$folderNormalized) {
                throw new InvalidArgumentException('Datei liegt nicht direkt im gewählten Panelordner');
            }
        }
        $audit=$action==='batchRenameApply'?new TrashManager($drives):null;
        $manager=new BatchRename([$chosenDrive=>$drives[$chosenDrive]],5000);
        $data= $action==='batchRenamePreview'
            ? $manager->preview($chosenDrive,$paths,$pattern,$start,$step)
            : $manager->apply($chosenDrive,$paths,$pattern,$start,$step);
        if($audit!==null)$audit->log('batchRenameApply',['drive'=>$chosenDrive,'paths'=>$paths,'result'=>$data]);
        echo json_encode(['ok'=>true,'data'=>$data],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    if ($action==='transferSelectionPreview') {
        if (($_SERVER['REQUEST_METHOD']??'')!=='POST') throw new InvalidArgumentException('POST erforderlich');
        $data=(new FolderOperations($drives,$files))->previewSelection(
            (string)($body['operation']??''),(string)($body['drive']??''),
            (array)($body['paths']??[]),(string)($body['targetDrive']??''),
            (string)($body['targetFolder']??''),(string)($body['policy']??''));
        echo json_encode(['ok'=>true,'data'=>$data],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    if (in_array($action,['transferTreePreview','transferTreeApply','createFolder','renameEntry'],true)) {
        if (($_SERVER['REQUEST_METHOD']??'')!=='POST') throw new InvalidArgumentException('POST erforderlich');
        if (session_status()!==PHP_SESSION_ACTIVE) session_start();
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
        if (!is_string($csrf) || !is_string($_SESSION['files_batch_rename_csrf']??null) || !hash_equals($_SESSION['files_batch_rename_csrf'],$csrf)) {
            http_response_code(403);throw new InvalidArgumentException('CSRF-Prüfung fehlgeschlagen');
        }
        $origin=$_SERVER['HTTP_ORIGIN']??'';
        if ($origin!=='' && parse_url($origin,PHP_URL_HOST)!==parse_url('http://'.($_SERVER['HTTP_HOST']??''),PHP_URL_HOST)) {
            http_response_code(403);throw new InvalidArgumentException('Fremder Ursprung');
        }
        $audit=$action!=='transferTreePreview'?new TrashManager($drives):null;
        $manager=new FolderOperations($drives,$files);
        $chosenDrive=(string)($body['drive']??'');
        if ($action==='createFolder') $data=$manager->create($chosenDrive,(string)($body['folder']??''),(string)($body['name']??''));
        elseif ($action==='renameEntry') $data=$manager->rename($chosenDrive,(string)($body['path']??''),(string)($body['newName']??''));
        else {
            if ($action==='transferTreeApply') @set_time_limit(600);
            $data=$manager->transfer((string)($body['operation']??''),$chosenDrive,
            (array)($body['paths']??[]),(string)($body['targetDrive']??''),
            (string)($body['targetFolder']??''),(string)($body['policy']??''),$action==='transferTreeApply');
        }
        if(in_array($action,['transferTreeApply','createFolder','renameEntry'],true)) {
            $audit->log($action,['drive'=>$chosenDrive,'paths'=>$body['paths']??[],'path'=>$body['path']??null,'name'=>$body['name']??null,'result'=>$data]);
        }
        echo json_encode(['ok'=>true,'data'=>$data],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    if (in_array($action,['transferPreview','transferApply'],true)) {
        if ($_SERVER['REQUEST_METHOD']!=='POST') throw new InvalidArgumentException('POST erforderlich');
        if (session_status()!==PHP_SESSION_ACTIVE) session_start();
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
        if (!is_string($csrf) || !is_string($_SESSION['files_batch_rename_csrf']??null) || !hash_equals($_SESSION['files_batch_rename_csrf'],$csrf)) {
            http_response_code(403); throw new InvalidArgumentException('CSRF-Prüfung fehlgeschlagen');
        }
        $origin=$_SERVER['HTTP_ORIGIN']??'';
        if ($origin!=='' && parse_url($origin,PHP_URL_HOST)!==parse_url('http://'.($_SERVER['HTTP_HOST']??''),PHP_URL_HOST)) {
            http_response_code(403);throw new InvalidArgumentException('Fremder Ursprung');
        }
        $audit=$action==='transferApply'?new TrashManager($drives):null;
        if ($action==='transferApply') @set_time_limit(600);
        $data=$files->transfer((string)($body['operation']??''),(string)($body['drive']??''),
            (array)($body['paths']??[]),(string)($body['targetDrive']??''),
            (string)($body['targetFolder']??''),(string)($body['policy']??''),$action==='transferApply');
        if($audit!==null)$audit->log('transferApply',['operation'=>$body['operation']??null,'paths'=>$body['paths']??[],'result'=>$data]);
        echo json_encode(['ok'=>true,'data'=>$data],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    if (in_array($action,['deletePreview','deleteApply'],true)) {
        if ($_SERVER['REQUEST_METHOD']!=='POST') throw new InvalidArgumentException('POST erforderlich');
        if (session_status()!==PHP_SESSION_ACTIVE) session_start();
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
        if (!is_string($csrf) || !is_string($_SESSION['files_batch_rename_csrf']??null) || !hash_equals($_SESSION['files_batch_rename_csrf'],$csrf)) {
            http_response_code(403); throw new InvalidArgumentException('CSRF-Prüfung fehlgeschlagen');
        }
        $origin=$_SERVER['HTTP_ORIGIN']??'';
        if ($origin!=='' && parse_url($origin,PHP_URL_HOST)!==parse_url('http://'.($_SERVER['HTTP_HOST']??''),PHP_URL_HOST)) {
            http_response_code(403);throw new InvalidArgumentException('Fremder Ursprung');
        }
        if($action==='deletePreview') {
            // Do not offer private trash contents or ancestor directories for deletion.
            $private=getenv('FILES_PRIVATE_STORAGE') ?: dirname(__DIR__,4).'/.files-private-v2';
            $privateReal=realpath($private);
            if($privateReal) foreach((array)($body['paths']??[]) as $candidate) {
                $driveRoot=realpath((string)($drives[(string)($body['drive']??'')]??''));
                $target=$driveRoot && is_string($candidate) ? realpath($driveRoot.DIRECTORY_SEPARATOR.$candidate) : false;
                if($target && ($target===$privateReal || str_starts_with($target.DIRECTORY_SEPARATOR,$privateReal.DIRECTORY_SEPARATOR) || str_starts_with($privateReal.DIRECTORY_SEPARATOR,$target.DIRECTORY_SEPARATOR))) throw new InvalidArgumentException('Privater Papierkorb und übergeordnete Ordner sind geschützt');
            }
            $data=$files->deleteEntries((string)($body['drive']??''),(string)($body['folder']??''),
                (array)($body['paths']??[]),false,false);
        } else {
            $trash=new TrashManager($drives);
            $data=$trash->put((string)($body['drive']??''),(string)($body['folder']??''),
                (array)($body['paths']??[]),($body['confirmed']??false)===true);
        }
        echo json_encode(['ok'=>true,'data'=>$data],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    if ($action==='removeNameText') {
        if ($_SERVER['REQUEST_METHOD']!=='POST') throw new InvalidArgumentException('POST erforderlich');
        $origin=$_SERVER['HTTP_ORIGIN']??'';
        if ($origin!=='' && parse_url($origin,PHP_URL_HOST)!==parse_url('http://'.($_SERVER['HTTP_HOST']??''),PHP_URL_HOST)) throw new InvalidArgumentException('Fremder Ursprung');
        $audit=new TrashManager($drives);
        $data=$files->removeFromNames((string)($body['drive']??''),(array)($body['paths']??[]),(string)($body['needle']??''));
        $audit->log('removeNameText',['paths'=>$body['paths']??[],'result'=>$data]);
        echo json_encode(['ok'=>true,'data'=>$data],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    if (in_array($action,['rename','copy','move'],true)) {
        if ($_SERVER['REQUEST_METHOD']!=='POST') throw new InvalidArgumentException('POST erforderlich');
        $origin=$_SERVER['HTTP_ORIGIN']??'';
        if ($origin!=='' && parse_url($origin,PHP_URL_HOST)!==parse_url('http://'.($_SERVER['HTTP_HOST']??''),PHP_URL_HOST)) throw new InvalidArgumentException('Fremder Ursprung');
        $audit=new TrashManager($drives);
        $data=$files->perform($action,(string)($body['drive']??''),(array)($body['paths']??[]),(string)($body['targetDrive']??''),(string)($body['targetFolder']??''),(string)($body['newName']??''));
        $audit->log('legacy.'.$action,['paths'=>$body['paths']??[],'result'=>$data]);
        echo json_encode(['ok'=>true,'data'=>$data],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    if($action==='delete')throw new InvalidArgumentException('Direktes Löschen deaktiviert: Papierkorb verwenden');
    switch ($action) {
        case 'config': $data=['fields'=>$files->fields(),'drives'=>$files->drives()];break;
        case 'folders': $data=$files->folders($drive,$folder);break;
        case 'list': $data=$files->list($drive,(string)($_GET['name']??''),(string)($_GET['extension']??''),$folder,$recursive,$columns,5000,(string)($_GET['pattern']??'*'),filter_var($_GET['invertPattern']??'0',FILTER_VALIDATE_BOOLEAN));break;
        case 'duplicates': $data=$files->duplicates((string)($_GET['leftDrive']??''),(string)($_GET['rightDrive']??''),(string)($_GET['leftFolder']??''),(string)($_GET['rightFolder']??''),$recursive);break;
        default: throw new InvalidArgumentException('Unbekannte Aktion');
    }
    echo json_encode(['ok'=>true,'data'=>$data],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    $status=http_response_code();
    if (!is_int($status) || $status<400) $status=$e instanceof InvalidArgumentException?400:500;
    http_response_code($status);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_INVALID_UTF8_SUBSTITUTE);
}
