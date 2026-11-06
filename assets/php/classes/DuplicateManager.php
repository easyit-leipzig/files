<?php
declare(strict_types=1);
/** V3: Compare two authorized directory views. Never mutates source files. */
final class DuplicateManager {
    public function __construct(private Files $files, private array $roots) {}
    public function scan(array $input): array {
        $keys=['name','size','modified','sha256'];
        $rules=$input['criteria']??[];
        if(!is_array($rules) || !array_is_list($rules) || !$rules || array_diff($rules,$keys)) throw new InvalidArgumentException('Mindestens ein gültiges Vergleichskriterium erforderlich');
        $rules=array_values(array_unique($rules));
        $sides=[];
        foreach(['left','right'] as $side){
            $cfg=$input[$side]??null;
            if(!is_array($cfg))throw new InvalidArgumentException('Panelangaben fehlen');
            $drive=(string)($cfg['drive']??'');
            if(!isset($this->roots[$drive]))throw new InvalidArgumentException('Laufwerk nicht freigegeben');
            $folder=(string)($cfg['folder']??'');
            $recursive=($cfg['recursive']??false)===true;
            $pattern=(string)($cfg['pattern']??'*');
            $invert=($cfg['invert']??false)===true;
            // SHA calculation happens below only for same-size candidate sets.
            $rows=$this->files->list($drive,'','',$folder,$recursive,[],5000,$pattern,$invert);
            $sides[$side]=array_values(array_filter($rows,fn($row)=>empty($row['isDirectory'])));
        }
        if(($input['left']['drive']??null)===($input['right']['drive']??null) && ($input['left']['folder']??'')===($input['right']['folder']??'')) throw new InvalidArgumentException('Unterschiedliche Verzeichnisse wählen');
        $pre=[];
        foreach($sides as $side=>$rows)foreach($rows as $r){
            $parts=[];
            foreach($rules as $key)if($key!=='sha256')$parts[]=$r[$key];
            if(in_array('sha256',$rules,true))$parts[]=$r['size']; // fast prefilter; hashes only for equal sizes
            $key=json_encode($parts,JSON_THROW_ON_ERROR);
            $pre[$key][$side][]=$r;
        }
        $groups=[];
        foreach($pre as $candidate){
            if(empty($candidate['left'])||empty($candidate['right']))continue;
            if(in_array('sha256',$rules,true)){
                $hashGroups=[];
                foreach(['left','right'] as $side)foreach($candidate[$side] as $entry){
                    $abs=$this->safeFile((string)$input[$side]['drive'],$entry['relativePath']);
                    $digest=hash_file('sha256',$abs);
                    if($digest===false)continue;
                    $entry['sha256']=$digest;
                    $hashGroups[$digest][$side][]=$entry;
                }
                foreach($hashGroups as $rows)if(!empty($rows['left'])&&!empty($rows['right']))$groups[]=['left'=>$rows['left'],'right'=>$rows['right']];
            }else $groups[]=['left'=>$candidate['left'],'right'=>$candidate['right']];
        }
        usort($groups,fn($a,$b)=>strnatcasecmp($a['left'][0]['name'],$b['left'][0]['name']));
        return ['criteria'=>$rules,'groups'=>array_slice($groups,0,1000),'groupCount'=>count($groups),
            'scanned'=>['left'=>count($sides['left']),'right'=>count($sides['right'])],
            'truncated'=>count($groups)>1000, 'listCap'=>5000];
    }
    private function safeFile(string $drive,string $relative): string {
        $root=realpath((string)$this->roots[$drive]);
        $rel=str_replace('\\','/',$relative);
        if($rel===''||str_starts_with($rel,'/')||preg_match('/^[A-Za-z]:/',$rel)||in_array('..',explode('/',$rel),true))throw new InvalidArgumentException('Ungültiger Dateipfad');
        $abs=realpath($root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$rel));
        $r=rtrim(str_replace('\\','/',$root),'/').'/';$f=str_replace('\\','/',$abs?:'');
        if(PHP_OS_FAMILY==='Windows'){$r=strtolower($r);$f=strtolower($f);}
        if(!$abs||!str_starts_with($f,$r)||!is_file($abs)||is_link($abs))throw new InvalidArgumentException('Datei nicht zulässig');
        return $abs;
    }
}
