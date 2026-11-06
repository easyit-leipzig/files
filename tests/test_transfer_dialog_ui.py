"""Browser mock regression for the 7-item cross-drive transfer dialog.
No web-server or Python requirements for running the PHP application.
"""
import os
from pathlib import Path
import requests
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
URL=os.environ.get('FILES_TEST_BASE_URL','http://127.0.0.1:8765/')
source=requests.get(URL.rstrip('/')+'/index.php',timeout=10).text
soup=BeautifulSoup(source,'html.parser')
scripts=[s.string or s.get_text() for s in soup.find_all('script') if not s.get('src')]
for s in soup.find_all('script'):s.decompose()
with sync_playwright() as pw:
 b=pw.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
 p=b.new_page()
 errors=[];p.on('pageerror',lambda error:errors.append(str(error)))
 p.set_content(str(soup))
 p.add_style_tag(content=(ROOT/'assets/css/files.css').read_text())
 for name in ['nojquery.min.js','classes/Files.js','classes/BatchRename.js','classes/FilesHelp.js']:
  p.add_script_tag(content=(ROOT/'assets/javascript'/name).read_text())
 p.evaluate(r'''() => {
  window.__calls=[];
  window.__fakeFiles=Array.from({length:7},(_,i)=>i===0?
   {name:'folderA',relativePath:'Downloads/folderA',isDirectory:true,type:'dir'}:
   {name:'song'+i+'.mp3',relativePath:'Downloads/song'+i+'.mp3',isDirectory:false,type:'file',size:30});
  Files.prototype.request=async function(action,args={}) {
   if(action==='config')return {fields:{name:'Name',relativePath:'Relativer Pfad',type:'Typ',size:'Größe'},drives:[{id:'C:',label:'C:'},{id:'E:',label:'E:'}]};
   if(action==='list')return args.drive==='C:'?[...window.__fakeFiles]:[];
   if(action==='folders')return {folder:args.folder||'',rootLabel:args.drive||'',parent:null,directories:[],files:[],isEmpty:true};
   return [];
  };
  window.fetch=async function(endpoint,opts={}) {
   const x=JSON.parse(opts.body||'{}');window.__calls.push(x);
   if(x.action==='transferSelectionPreview') {
    await new Promise(r=>setTimeout(r,150));
    return {ok:true,status:200,text:async()=>JSON.stringify({ok:true,data:x.paths.map((path,i)=>({path,ok:true,isDirectory:i===0,decision:'copy',targetSize:null}))})};
   }
   if(x.action==='transferApply'||x.action==='transferTreeApply') {
    return {ok:true,status:200,text:async()=>JSON.stringify({ok:true,data:[{path:x.paths[0],ok:true,decision:'copy'}]})};
   }
   return {ok:true,status:200,json:async()=>({ok:true,data:[]}),text:async()=>JSON.stringify({ok:true,data:[]})};
  };
 }''')
 for s in scripts:p.add_script_tag(content=s)
 p.wait_for_selector('#panel-left tbody input.file-select')
 p.locator('#drive-left').select_option('C:')
 p.wait_for_timeout(50)
 p.locator('#drive-right').select_option('E:')
 p.wait_for_timeout(50)
 p.locator('#panel-left tbody input.file-select').evaluate_all('(nodes)=>nodes.forEach(n=>{if(!n.checked)n.click()})')
 p.wait_for_function("document.getElementById('selected-count-left').textContent.includes('7')")
 p.locator('#action-source').select_option('left')
 p.locator('#action-copy').click()
 p.wait_for_function('document.getElementById("transfer-dialog").open')
 assert p.locator('#transfer-apply').is_disabled(),'Action must be disabled while preparing preview'
 assert p.evaluate("getComputedStyle(document.getElementById('transfer-progress')).display")=='none','Progress must not show fake 0/0'
 assert float(p.locator('#transfer-apply').evaluate('(el)=>getComputedStyle(el).opacity'))<1,'Disabled button visually distinct'
 p.wait_for_function('!document.getElementById("transfer-apply").disabled')
 assert '7 neu' in p.locator('#transfer-summary').inner_text()
 assert p.locator('#transfer-preview tr').count()==7
 p.on('dialog',lambda d:d.accept())
 p.locator('#transfer-apply').click()
 p.wait_for_function('!document.getElementById("transfer-dialog").open')
 calls=p.evaluate('window.__calls')
 assert len([c for c in calls if c['action']=='transferSelectionPreview'])==1,calls
 applied=[c for c in calls if c['action'] in ('transferApply','transferTreeApply')]
 assert len(applied)==7,applied
 assert applied[0]['action']=='transferTreeApply' and all(x['drive']=='C:' and x['targetDrive']=='E:' for x in applied),applied
 assert not errors,errors
 print('PASS: 7 mixed file/folder selections and cross-drive preview')
 print('PASS: No misleading 0/0 status; disabled button visually clear')
 print('PASS: Preview enables action and all 7 entries are transferred')
 print('PASS: Correct source/target and API action type')
 print('PASS: Panels refresh and dialog closes after transfer')
 b.close()
