"""Optional HTTP integration test (Python only for test execution, never application runtime).
Run against a PHP built-in server with FILES_ALLOWED_DRIVES_JSON={"Test":"<scratch>"}
and FILES_PRIVATE_STORAGE pointing outside HTTP document root.
Example: FILES_TEST_BASE_URL=http://127.0.0.1:8765/ FILES_TEST_DRIVE_ROOT=<scratch> python tests/test_http_integration.py
"""
import os,re,uuid,pathlib,shutil,requests,sys
BASE=os.getenv('FILES_TEST_BASE_URL','').rstrip('/')+'/'
ROOT=os.getenv('FILES_TEST_DRIVE_ROOT','')
if not BASE.strip('/') or not ROOT:
 print('SKIP: FILES_TEST_BASE_URL und FILES_TEST_DRIVE_ROOT setzen')
 sys.exit(0)
ROOT=pathlib.Path(ROOT).resolve()
SESSION=requests.Session();passes=0

def check(value,desc):
 global passes
 if not value:raise AssertionError(desc)
 passes+=1
 print('PASS',desc)

def get(action,**args):
 r=SESSION.get(BASE+'assets/php/api.php',params={'action':action,**args},timeout=15)
 data=r.json()
 if not r.ok or not data.get('ok'):raise AssertionError(f'{action}: {r.status_code} {data}')
 return data['data']

def post(action,**args):
 r=SESSION.post(BASE+'assets/php/api.php',json={'action':action,**args},headers={'X-CSRF-Token':TOKEN},timeout=40)
 data=r.json()
 if not r.ok or not data.get('ok'):raise AssertionError(f'{action}: {r.status_code} {data}')
 return data['data']

html=SESSION.get(BASE+'index.php',timeout=15).text
TOKEN=re.search(r'window.FILES_RENAME_CSRF = "([0-9a-f]+)"',html).group(1)
check(bool(TOKEN),'Session und CSRF-Token')
check(any(x['id']=='Test' for x in get('config')['drives']),'Freigegebenes Laufwerk')
R='audit_'+uuid.uuid4().hex[:8]
TMP=ROOT/R
SRC=TMP/'source';TARGET=TMP/'target'
try:
 SRC.mkdir(parents=True);TARGET.mkdir();(SRC/'nested').mkdir();(SRC/'nested'/'inner.txt').write_text('INNER')
 (SRC/'keep.txt').write_text('KEEP');(SRC/'drop.txt').write_text('DROP');(SRC/'report__draft.md').write_text('REPORT');(SRC/'batch1.txt').write_text('BATCH')
 prefix=f'{R}/source';dest=f'{R}/target'
 folders=get('folders',drive='Test',folder=prefix)
 check(any(f['name']=='keep.txt' for f in folders['files']) and any(d['name']=='nested' for d in folders['directories']),'Ordnerdialog: Unterordner und Dateien sichtbar')
 items=get('list',drive='Test',folder=prefix,recursive=0,pattern='*.md',columns='name,relativePath,size')
 check({x['name'] for x in items if not x['isDirectory']}=={'report__draft.md'} and any(x['isDirectory'] for x in items),'Filter und Ordnerzeilen')
 reverse=get('list',drive='Test',folder=prefix,recursive=0,pattern='*.md',invertPattern=1)
 check('keep.txt' in {x['name'] for x in reverse},'Umgekehrter Dateifilter')
 check(post('v5Statistics',drive='Test',folder=prefix,recursive=True)['files']>=5,'Verzeichnisstatistik')
 check(post('filePreview',drive='Test',path=prefix+'/keep.txt')['content']=='KEEP','Textvorschau')
 created=post('createFolder',drive='Test',folder=prefix,name='added')
 check((SRC/'added').is_dir() and created['ok'],'Neuen Ordner anlegen')
 post('renameEntry',drive='Test',path=prefix+'/report__draft.md',newName='report__new.md')
 check((SRC/'report__new.md').exists(),'Einzelumbenennung')
 post('removeNameText',drive='Test',paths=[prefix+'/report__new.md'],needle='__new')
 check((SRC/'report.md').exists(),'Teiltext aus Dateinamen entfernen')
 bp=dict(drive='Test',folder=prefix,paths=[prefix+'/batch1.txt'],pattern='Nummer_{n:2}',start=1,step=1,recursive=False)
 check(post('batchRenamePreview',**bp)['items'][0]['after']==prefix+'/Nummer_01.txt','Reihenumbenennung Vorschau')
 post('batchRenameApply',**bp)
 check((SRC/'Nummer_01.txt').exists(),'Reihenumbenennung Ausführung')
 move=dict(operation='copy',drive='Test',paths=[prefix+'/keep.txt'],targetDrive='Test',targetFolder=dest,policy='source')
 check(post('transferPreview',**move)[0]['decision']=='copy','Kopiervorschau')
 check(post('transferApply',**move)[0]['ok'] and (TARGET/'keep.txt').read_text()=='KEEP','Datei kopieren')
 skip=dict(move,operation='move',policy='target')
 check(post('transferApply',**skip)[0]['decision']=='skip' and (SRC/'keep.txt').exists(),'Konfliktregel Ziel behalten / Quelle erhalten')
 tree=dict(move,paths=[prefix+'/nested'])
 check(post('transferTreePreview',**tree),'Ordnervorschau')
 check(post('transferTreeApply',**tree)[0]['ok'] and (TARGET/'nested'/'inner.txt').read_text()=='INNER','Ordner rekursiv kopieren')
 scan=post('duplicateScan',criteria=['name','size','sha256'],left={'drive':'Test','folder':prefix,'recursive':False,'pattern':'*','invert':False},right={'drive':'Test','folder':dest,'recursive':False,'pattern':'*','invert':False})
 check(scan['groupCount']>=1,'Erweiterter Dublettenvergleich')
 check(len(get('duplicates',leftDrive='Test',rightDrive='Test',leftFolder=prefix,rightFolder=dest,recursive=0))>=1,'SHA-256-Dublettenvergleich')
 move2=dict(operation='move',drive='Test',paths=[prefix+'/report.md'],targetDrive='Test',targetFolder=dest,policy='source')
 check(post('transferApply',**move2)[0]['ok'] and not (SRC/'report.md').exists() and (TARGET/'report.md').exists(),'Datei verschieben')
 trash_paths=[prefix+'/drop.txt',prefix+'/added']
 pp=dict(drive='Test',folder=prefix,paths=trash_paths)
 check(len(post('deletePreview',**pp))==2,'Löschvorschau exakt markierter Einträge')
 gone=post('deleteApply',**pp,confirmed=True)
 check(all(x['ok'] for x in gone) and not (SRC/'drop.txt').exists() and not (SRC/'added').exists() and (SRC/'keep.txt').exists(),'Nur markierte Dateien und Ordner in Papierkorb')
 check(len(post('trashList'))>=2,'Papierkorbliste')
 for entry in gone: post('trashRestore',id=entry['id'])
 check((SRC/'drop.txt').read_text()=='DROP' and (SRC/'added').is_dir(),'Wiederherstellung beider Einträge')
 (SRC/'undo.txt').write_text('UNDO')
 post('deleteApply',drive='Test',folder=prefix,paths=[prefix+'/undo.txt'],confirmed=True)
 post('undoLastTrash')
 check((SRC/'undo.txt').read_text()=='UNDO','Letztes Löschen rückgängig')
 check(any(x['event']=='trash.put' for x in post('auditRecent')),'Aktionsprotokoll')
 denial=SESSION.post(BASE+'assets/php/api.php',json={'action':'deleteApply','drive':'Test','folder':prefix,'paths':[prefix+'/keep.txt'],'confirmed':True},timeout=10)
 check(denial.status_code==403 and (SRC/'keep.txt').exists(),'CSRF-Schutz gegen unerlaubtes Löschen')
 if hasattr(os,'symlink'):
  try: os.symlink(SRC/'keep.txt',SRC/'alias.txt')
  except OSError:pass
  if (SRC/'alias.txt').is_symlink():
   try:post('deletePreview',drive='Test',folder=prefix,paths=[prefix+'/alias.txt'])
   except AssertionError:pass
   else:raise AssertionError('Symlink wurde nicht blockiert')
   check((SRC/'keep.txt').read_text()=='KEEP','Symlink gegen ungewolltes Löschen geschützt')
 # Check ZIP only on servers where the extension is enabled.
 ziptest=SESSION.post(BASE+'assets/php/api.php',json={'action':'v5ZipCreate','drive':'Test','folder':prefix,'paths':[prefix+'/keep.txt'],'name':'test.zip'},headers={'X-CSRF-Token':TOKEN},timeout=30).json()
 if ziptest.get('ok'):
  check((SRC/'test.zip').exists(),'ZIP erstellen')
  (SRC/'unzipped').mkdir()
  z=post('v5ZipExtract',drive='Test',folder=prefix+'/unzipped',path=prefix+'/test.zip')
  check(z['entries']>=1 and (SRC/'unzipped'/R/'source'/'keep.txt').exists(),'ZIP entpacken')
 else:
  check('zip' in ziptest.get('error','').lower(),'ZIP-Erweiterung fehlt: korrekte Diagnose statt beschädigtem Archiv')
 print(f'OK: {passes} HTTP-Integrationstests bestanden.')
finally:
 shutil.rmtree(TMP,ignore_errors=True)
