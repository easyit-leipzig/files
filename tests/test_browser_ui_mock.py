"""Optional offline Chromium regression test; uses Playwright only in the test runner.
PHP application itself has no Python or browser automation dependency.
"""
from pathlib import Path
import os
import requests
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright
APP=Path(__file__).resolve().parents[1]
# PHP has to render session token in HTML; no browser network access is required.
url=os.getenv('FILES_TEST_BASE_URL','http://127.0.0.1:8765/').rstrip('/')+'/index.php'
raw=requests.get(url,timeout=8).text
soup=BeautifulSoup(raw,'html.parser')
js=[t.string or t.get_text() for t in soup.find_all('script') if not t.get('src')]
for script in soup.find_all('script'):script.decompose()
with sync_playwright() as pw:
 browser=pw.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
 page=browser.new_page()
 errors=[];page.on('pageerror',lambda error:errors.append(str(error)))
 page.set_content(str(soup))
 for script in ['assets/javascript/nojquery.min.js','assets/javascript/classes/Files.js','assets/javascript/classes/BatchRename.js','assets/javascript/classes/FilesHelp.js']:
  page.add_script_tag(content=(APP/script).read_text())
 page.evaluate(r'''() => {
  window.__calls=[];
  window.__fakeRows={
   '':[{name:'dir',relativePath:'dir',type:'dir',isDirectory:true},
       {name:'keep.txt',relativePath:'keep.txt',type:'file',size:4,isDirectory:false},
       {name:'shared.txt',relativePath:'shared.txt',type:'file',size:4,isDirectory:false}],
   'dir':[{name:'shared.txt',relativePath:'dir/shared.txt',type:'file',size:4,isDirectory:false},
         {name:'other.txt',relativePath:'dir/other.txt',type:'file',size:5,isDirectory:false}]
  };
  Files.prototype.request=async function(action,p={}) {
   if(action==='config') return {fields:{name:'Name',relativePath:'Relativer Pfad',size:'Größe',type:'Typ'},drives:[{id:'Test',label:'Test'}]};
   if(action==='folders') return {folder:p.folder||'',rootLabel:'Test',parent:p.folder?'':null,directories:p.folder?[]:[{name:'dir',path:'dir'}],files:[],isEmpty:false};
   if(action==='list') return [...(window.__fakeRows[p.folder||'']||[])];
   return [];
  };
  window.fetch=async function(endpoint,opts={}){
   const payload=JSON.parse(opts.body||'{}');
   window.__calls.push(payload);
   let data=[];
   if(payload.action==='deletePreview')data=payload.paths.map(path=>({path,isDirectory:false,files:1,directories:0}));
   if(payload.action==='deleteApply'){
    data=payload.paths.map(path=>({path,ok:true,id:'11111111111111111111111111111111'}));
    for(const key of Object.keys(window.__fakeRows))window.__fakeRows[key]=window.__fakeRows[key].filter(file=>!payload.paths.includes(file.relativePath));
   }
   return {ok:true,status:200,json:async()=>({ok:true,data})};
  };
 }''')
 for script in js:page.add_script_tag(content=script)
 page.locator('#panel-left tbody input.file-select').first.wait_for()
 assert not errors,errors
 # First folder row makes checkbox offsets different from file offsets.
 page.locator('#panel-right tbody tr.directory-row:not(.parent-directory-row)').dblclick()
 page.wait_for_function("document.querySelector('#path-right').textContent.includes('dir')")
 page.locator('#duplicates').click()
 page.wait_for_function("document.querySelector('#selected-count-right').textContent.includes('1')")
 # Both sides have selected 'shared.txt'; the folder must not be selected.
 assert page.locator('#panel-left tbody tr.directory-row input.file-select').first.is_checked() is False
 assert page.locator('#panel-left tbody tr').filter(has_text='shared.txt').locator('input.file-select').is_checked()
 assert page.locator('#panel-right tbody tr').filter(has_text='shared.txt').locator('input.file-select').is_checked()
 # Explicit right-side choice must delete only the right path even if left is marked.
 page.locator('#action-source').select_option('right')
 dialogs=[]
 page.on('dialog',lambda d:(dialogs.append(d.message),d.accept()))
 page.locator('#action-delete').click()
 page.wait_for_function("window.__calls.some(x=>x.action==='deleteApply')")
 calls=page.evaluate("window.__calls.filter(x=>x.action==='deleteApply')")
 assert len(calls)==1 and calls[0]['paths']==['dir/shared.txt'],calls
 assert not errors,errors
 assert page.locator('#panel-left tbody tr').filter(has_text='shared.txt').count()==1
 print('PASS UI: Dublettenmarkierung mit Ordnerzeilen korrekt')
 print('PASS UI: Quellpanel bei beidseitiger Auswahl wird respektiert')
 print('PASS UI: Nur markierte Datei im Lösch-POST')
 print('PASS UI: Panels nach dem Lösch-POST neu geladen')
 print('PASS UI: Keine ungefangenen JavaScript-Fehler')
 browser.close()
