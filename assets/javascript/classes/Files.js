/** Files UI controller, noJquery 4 (nj) for DOM lookup and ready lifecycle. */
class Files {
  constructor(endpoint='assets/php/api.php') {
    this.endpoint=endpoint;
    this.currentFolders={left:'',right:''};
    this.currentDrives={left:'',right:''};
    this.availableDrives=[];
    this.selected={left:null,right:null};
    this.selectedPaths={left:new Set(),right:new Set()};
    this.rows={left:[],right:[]};
    this.originalRows={left:[],right:[]};
    this.sortState={left:{key:null,direction:null},right:{key:null,direction:null}};
    this.fields={};
    this.columns=['name','relativePath','extension','size','modified','created','permissions']; // compatibility default
    this.panelColumns={left:[...this.columns],right:[...this.columns]};
    this.columnDialogSide=null;
    this.recursive={left:false,right:false};
    this.filters={name:'',extension:''};
    this.panelPatterns={left:'*',right:'*'};
    this.invertPanelFilter={left:false,right:false};
    this.filterRequest={left:0,right:0};
    this.dialogSide=null; this.dialogFolder=''; this.dialogRequest=0;
    this.history={left:[],right:[]};this.historyPosition={left:-1,right:-1};
    this.favorites=this.loadFavorites();this.transferCancel=false;this.dragSide=null;
  }
  setupDragDrop(){
    for(const target of ['left','right']){
      const host=document.getElementById('panel-'+target);
      host.addEventListener('dragover',event=>{
        if(this.dragSide&&this.dragSide!==target){event.preventDefault();host.classList.add('v4-drop-target');event.dataTransfer.dropEffect='copy';}
      });
      host.addEventListener('dragleave',event=>{if(!host.contains(event.relatedTarget))host.classList.remove('v4-drop-target');});
      host.addEventListener('drop',event=>{
        host.classList.remove('v4-drop-target');
        if(!this.dragSide||this.dragSide===target)return;
        event.preventDefault();const side=this.dragSide;this.dragSide=null;
        const dialog=document.getElementById('v4-drag-dialog');
        document.getElementById('v4-drag-context').textContent=this.getSelectedEntries(side).length+' Einträge aus '+(side==='left'?'links':'rechts')+' nach '+(target==='left'?'links':'rechts')+' übertragen';
        dialog.dataset.source=side;dialog.showModal();
      });
    }
    const dlg=document.getElementById('v4-drag-dialog');
    for(const op of ['copy','move'])document.getElementById('v4-drag-'+op).addEventListener('click',()=>{
      const side=dlg.dataset.source;dlg.close();this.showTransferDialog(op,side).catch(e=>document.getElementById('status').textContent='Fehler: '+e.message);
    });
    document.getElementById('v4-drag-cancel').addEventListener('click',()=>dlg.close());
  }
  loadFavorites(){
    try {const list=JSON.parse(localStorage.getItem('files.v4.favorites')||'[]');return Array.isArray(list)?list.filter(x=>x&&typeof x.drive==='string'&&typeof x.folder==='string').slice(0,50):[];}
    catch(_){return [];}
  }
  saveFavorites(){try{localStorage.setItem('files.v4.favorites',JSON.stringify(this.favorites));}catch(_){}this.renderFavorites();}
  renderFavorites(){
    for(const side of ['left','right']){
      const select=document.getElementById('favorite-'+side);if(!select)continue;
      const current=select.value;select.replaceChildren();
      const empty=new Option('★ Favoriten öffnen …','');select.append(empty);
      for(let i=0;i<this.favorites.length;i++){
        const entry=this.favorites[i];select.append(new Option(entry.drive+':/'+entry.folder,String(i)));
      }
      if([...select.options].some(o=>o.value===current))select.value=current;
    }
  }
  toggleFavorite(side){
    const drive=this.currentDrives[side],folder=this.currentFolders[side];
    const i=this.favorites.findIndex(x=>x.drive===drive&&x.folder===folder);
    if(i>=0)this.favorites.splice(i,1);else this.favorites.push({drive,folder});
    this.saveFavorites();this.updateNav(side);
  }
  async openFavorite(side,index){
    if(index==='' || !this.favorites[Number(index)])return;
    const fav=this.favorites[Number(index)];
    if(!this.availableDrives.some(d=>d.id===fav.drive))throw new Error('Favoriten-Laufwerk nicht freigegeben');
    this.currentDrives[side]=fav.drive;document.getElementById('drive-'+side).value=fav.drive;
    await this.openFolder(side,fav.folder);
  }
  updateNav(side){
    const list=this.history[side],i=this.historyPosition[side];
    const back=document.getElementById('back-'+side),next=document.getElementById('forward-'+side),star=document.getElementById('star-'+side);
    if(back)back.disabled=i<1;
    if(next)next.disabled=i<0||i>=list.length-1;
    if(star){const exists=this.favorites.some(f=>f.drive===this.currentDrives[side]&&f.folder===this.currentFolders[side]);star.textContent=exists?'★ Favorit entfernen':'☆ Favorit hinzufügen';star.setAttribute('aria-pressed',String(exists));}
  }
  async navigateHistory(side,delta){
    const index=this.historyPosition[side]+delta,entry=this.history[side][index];if(!entry)return;
    if(!this.availableDrives.some(d=>d.id===entry.drive))throw new Error('Laufwerk nicht freigegeben');
    this.currentDrives[side]=entry.drive;document.getElementById('drive-'+side).value=entry.drive;
    await this.openFolder(side,entry.folder,false);this.historyPosition[side]=index;this.updateNav(side);
  }
  async previewSelected(side){
    const chosen=this.getSelectedFiles(side);if(chosen.length!==1)throw new Error('Für die Vorschau genau eine Datei markieren');
    const f=chosen[0],dlg=document.getElementById('file-preview-dialog'),content=document.getElementById('file-preview-content');
    content.textContent='Vorschau wird geladen …';dlg.showModal();
    try {
      const data=await this.secureRequest('filePreview',{drive:this.currentDrives[side],path:f.relativePath});
      document.getElementById('file-preview-title').textContent='Vorschau: '+data.name;
      content.replaceChildren();
      if(data.kind==='text'){
        const pre=document.createElement('pre');pre.className='v4-preview-text';pre.textContent=data.content+(data.truncated?'\n\n… Vorschau auf 128 KiB begrenzt':'');content.append(pre);
      }else if(data.kind==='image'){
        const img=document.createElement('img');img.alt=data.name;img.className='v4-preview-image';img.src='data:'+data.mime+';base64,'+data.base64;content.append(img);
      }else{content.textContent=data.message+' ('+data.size+' Bytes; '+data.mime+')';}
    }catch(e){content.textContent='Fehler: '+e.message;}
  }
  async request(action,params={}) {
    const url=new URL(this.endpoint,location.href);url.searchParams.set('action',action);
    Object.entries(params).forEach(([key,value])=>url.searchParams.set(key,String(value)));
    const response=await fetch(url,{cache:'no-store'});
    const result=await response.json();
    if (!response.ok || !result.ok) throw new Error(result.error||'API-Fehler');
    return result.data;
  }
  async mutate(action,payload) {
    const response=await fetch(this.endpoint,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':window.FILES_RENAME_CSRF},body:JSON.stringify({action,...payload}),cache:'no-store'});
    const data=await response.json();
    if(!response.ok || !data.ok) throw new Error(data.error||'Aktion fehlgeschlagen');
    return data.data;
  }
  /** Explicitly confirmed file operations; destination is the other panel's current folder. */
  async performAction(action,side) {
    this.assertSide(side);
    const chosen=action==='rename'?this.getSelectedEntries(side):this.getSelectedEntries(side);
    if(action==='delete') return this.deleteSelected(side);
    if(!chosen.length) throw new Error('Im Quellpanel sind keine Dateien oder Ordner ausgewählt');
    if(action==='rename' && chosen.length!==1) throw new Error('Zum Umbenennen genau einen Eintrag auswählen');
    const other=side==='left'?'right':'left';
    let newName='';
    if(action==='rename') {
      newName=window.prompt('Neuer Dateiname:',chosen[0].name);
      if(newName===null) return;
      if(!newName.trim() || newName==='.' || newName==='..' || /[\\/:]/.test(newName)) throw new Error('Ungültiger Dateiname');
    }
    const verb={delete:'in den Papierkorb verschieben',rename:'umbenennen',copy:'kopieren',move:'verschieben'}[action];
    if(!verb) throw new Error('Unbekannte Aktion');
    if (action==='copy' || action==='move') return this.showTransferDialog(action,side);
    if(!window.confirm(chosen.length+' ausgewählte Datei(en) '+verb+'?')) return;
    if (action==='rename') {
      await this.secureRequest('renameEntry',{drive:this.currentDrives[side],path:chosen[0].relativePath,newName});
      await Promise.all(['left','right'].map(s=>this.refreshSide(s)));
      document.getElementById('status').textContent='Eintrag erfolgreich umbenannt';return;
    }
    const results=await this.mutate(action,{drive:this.currentDrives[side],paths:chosen.map(f=>f.relativePath),targetDrive:this.currentDrives[other],targetFolder:this.currentFolders[other],newName});
    await Promise.all(['left','right'].map(s=>this.refreshSide(s)));
    const failed=results.filter(x=>!x.ok);
    const succeeded=results.length-failed.length;
    if(failed.length) throw new Error(succeeded+' erfolgreich, '+failed.length+' fehlgeschlagen: '+failed.slice(0,3).map(x=>x.path+': '+x.error).join('; '));
    document.getElementById('status').textContent=succeeded+' Datei(en) erfolgreich bearbeitet';
  }
  /** Two-step protected deletion. Folder selection is restricted to delete. */
  async deleteSelected(side) {
    this.assertSide(side);
    const chosen=this.getSelectedEntries(side);
    if(!chosen.length) throw new Error('Bitte Dateien oder Ordner markieren');
    const payload={drive:this.currentDrives[side],folder:this.currentFolders[side],paths:chosen.map(x=>x.relativePath)};
    const preview=await this.deleteRequest('deletePreview',payload);
    const folders=preview.filter(x=>x.isDirectory);
    const files=preview.filter(x=>!x.isDirectory);
    const count=preview.reduce((n,x)=>n+(x.files||0),0);
    const dirs=preview.reduce((n,x)=>n+(x.directories||0),0);
    const details=preview.map(x=>(x.isDirectory?'📁 ':'📄 ')+x.path+' ('+x.files+' Dateien, '+x.directories+' Unterordner)').join('\n');
    const message='IN DEN PAPIERKORB VERSCHIEBEN?\n\nQuellpanel: '+(side==='left'?'Links':'Rechts')+' ('+payload.drive+':/'+payload.folder+')\n\n'+folders.length+' markierte Ordner, '+files.length+' einzelne Dateien.\nInsgesamt: '+count+' Dateien und '+dirs+' Ordner (einschließlich markierter Ordner).\n\n'+details.slice(0,3500)+'\n\nDie Einträge werden in den privaten Papierkorb verschoben und können wiederhergestellt werden. Fortfahren?';
    if(!window.confirm(message)) return;
    // Explicit typed confirmation prevents accidental recursive deletion.
    if(folders.length && window.prompt('Zur Bestätigung PAPIERKORB eingeben:')!=='PAPIERKORB') return;
    let results;
    try {
      results=await this.deleteRequest('deleteApply',{...payload,confirmed:true});
    } catch(error) {
      // Keep the checkboxes when nothing has been moved, so retry is possible.
      throw new Error('Papierkorb-Aktion nicht ausgeführt: '+error.message);
    }
    const errors=results.filter(x=>!x.ok);
    const ok=results.length-errors.length;
    if(ok>0) this.clearSelection(side);
    // Always reload to reflect actual filesystem state (also partial success).
    await Promise.allSettled(['left','right'].map(x=>this.refreshSide(x)));
    const warnings=results.filter(x=>x.warning).map(x=>x.path+': '+x.warning);
    document.getElementById('status').textContent=ok+' Einträge im Papierkorb, '+errors.length+' Fehler'+(warnings.length?' · '+warnings.join('; '):'');
    if(errors.length) throw new Error(errors.slice(0,4).map(x=>x.path+': '+x.error).join('; '));
  }
  async deleteRequest(action,payload) {
    const response=await fetch(this.endpoint,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':window.FILES_RENAME_CSRF},body:JSON.stringify({action,...payload}),cache:'no-store'});
    const result=await response.json();
    if(!response.ok||!result.ok)throw new Error(result.error||'Löschen fehlgeschlagen');
    return result.data;
  }
  async showTrashDialog() {
    const dialog=document.getElementById('trash-dialog');
    const rows=document.getElementById('trash-rows');
    const entries=await this.secureRequest('trashList',{});
    rows.replaceChildren();
    if(!entries.length)rows.textContent='Papierkorb ist leer.';
    for(const item of entries){
      const row=document.createElement('div');row.className='trash-entry';
      const label=document.createElement('span');label.textContent=(item.isDirectory?'📁 ':'📄 ')+item.drive+':/'+item.path+' · '+item.deletedAt;
      const button=document.createElement('button');button.type='button';button.textContent='Wiederherstellen';
      button.addEventListener('click',async()=>{
        if(!confirm('Wiederherstellen?\n'+item.drive+':/'+item.path+'\nBestehende Dateien werden nicht überschrieben.'))return;
        try {await this.secureRequest('trashRestore',{id:item.id});await Promise.all(['left','right'].map(x=>this.refreshSide(x)));await this.showTrashDialog();document.getElementById('status').textContent='Wiederhergestellt: '+item.path;}
        catch(err){document.getElementById('status').textContent='Fehler: '+err.message;}
      });
      row.append(label,button);rows.append(row);
    }
    if(!dialog.open)dialog.showModal();
  }
  async undoLastTrash(){
    if(!confirm('Zuletzt in den Papierkorb verschobenen Eintrag wiederherstellen?'))return;
    const result=await this.secureRequest('undoLastTrash',{});
    await Promise.all(['left','right'].map(x=>this.refreshSide(x)));
    document.getElementById('status').textContent='Wiederhergestellt: '+result.path;
  }
  async showAudit(){
    const events=await this.secureRequest('auditRecent',{});
    const dialog=document.getElementById('audit-dialog');
    const pre=document.getElementById('audit-rows');pre.textContent=events.map(x=>x.at+' '+x.event+' '+JSON.stringify(x.context)).join('\n')||'Noch keine Aktionen protokolliert.';
    dialog.showModal();
  }
  async showTransferDialog(operation,side) {
    this.assertSide(side);
    const selected=this.getSelectedEntries(side);
    if(!selected.length)throw new Error('Keine Dateien oder Ordner markiert');
    const other=side==='left'?'right':'left';
    const dialog=document.getElementById('transfer-dialog');
    if(dialog.open)throw new Error('Ein Übertragungsdialog ist bereits geöffnet');
    const select=document.getElementById('transfer-policy');
    const preview=document.getElementById('transfer-preview');
    const summary=document.getElementById('transfer-summary');
    const execute=document.getElementById('transfer-apply');
    const cancelButton=document.getElementById('transfer-cancel');
    const progressBox=document.getElementById('transfer-progress');
    const progressBar=document.getElementById('transfer-progress-bar');
    const progressLabel=document.getElementById('transfer-progress-label');
    const abort=document.getElementById('transfer-abort');
    const verb=operation==='copy'?'kopieren':'verschieben';
    document.getElementById('transfer-title').textContent=operation==='copy'?'Dateien und Ordner kopieren':'Dateien und Ordner verschieben';
    document.getElementById('transfer-context').textContent=selected.length+' markierte Einträge von '+this.currentDrives[side]+':/'+this.currentFolders[side]+' nach '+this.currentDrives[other]+':/'+this.currentFolders[other];
    select.value='newer';
    // Bind source, destination and original selection to THIS operation.
    // Switching panels behind an open dialog cannot silently redirect a transfer.
    const context={operation,drive:this.currentDrives[side],paths:selected.map(f=>f.relativePath),targetDrive:this.currentDrives[other],targetFolder:this.currentFolders[other]};
    const payload=(policy=select.value)=>({...context,policy});
    let busy=false,valid=false,previewVersion=0,controller=null,closed=false;
    const describe={copy:'Neu kopieren',merge:'Ordner zusammenführen',replace:'Ziel ersetzen',skip:'Ziel behalten / überspringen',rename:'Unter neuem Namen speichern',error:'Fehler'};
    const setProgress=(done,total,label)=>{
      progressBox.hidden=false;progressBar.max=Math.max(1,total);progressBar.value=done;
      progressLabel.textContent=done+' / '+total+' Einträge · '+label;
    };
    const refresh=async()=>{
      if(busy||closed)return;
      const version=++previewVersion;
      if(controller)controller.abort();
      controller=new AbortController();
      valid=false;execute.disabled=true;execute.textContent='Vorschau wird geprüft …';
      preview.replaceChildren();progressBox.hidden=true;abort.hidden=true;
      summary.textContent='Prüfe '+selected.length+' ausgewählte Einträge …';
      try {
        // Bounded top-level preview. Do NOT recurse through tens of thousands
        // of files before the user is allowed to start the operation.
        const rows=await this.transferRequest('transferSelectionPreview',payload(),controller.signal);
        if(closed||version!==previewVersion)return;
        if(!Array.isArray(rows)||!rows.length)throw new Error('Die Vorschau enthält keine Einträge');
        const counts={copy:0,merge:0,replace:0,skip:0,rename:0,error:0};
        for(const r of rows){
          counts[r.decision]=(counts[r.decision]||0)+1;
          const tr=document.createElement('tr');
          for(const content of [r.path,describe[r.decision]||r.decision,r.error||r.newName||'',r.targetSize==null?'–':String(r.targetSize)+' Bytes']){
            const td=document.createElement('td');td.textContent=content;tr.append(td);
          }
          preview.append(tr);
        }
        const folderCount=rows.filter(r=>r.isDirectory).length;
        const errors=rows.filter(r=>r.decision==='error'||r.ok===false);
        summary.textContent=counts.copy+' neu · '+counts.merge+' Ordner zusammenführen · '+counts.replace+' ersetzen · '+counts.rename+' umbenennen · '+counts.skip+' überspringen'+
          (folderCount?' · '+folderCount+' Ordner (Unterdateien werden während der Ausführung geprüft)':'')+
          (errors.length?'\nFehler: '+errors.slice(0,3).map(r=>r.path+': '+(r.error||'Nicht möglich')).join('; '):'');
        valid=errors.length===0&&rows.length>0;
        execute.disabled=!valid;
        execute.textContent=valid?'Aktion ausführen':'Aktion nicht möglich';
      }catch(e){
        if(closed||version!==previewVersion||e.name==='AbortError')return;
        summary.textContent='Vorschau fehlgeschlagen: '+e.message+'\nBitte Laufwerke, Pfade und PHP-Server prüfen.';
        execute.disabled=true;execute.textContent='Aktion nicht möglich';
      }
    };
    const change=()=>{if(!busy)void refresh();};
    const requestAbort=()=>{
      if(!busy)return;
      this.transferCancel=true;abort.disabled=true;progressLabel.textContent+=' · Abbruch nach aktuellem Eintrag angefordert';
    };
    const cancel=()=>{
      if(busy){requestAbort();return;}
      if(controller)controller.abort();dialog.close();
    };
    const executeAction=async()=>{
      if(busy||closed)return;
      if(!valid){await refresh();return;}
      if(!window.confirm(selected.length+' Einträge wirklich '+verb+'? Bestehende Zieldateien werden gemäß der gewählten Regel behandelt.'))return;
      busy=true;this.transferCancel=false;valid=false;
      if(controller)controller.abort();
      ++previewVersion;
      execute.disabled=true;execute.textContent='Übertragung läuft …';select.disabled=true;
      abort.hidden=false;abort.disabled=false;cancelButton.textContent='Nach aktuellem Eintrag abbrechen';
      let processed=0,done=0,skipped=0,errors=[];
      const policy=select.value;
      setProgress(0,selected.length,'Start');
      try{
        // One top-level item per request, so the user sees progress and can
        // cancel safely between items. Individual folders remain indivisible.
        for(const entry of selected){
          if(this.transferCancel)break;
          summary.textContent='Übertrage '+(processed+1)+' von '+selected.length+': '+entry.name+' …';
          setProgress(processed,selected.length,(entry.isDirectory?'Ordner':'Datei')+' '+entry.name+' wird '+(operation==='copy'?'kopiert':'verschoben')+' …');
          const itemAction=entry.isDirectory?'transferTreeApply':'transferApply';
          try{
            const results=await this.transferRequest(itemAction,{...payload(policy),paths:[entry.relativePath]});
            if(!Array.isArray(results))throw new Error('Ungültige Serverantwort');
            for(const r of results){if(!r.ok)errors.push(r.path+': '+(r.error||'Unbekannter Fehler'));
              else if(r.decision==='skip')skipped++;else done++;}
          }catch(e){errors.push(entry.relativePath+': '+e.message);}
          processed++;setProgress(processed,selected.length,entry.name);
        }
        try{await Promise.all(['left','right'].map(s=>this.refreshSide(s)));}
        catch(e){errors.push('Panel-Aktualisierung: '+e.message);}
        const note=done+' Dateien bearbeitet, '+skipped+' übersprungen, '+errors.length+' Fehler'+
          (this.transferCancel?' · Abgebrochen; bereits abgeschlossene Aktionen bleiben bestehen':'');
        document.getElementById('status').textContent=note;
        summary.textContent=note+(errors.length?'\n'+errors.slice(0,5).join('\n'):'');
        if(!this.transferCancel&&!errors.length)dialog.close();
      }finally{
        busy=false;select.disabled=false;abort.hidden=true;cancelButton.textContent='Abbrechen';
        execute.disabled=closed;execute.textContent='Vorschau aktualisieren';
        // A second transfer requires a fresh preview rather than reusing an
        // out-of-date plan after partially completed operations.
      }
    };
    const preventBusyClose=event=>{if(busy){event.preventDefault();requestAbort();}};
    const onClose=()=>{
      closed=true;++previewVersion;if(controller)controller.abort();
      select.removeEventListener('change',change);
      cancelButton.removeEventListener('click',cancel);
      execute.removeEventListener('click',executeAction);
      abort.removeEventListener('click',requestAbort);
      dialog.removeEventListener('cancel',preventBusyClose);
      cancelButton.textContent='Abbrechen';execute.textContent='Aktion ausführen';
    };
    progressBox.hidden=true;abort.hidden=true;execute.disabled=true;
    select.disabled=false;cancelButton.textContent='Abbrechen';
    select.addEventListener('change',change);
    cancelButton.addEventListener('click',cancel);
    execute.addEventListener('click',executeAction);
    abort.addEventListener('click',requestAbort);
    dialog.addEventListener('cancel',preventBusyClose);
    dialog.addEventListener('close',onClose,{once:true});
    dialog.showModal();
    await refresh();
  }
  async transferRequest(action,payload,signal) {
    let response;
    try {
      response=await fetch(this.endpoint,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':window.FILES_RENAME_CSRF},
        body:JSON.stringify({action,...payload}),cache:'no-store',signal});
    }catch(e){if(e.name==='AbortError')throw e;throw new Error('Keine Verbindung zur PHP-API: '+e.message);}
    const raw=await response.text();
    let result;
    try{result=JSON.parse(raw);}catch(_){throw new Error('PHP hat keine gültige JSON-Antwort geliefert (HTTP '+response.status+'). Bitte PHP-Fehlerprotokoll prüfen.');}
    if(!response.ok||!result.ok)throw new Error(result.error||'Übertragung fehlgeschlagen (HTTP '+response.status+')');
    return result.data;
  }
  async secureRequest(action,payload) {
    const response=await fetch(this.endpoint,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':window.FILES_RENAME_CSRF},body:JSON.stringify({action,...payload}),cache:'no-store'});
    const result=await response.json();
    if (!response.ok||!result.ok) throw new Error(result.error||'Ordneraktion fehlgeschlagen');
    return result.data;
  }
  async createFolder(side) {
    this.assertSide(side);
    const name=window.prompt('Name des neuen Ordners im '+(side==='left'?'linken':'rechten')+' Panel:');
    if(name===null) return;
    if(!name.trim()) throw new Error('Bitte einen Ordnernamen eingeben');
    await this.secureRequest('createFolder',{drive:this.currentDrives[side],folder:this.currentFolders[side],name});
    await this.refreshSide(side);
    document.getElementById('status').textContent='Ordner „'+name+'“ erstellt';
  }
  /** Rename only selected files of the requested panel after a preview/confirmation. */
  async removeNameText(side, needle) {
    this.assertSide(side);
    const chosen=this.getSelectedFiles(side);
    if(!chosen.length) throw new Error('Im Panel '+(side==='left'?'links':'rechts')+' zunächst Dateien markieren');
    needle=String(needle ?? document.getElementById('remove-name-input-'+side)?.value ?? '');
    if(!needle.length) throw new Error('Der Suchtext darf nicht leer sein');
    const changes=chosen.map(f=>({file:f,newName:f.name.split(needle).join('')})).filter(x=>x.newName!==x.file.name);
    if(!changes.length) throw new Error('Der Suchtext ist in keinem der markierten Dateinamen enthalten');
    const invalid=changes.find(x=>!x.newName || x.newName==='.' || x.newName==='..' || /[\\/:<>"|?*]/.test(x.newName) || /[. ]$/.test(x.newName));
    if(invalid) throw new Error('Ungültiger Ergebnisdateiname: '+invalid.newName);
    const preview=changes.slice(0,12).map(x=>x.file.name+'  →  '+x.newName).join('\n');
    if(!window.confirm('Aus '+changes.length+' Dateinamen im Panel '+(side==='left'?'links':'rechts')+' den Text „'+needle+'“ entfernen?\n\n'+preview+(changes.length>12?'\n… und '+(changes.length-12)+' weitere':'')+'\n\nDateien werden auf dem Server umbenannt. Fortfahren?')) return;
    const result=await this.mutate('removeNameText',{drive:this.currentDrives[side],paths:chosen.map(f=>f.relativePath),needle});
    await Promise.all(['left','right'].map(s=>this.refreshSide(s)));
    document.getElementById('status').textContent=result.length+' Datei(en) im Panel '+(side==='left'?'links':'rechts')+' umbenannt';
    return result;
  }
  updateActionSelection() {
    const node=document.getElementById('action-selection');
    if(node) node.textContent='Links: '+this.getSelectedEntries('left').length+' · Rechts: '+this.getSelectedEntries('right').length+' ausgewählt';
    for(const side of ['left','right']){const button=document.getElementById('preview-'+side);if(button)button.disabled=this.getSelectedFiles(side).length!==1;}
  }
  // V5: export currently displayed records, not all filesystem entries.
  v5Export(side,kind){
    this.assertSide(side);const rows=this.rows[side]||[];
    const fields=['name','relativePath','type','extension','size','modified','created','permissions'];
    const escapeCsv=value=>'"'+String(value??'').replace(/"/g,'""')+'"';
    const content=kind==='csv'?'\uFEFF'+[fields.join(';'),...rows.map(row=>fields.map(f=>escapeCsv(row[f])).join(';'))].join('\r\n'):
      JSON.stringify({drive:this.currentDrives[side],folder:this.currentFolders[side],exportedAt:new Date().toISOString(),filtered:true,records:rows},null,2);
    const blob=new Blob([content],{type:kind==='csv'?'text/csv;charset=utf-8':'application/json;charset=utf-8'});
    const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download='files-'+side+'-'+new Date().toISOString().slice(0,10)+'.'+kind;
    document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),30000);
    document.getElementById('status').textContent=rows.length+' sichtbare Einträge als '+kind.toUpperCase()+' exportiert';
  }
  v5Dialog(title){
    document.getElementById('v5-dialog-title').textContent=title;
    const host=document.getElementById('v5-dialog-body');host.replaceChildren();
    const dlg=document.getElementById('v5-dialog');dlg.showModal();return host;
  }
  v5Line(host,text){const p=document.createElement('p');p.textContent=text;host.append(p);return p;}
  v5Profiles(){try{const data=JSON.parse(localStorage.getItem('files.v5.profiles')||'{}');return data&&typeof data==='object'&&!Array.isArray(data)?data:{};}catch(_){return {};}}
  v5SaveProfiles(profiles){localStorage.setItem('files.v5.profiles',JSON.stringify(profiles));}
  v5ProfileDialog(side){
    this.assertSide(side);const host=this.v5Dialog('Filterprofile – Panel '+(side==='left'?'links':'rechts'));
    const profiles=this.v5Profiles();const label=document.createElement('label');label.textContent='Profilname';const name=document.createElement('input');name.placeholder='z. B. Fotos';label.append(name);host.append(label);
    const list=document.createElement('select');list.setAttribute('aria-label','Gespeichertes Profil');
    const refresh=()=>{list.replaceChildren(new Option('Gespeichertes Profil wählen …',''));for(const key of Object.keys(this.v5Profiles()).sort())list.add(new Option(key,key));};refresh();host.append(list);
    const controls=document.createElement('div');controls.className='dialog-actions';host.append(controls);
    const add=(title,fn)=>{const b=document.createElement('button');b.type='button';b.textContent=title;b.addEventListener('click',async()=>{try{await fn();}catch(e){this.v5Line(host,'Fehler: '+e.message);}});controls.append(b);};
    add('Speichern',()=>{const key=name.value.trim();if(!key||key.length>80)throw new Error('Profilname fehlt oder ist zu lang');const all=this.v5Profiles();if(Object.keys(all).length>=100&&!all[key])throw new Error('Maximal 100 Filterprofile');all[key]={pattern:this.panelPatterns[side],invert:this.invertPanelFilter[side],recursive:this.recursive[side],name:this.filters.name,extension:this.filters.extension};this.v5SaveProfiles(all);refresh();list.value=key;this.v5Line(host,'Profil gespeichert: '+key);});
    add('Anwenden',async()=>{const cfg=this.v5Profiles()[list.value];if(!cfg)throw new Error('Bitte Profil wählen');this.panelPatterns[side]=cfg.pattern||'*';this.invertPanelFilter[side]=!!cfg.invert;this.recursive[side]=!!cfg.recursive;document.getElementById('file-filter-'+side).value=this.panelPatterns[side];document.getElementById('invert-filter-'+side).checked=this.invertPanelFilter[side];document.getElementById('recursive-'+side).checked=this.recursive[side];if(cfg.name!==undefined)document.getElementById('name').value=cfg.name;if(cfg.extension!==undefined)document.getElementById('extension').value=cfg.extension;this.filters={name:cfg.name||'',extension:cfg.extension||''};await this.refresh();this.v5Line(host,'Profil angewendet: '+list.value);});
    add('Löschen',()=>{if(!list.value)throw new Error('Bitte Profil wählen');if(!confirm('Filterprofil wirklich löschen?'))return;const all=this.v5Profiles();delete all[list.value];this.v5SaveProfiles(all);refresh();});
    this.v5Line(host,'Profile werden lokal in diesem Browser gespeichert. Globale Namens-/Endungsfilter wirken weiterhin auf beide Panels.');
  }
  async v5Statistics(side){
    this.assertSide(side);const result=await this.secureRequest('v5Statistics',{drive:this.currentDrives[side],folder:this.currentFolders[side],recursive:this.recursive[side]});
    const host=this.v5Dialog('Verzeichnisstatistik – '+side);
    this.v5Line(host,'Dateien: '+result.files+' · Ordner: '+result.directories+' · Größe: '+result.bytes.toLocaleString('de-DE')+' Bytes');
    this.v5Line(host,result.recursive?'Mit Unterordnern':'Nur aktuelles Verzeichnis');
    if(result.truncated)this.v5Line(host,'Achtung: Statistik nach 100.000 Einträgen begrenzt.');
    const table=document.createElement('ul');for(const [ext,n] of Object.entries(result.extensions||{})){const li=document.createElement('li');li.textContent=ext+': '+n;table.append(li);}host.append(table);
  }
  async v5ZipCreate(side){
    this.assertSide(side);const chosen=this.getSelectedEntries(side);if(!chosen.length)throw new Error('Bitte Dateien oder Ordner markieren');
    const value=window.prompt('Name des neuen ZIP-Archivs:', 'Archiv.zip');if(value===null)return;
    if(!window.confirm(chosen.length+' markierte Einträge als '+value+' archivieren? Vorhandene ZIP-Dateien werden nicht überschrieben.'))return;
    const data=await this.secureRequest('v5ZipCreate',{drive:this.currentDrives[side],folder:this.currentFolders[side],paths:chosen.map(x=>x.relativePath),name:value});
    await this.refreshSide(side);document.getElementById('status').textContent='Archiv erstellt: '+data.name+' ('+data.entries+' Einträge)';
  }
  async v5ZipExtract(side){
    this.assertSide(side);const chosen=this.getSelectedFiles(side);if(chosen.length!==1||!chosen[0].name.toLowerCase().endsWith('.zip'))throw new Error('Bitte genau eine ZIP-Datei markieren');
    if(!window.confirm('Archiv '+chosen[0].name+' in den aktuellen Ordner entpacken? Bereits vorhandene Namen werden nicht überschrieben.'))return;
    const data=await this.secureRequest('v5ZipExtract',{drive:this.currentDrives[side],folder:this.currentFolders[side],path:chosen[0].relativePath});
    await this.refreshSide(side);document.getElementById('status').textContent=data.entries+' ZIP-Einträge entpackt';
  }
  async initialize() {
    const config=await this.request('config'); this.fields=config.fields;this.availableDrives=config.drives||[];
    if (!this.availableDrives.length) throw new Error('Keine freigegebenen Laufwerke vorhanden');
    for (const side of ['left','right']) {
      const select=nj('#drive-'+side).e;select.replaceChildren();
      for(const drive of this.availableDrives) {const option=document.createElement('option');option.value=drive.id;option.textContent=drive.label;select.append(option);}
      select.value=this.availableDrives[0].id;this.currentDrives[side]=select.value;
    }
    for(const side of ['left','right']) {
      try {const stored=JSON.parse(localStorage.getItem('files.columns.'+side)||'null');
        if(Array.isArray(stored)) {
          const valid=stored.filter(key=>Object.prototype.hasOwnProperty.call(this.fields,key));
          if(valid.length) this.panelColumns[side]=[...new Set(valid)];
        }
      } catch (_) { /* Browser-Speicher nicht verfügbar */ }
    }
    this.renderFavorites();
    this.setupDragDrop();
    await Promise.all(['left','right'].map(side=>this.openFolder(side,'')));
  }
  async folders(side,folder=this.currentFolders[side]) {return this.request('folders',{drive:this.currentDrives[side],folder});}
  async list(side,filters=this.filters) {
    return this.request('list',{drive:this.currentDrives[side],folder:this.currentFolders[side],recursive:this.recursive[side]?1:0,
      columns:this.panelColumns[side].join(','),name:filters.name||'',extension:filters.extension||'',pattern:this.panelPatterns[side]||'*',invertPattern:this.invertPanelFilter[side]?1:0});
  }
  async duplicates() {return this.request('duplicates',{leftDrive:this.currentDrives.left,rightDrive:this.currentDrives.right,leftFolder:this.currentFolders.left,rightFolder:this.currentFolders.right,recursive:this.recursive.left&&this.recursive.right?1:0});}
  /** Switch the selected drive and immediately display the files at its root. */
  async selectDrive(side,drive) {
    if(!this.availableDrives.some(item=>item.id===drive)) throw new Error('Unbekanntes Laufwerk');
    this.currentDrives[side]=drive; this.currentFolders[side]='';
    this.clearSelection(side);
    await this.openFolder(side,'');
  }
  /** Set selected server folder and load ALL matching files in that panel. */
  async openFolder(side,folder,record=true) {
    const data=await this.folders(side,folder);
    if (this.currentFolders[side]!==data.folder) this.clearSelection(side);
    this.currentFolders[side]=data.folder;
    const button=nj('#folder-'+side).e;
    button.textContent='📁 '+(data.folder ? data.rootLabel.replace(/\/$/,'')+'/'+data.folder : data.rootLabel);
    nj('#path-'+side).e.textContent=(data.rootLabel==='/'?'/':data.rootLabel+'/')+(data.folder||'');
    await this.refreshSide(side);
    if(record){
      const current={drive:this.currentDrives[side],folder:data.folder},list=this.history[side];
      const pos=this.historyPosition[side];
      if(!list[pos]||list[pos].drive!==current.drive||list[pos].folder!==current.folder){
        list.splice(pos+1);list.push(current);if(list.length>100)list.shift();this.historyPosition[side]=list.length-1;
      }
    }
    this.updateNav(side);
  }
  /** Open a modal browsing directories inside the configured server root. */
  async showFolderDialog(side) {
    if (!['left','right'].includes(side)) throw new Error('Unbekanntes Panel');
    const dialog=nj('#folder-dialog').e;
    if (dialog.open) dialog.close();
    this.dialogSide=side;
    this.dialogFolder=this.currentFolders[side];
    nj('#folder-dialog-title').e.textContent='Ordner für Panel '+(side==='left'?'links':'rechts')+' auswählen';
    dialog.showModal();
    try {await this.browseFolder(this.dialogFolder);} catch(error) {dialog.close();throw error;}
  }
  /** Browse without committing the current panel until confirmation. */
  async browseFolder(folder) {
    const side=this.dialogSide;
    if (!side) return;
    const requestId=++this.dialogRequest;
    const entries=nj('#folder-entries').e;
    entries.replaceChildren();entries.textContent='Verzeichnisse werden geladen …';
    const data=await this.folders(side,folder);
    if (requestId!==this.dialogRequest || this.dialogSide!==side) return;
    this.dialogFolder=data.folder;
    nj('#folder-breadcrumb').e.textContent='📁 '+data.rootLabel+(data.folder?(data.rootLabel==='/'?'':'/')+data.folder:'');
    entries.replaceChildren();
    const confirm=nj('#folder-confirm').e;
    confirm.disabled=!!data.isEmpty;
    confirm.title=data.isEmpty?'Dieser Ordner ist leer und kann nicht ausgewählt werden.':'';
    const summary=document.createElement('p');
    summary.className='folder-contents-summary';
    const pickerDirs=Array.isArray(data.directories)?data.directories:[];
    const pickerFiles=Array.isArray(data.files)?data.files:[];
    summary.textContent=pickerDirs.length+' Unterordner · '+pickerFiles.length+' Dateien'+(data.isEmpty?' — Ordner ist leer':'');
    entries.append(summary);
    const add=(label,target,kind)=>{
      const button=document.createElement('button');button.type='button';button.className='folder-entry '+kind;
      button.textContent=label;button.addEventListener('click',()=>this.browseFolder(target).catch(e=>{entries.textContent='Fehler: '+e.message;}));
      entries.append(button);
    };
    if (data.parent!==null) add('⬆ .. (übergeordneter Ordner)',data.parent,'parent');
    pickerDirs.forEach(item=>add('📁 '+item.name,item.path,'directory'));
    pickerFiles.forEach(item=>{
      const row=document.createElement('div');row.className='folder-entry picker-file';
      row.textContent='📄 '+item.name;row.title='Datei (nur Anzeige, nicht auswählbar)';
      entries.append(row);
    });
    if (data.isEmpty) {
      const empty=document.createElement('p');empty.textContent='Dieser Ordner ist leer. Bitte einen anderen Ordner wählen.';entries.append(empty);
    }
  }
  async confirmFolderDialog() {
    if (!this.dialogSide) return;
    const side=this.dialogSide, folder=this.dialogFolder;
    const data=await this.folders(side,folder);
    if(data.isEmpty) throw new Error('Leere Ordner können nicht ausgewählt werden');
    await this.openFolder(side,folder);
    this.closeFolderDialog();
  }
  closeFolderDialog() {
    ++this.dialogRequest;
    this.dialogSide=null;
    const dialog=nj('#folder-dialog').e;
    if (dialog.open) dialog.close();
  }
  /** Filter the filename independently for each panel, e.g. *.md or report_*.pdf. */
  async setPanelFilter(side,pattern) {
    if (!['left','right'].includes(side)) throw new Error('Unbekanntes Panel');
    this.panelPatterns[side]=String(pattern).trim()||'*';
    const input=document.getElementById('file-filter-'+side);
    if(input && input.value!==this.panelPatterns[side]) input.value=this.panelPatterns[side];
    return this.refreshSide(side);
  }
  /** Toggle wildcard match/inverse independently for each panel. */
  /** Include nested folders independently for each panel. */
  async setRecursive(side,enabled) {
    this.assertSide(side);
    this.recursive[side]=Boolean(enabled);
    await this.refreshSide(side);
  }
  async setInvertPanelFilter(side,enabled) {
    this.assertSide(side);
    this.invertPanelFilter[side]=Boolean(enabled);
    return this.refreshSide(side);
  }
  async refreshSide(side) {
    const requestId=++this.filterRequest[side];
    const records=await this.list(side);
    if(requestId===this.filterRequest[side]) this.render(side,records);
    return records;
  }
  async refresh(filters=this.filters) {
    this.filters={...filters};
    await Promise.all(['left','right'].map(side=>this.refreshSide(side)));
  }
  /** Open the independent field chooser for a panel. */
  showColumnDialog(side) {
    this.assertSide(side);
    this.columnDialogSide=side;
    const dialog=document.getElementById('column-dialog');
    const title=document.getElementById('column-dialog-title');
    title.textContent='Spalten für Panel '+(side==='left'?'links':'rechts')+' auswählen';
    const host=document.getElementById('column-dialog-options');host.replaceChildren();
    for(const [key,label] of Object.entries(this.fields)) {
      const wrapper=document.createElement('label');wrapper.className='column-option';
      const input=document.createElement('input');input.type='checkbox';input.value=key;
      input.checked=this.panelColumns[side].includes(key);
      wrapper.append(input,document.createTextNode(' '+label));host.append(wrapper);
    }
    if(!dialog.open) dialog.showModal();
  }
  closeColumnDialog() {
    const dialog=document.getElementById('column-dialog');
    if(dialog.open) dialog.close();
    this.columnDialogSide=null;
  }
  /** Commit selected fields for only one panel; at least one field is required. */
  async applyColumnDialog() {
    const side=this.columnDialogSide;
    if(!side) return;
    const host=document.getElementById('column-dialog-options');
    const columns=[...host.querySelectorAll('input:checked')].map(el=>el.value);
    if(!columns.length) throw new Error('Mindestens eine Dateieigenschaft auswählen');
    this.panelColumns[side]=columns;
    try {localStorage.setItem('files.columns.'+side,JSON.stringify(columns));}catch(_){}
    this.closeColumnDialog();
    await this.refreshSide(side);
  }
  /** Set columns programmatically for one panel. */
  async setPanelColumns(side,columns) {
    this.assertSide(side);
    if(!Array.isArray(columns)||!columns.length||columns.some(key=>!Object.prototype.hasOwnProperty.call(this.fields,key)))
      throw new Error('Ungültige Spaltenauswahl');
    this.panelColumns[side]=[...new Set(columns)];
    try {localStorage.setItem('files.columns.'+side,JSON.stringify(this.panelColumns[side]));}catch(_){}
    await this.refreshSide(side);
  }
  getSelectedEntries(side) {
    this.assertSide(side);
    return this.rows[side].filter(item=>this.selectedPaths[side].has(item.relativePath));
  }
  /** Return the selected file records currently visible in this panel. */
  getSelectedFiles(side) {
    this.assertSide(side);
    return this.rows[side].filter(file=>!file.isDirectory && this.selectedPaths[side].has(file.relativePath));
  }
  assertSide(side) {
    if (!['left','right'].includes(side)) throw new Error('Unbekanntes Panel');
  }
  /** Clear selections for exactly one panel. */
  clearSelection(side) {
    this.assertSide(side);
    this.selectedPaths[side].clear();
    this.selected[side]=null;
    document.querySelectorAll('#panel-'+side+' tbody input.file-select').forEach(box=>{
      box.checked=false;
      box.closest('tr')?.classList.remove('selected');
    });
    this.updateSelectionState(side);
  }
  /** Select/deselect every currently displayed file (not hidden filtered files). */
  selectAll(side,checked=true) {
    this.assertSide(side);
    this.rows[side].forEach(file=>{
      if(checked) this.selectedPaths[side].add(file.relativePath);
      else this.selectedPaths[side].delete(file.relativePath);
    });
    const body=document.querySelector('#panel-'+side+' tbody');
    if(body) body.querySelectorAll('input.file-select').forEach(box=>{box.checked=checked;box.closest('tr').classList.toggle('selected',checked);});
    this.updateSelectionState(side);
  }
  /** Keep the header select-all box and selected-count label in sync. */
  updateSelectionState(side) {
    const rows=this.rows[side];
    const count=rows.filter(file=>this.selectedPaths[side].has(file.relativePath)).length;
    const head=document.querySelector('#select-all-'+side);
    if(head) {
      head.checked=rows.length>0 && count===rows.length;
      head.indeterminate=count>0 && count<rows.length;
      head.disabled=rows.length===0;
    }
    const label=document.getElementById('selected-count-'+side);
    if(label) label.textContent=count+' ausgewählt';
    this.updateActionSelection();
  }
  /** Cycle per-panel sorting: unsorted -> ascending -> descending -> unsorted. */
  sortPanel(side,key) {
    this.assertSide(side);
    if(!this.panelColumns[side].includes(key)) throw new Error('Spalte nicht vorhanden');
    const previous=this.sortState[side];
    const direction=previous.key!==key?'asc':previous.direction==='asc'?'desc':null;
    this.sortState[side]={key:direction?key:null,direction};
    this.render(side,this.originalRows[side],false);
    return this.sortState[side];
  }
  /** Numeric/date columns sort numerically; text columns use German natural ordering. */
  sortedRows(side,items) {
    const {key,direction}=this.sortState[side];
    if(!key||!direction) return [...items];
    const numeric=['size','created','modified','accessed'];
    const compareText=new Intl.Collator('de',{numeric:true,sensitivity:'base'});
    return items.map((file,index)=>({file,index})).sort((a,b)=>{
      const av=a.file[key],bv=b.file[key];
      const emptyA=av===null||av===undefined||av==='';
      const emptyB=bv===null||bv===undefined||bv==='';
      if(emptyA||emptyB) return emptyA===emptyB?a.index-b.index:emptyA?1:-1;
      const result=numeric.includes(key)
        ? Number(av)-Number(bv)
        : compareText.compare(String(av),String(bv));
      return (direction==='asc'?1:-1)*result||a.index-b.index;
    }).map(entry=>entry.file).sort((a,b)=>Number(!!b.isDirectory)-Number(!!a.isDirectory));
  }
  /** Render the file table with a checkbox column and a per-panel select-all control. */
  render(side,items,saveOriginal=true) {
    this.assertSide(side);
    if(saveOriginal) this.originalRows[side]=[...items];
    items=this.sortedRows(side,this.originalRows[side]);
    this.rows[side]=items;
    // The current selection is confined to the currently displayed result set.
    const visible=new Set(items.map(file=>file.relativePath));
    for(const path of this.selectedPaths[side]) if(!visible.has(path)) this.selectedPaths[side].delete(path);
    this.selected[side]=null;
    const host=nj('#panel-'+side).e;host.replaceChildren();
    const table=document.createElement('table');table.className='file-table';
    const thead=document.createElement('thead'),head=document.createElement('tr');
    const selectionHeader=document.createElement('th');selectionHeader.className='selection-column';
    const selectAll=document.createElement('input');selectAll.type='checkbox';selectAll.id='select-all-'+side;
    selectAll.setAttribute('aria-label','Alle angezeigten Dateien und Ordner im Panel '+(side==='left'?'links':'rechts')+' auswählen');
    selectAll.title='Alle angezeigten Dateien und Ordner auswählen oder abwählen';
    selectAll.addEventListener('change',()=>this.selectAll(side,selectAll.checked));
    selectionHeader.append(selectAll);head.append(selectionHeader);
    this.panelColumns[side].forEach(key=>{
      const th=document.createElement('th');th.className='sortable-column-header';
      const {key:activeKey,direction}=this.sortState[side];
      const active=activeKey===key;
      th.setAttribute('aria-sort',active?(direction==='asc'?'ascending':'descending'):'none');
      const button=document.createElement('button');button.type='button';button.className='column-sort-button';
      button.textContent=(this.fields[key]||key)+' ';
      const indicator=document.createElement('span');indicator.className='sort-indicator';
      indicator.textContent=active?(direction==='asc'?'▲':'▼'):'↕';
      indicator.setAttribute('aria-hidden','true');button.append(indicator);
      button.title='Sortieren: aufsteigend → absteigend → ohne Sortierung';
      button.setAttribute('aria-label',(this.fields[key]||key)+': '+(active?(direction==='asc'?'aufsteigend':'absteigend'):'ohne Sortierung')+'. Zum Wechseln klicken');
      button.addEventListener('click',()=>this.sortPanel(side,key));
      th.append(button);head.append(th);
    });
    thead.append(head);table.append(thead);
    const tbody=document.createElement('tbody');
    // Virtual parent entry: navigation only, never part of file selection or actions.
    const current=this.currentFolders[side];
    if (current) {
      const parent=current.includes('/')?current.slice(0,current.lastIndexOf('/')):'';
      const tr=document.createElement('tr');tr.className='directory-row parent-directory-row';tr.tabIndex=0;
      tr.title='Doppelklick: Zum übergeordneten Ordner wechseln';
      const iconCell=document.createElement('td');iconCell.className='selection-column';iconCell.textContent='⬆';tr.append(iconCell);
      this.panelColumns[side].forEach(key=>{
        const td=document.createElement('td');td.textContent=key==='name'?'..':key==='relativePath'?(parent||'/'):'–';
        if(key==='name') td.className='filename';tr.append(td);
      });
      const goUp=async()=>{
        try {await this.openFolder(side,parent);} catch(error) {
          document.getElementById('status').textContent='Übergeordneten Ordner nicht geöffnet: '+error.message;
        }
      };
      tr.addEventListener('dblclick',goUp);
      tr.addEventListener('keydown',event=>{if(event.target===tr&&(event.key==='Enter'||event.key===' ')){event.preventDefault();goUp();}});
      tbody.append(tr);
    }
    items.forEach(file=>{
      const tr=document.createElement('tr');tr.tabIndex=0;tr.title=file.relativePath;
      const cell=document.createElement('td');cell.className='selection-column';
      if(file.isDirectory) {
        tr.classList.add('directory-row');
        tr.title='Doppelklick: Ordner öffnen. Checkbox: zum Löschen markieren';
      }
      tr.draggable=true;
      tr.addEventListener('dragstart',event=>{
        if(!this.selectedPaths[side].has(file.relativePath)){
          this.clearSelection(side);this.selectedPaths[side].add(file.relativePath);tr.classList.add('selected');checkbox.checked=true;this.updateSelectionState(side);
        }
        this.dragSide=side;event.dataTransfer.effectAllowed='copyMove';
        event.dataTransfer.setData('text/plain','Files: markierte Einträge');
      });
      tr.addEventListener('dragend',()=>{this.dragSide=null;for(const panel of ['left','right'])document.getElementById('panel-'+panel).classList.remove('v4-drop-target');});
      const checkbox=document.createElement('input');checkbox.type='checkbox';checkbox.className='file-select';
      checkbox.checked=this.selectedPaths[side].has(file.relativePath);
      checkbox.setAttribute('aria-label',(file.isDirectory?'Ordner ':'Datei ')+file.relativePath+' auswählen');
      cell.append(checkbox);tr.append(cell);
      this.panelColumns[side].forEach(key=>{
        const td=document.createElement('td');td.textContent=file.isDirectory && ['size','extension','sha256'].includes(key)?'–':this.format(key,file[key]);
        if(file.isDirectory && key==='name') td.textContent='📁 '+file.name;
        if (key==='name'||key==='relativePath') td.classList.add('filename');
        tr.append(td);
      });
      const choose=(checked)=>{
        if(checked) this.selectedPaths[side].add(file.relativePath);
        else this.selectedPaths[side].delete(file.relativePath);
        checkbox.checked=checked;tr.classList.toggle('selected',checked);
        this.selected[side]=checked?file:null;
        this.updateSelectionState(side);
      };
      checkbox.addEventListener('change',()=>choose(checkbox.checked));
      tr.addEventListener('click',event=>{if(event.target!==checkbox && !file.isDirectory) choose(!checkbox.checked);});
      if(file.isDirectory) {
        tr.addEventListener('dblclick',()=>this.openFolder(side,file.relativePath).catch(e=>{document.getElementById('status').textContent=e.message;}));
      }
      tr.addEventListener('keydown',event=>{if(event.target===tr&&(event.key==='Enter'||event.key===' ')){event.preventDefault();if(file.isDirectory && event.key==='Enter') this.openFolder(side,file.relativePath).catch(e=>{document.getElementById('status').textContent=e.message;});else choose(!checkbox.checked);}});
      tr.classList.toggle('selected',checkbox.checked);
      tbody.append(tr);
    });table.append(tbody);host.append(table);
    const folderCount=items.filter(item=>item.isDirectory).length;
    const fileCount=items.length-folderCount;
    nj('#count-'+side).e.textContent=fileCount+' Dateien · '+folderCount+' Ordner'+(items.length===5000?' (Maximum erreicht)':'');
    this.updateSelectionState(side);
  }
  format(key,value) {
    if (value===null||value===undefined) return '–';
    if (['modified','created','accessed'].includes(key)) return new Date(Number(value)*1000).toLocaleString('de-DE');
    if (key==='size') return Number(value).toLocaleString('de-DE');
    if (typeof value==='boolean') return value?'Ja':'Nein';
    return String(value);
  }
  openDuplicateDialog() {
    const dialog=document.getElementById('duplicate-dialog');
    this.duplicateResult=null;this.duplicateRemoveSide=null;
    document.getElementById('duplicate-summary').textContent='Vergleichskriterien auswählen und „Suchen“ drücken.';
    document.getElementById('duplicate-results').replaceChildren();
    for(const id of ['duplicate-mark','duplicate-trash'])document.getElementById(id).disabled=true;
    if(!this.duplicateDialogWired){
      this.duplicateDialogWired=true;
      document.getElementById('duplicate-scan').addEventListener('click',()=>this.scanAdvancedDuplicates());
      document.getElementById('duplicate-mark').addEventListener('click',()=>this.markAdvancedDuplicates());
      document.getElementById('duplicate-trash').addEventListener('click',()=>this.trashAdvancedDuplicates());
      for(const id of ['duplicate-close','duplicate-cancel'])document.getElementById(id).addEventListener('click',()=>dialog.close());
    }
    dialog.showModal();
  }
  async scanAdvancedDuplicates() {
    const summary=document.getElementById('duplicate-summary');
    const results=document.getElementById('duplicate-results');
    this.duplicateResult=null;this.duplicateRemoveSide=null;
    for(const id of ['duplicate-mark','duplicate-trash'])document.getElementById(id).disabled=true;
    results.replaceChildren(); summary.textContent='Dateien werden verglichen …';
    const criteria=['name','size','modified','sha256'].filter(key=>document.getElementById('dup-'+key).checked);
    const view=side=>({drive:this.currentDrives[side],folder:this.currentFolders[side],recursive:this.recursive[side],pattern:this.panelPatterns[side],invert:this.invertPanelFilter[side]});
    const context={left:view('left'),right:view('right')};
    try{
      const result=await this.mutate('duplicateScan',{criteria,...context});
      this.duplicateResult={result,context};
      summary.textContent=result.groupCount+' Treffergruppen · links '+result.scanned.left+' Dateien durchsucht, rechts '+result.scanned.right+' (max. '+result.listCap+' Einträge pro Panel).'+(result.truncated?' Nur die ersten 1.000 Gruppen angezeigt.':'');
      for(const group of result.groups){
        const section=document.createElement('div');section.className='duplicate-group';
        const heading=document.createElement('strong');heading.textContent=group.left[0].name+' · links '+group.left.length+', rechts '+group.right.length;section.append(heading);
        for(const side of ['left','right'])for(const file of group[side]){
          const line=document.createElement('div');line.textContent=(side==='left'?'Links: ':'Rechts: ')+file.relativePath+' ('+file.size+' Bytes)'+(file.sha256?' · SHA-256 '+file.sha256.slice(0,16)+'…':'');section.append(line);
        }
        results.append(section);
      }
      document.getElementById('duplicate-mark').disabled=!result.groups.length;
    }catch(error){summary.textContent='Fehler: '+error.message;}
  }
  async markAdvancedDuplicates() {
    if(!this.duplicateResult)throw new Error('Zuerst suchen');
    const {result,context}=this.duplicateResult;
    for(const side of ['left','right'])if(context[side].drive!==this.currentDrives[side]||context[side].folder!==this.currentFolders[side]||context[side].recursive!==this.recursive[side]||context[side].pattern!==this.panelPatterns[side]||context[side].invert!==this.invertPanelFilter[side])throw new Error('Panelansicht hat sich verändert. Erneut suchen.');
    const keep=document.getElementById('duplicate-keep').value;
    const remove=keep==='left'?'right':'left';
    this.duplicateRemoveSide=remove;
    // Mark only the opposite panel. No automatic deletion, and keep the chosen side untouched.
    const paths=new Set(result.groups.flatMap(group=>group[remove].map(file=>file.relativePath)));
    for(const side of ['left','right']){
      this.selectedPaths[side].clear();
      if(side===remove)for(const row of this.rows[side])if(!row.isDirectory&&paths.has(row.relativePath))this.selectedPaths[side].add(row.relativePath);
      this.render(side,this.originalRows[side],false);
    }
    document.getElementById('duplicate-trash').disabled=!this.getSelectedFiles(remove).length;
    document.getElementById('duplicate-summary').textContent=this.getSelectedFiles(remove).length+' Dateien in '+(remove==='left'?'linkem':'rechtem')+' Panel markiert. Vor dem Löschen bitte prüfen.';
  }
  async trashAdvancedDuplicates() {
    if(!this.duplicateResult)throw new Error('Zuerst suchen');
    const side=this.duplicateRemoveSide;
    if(!side || side===(document.getElementById('duplicate-keep').value))throw new Error('Behalten-Seite geändert. Treffer erneut markieren.');
    if(!this.getSelectedFiles(side).length)throw new Error('Keine Treffer markiert');
    document.getElementById('duplicate-dialog').close();
    await this.deleteSelected(side); // V2 double confirmation and private trash
  }
  /** Select equal filenames in BOTH currently displayed panels; leave all file rows visible. */
  async showDuplicates() {
    if (this.currentDrives.left===this.currentDrives.right &&
        this.currentFolders.left===this.currentFolders.right) {
      throw new Error('Bitte zwei unterschiedliche Ordner auswählen');
    }
    // Refresh the panels first to honor the current folder, wildcard and recursion filters.
    await this.refresh();
    const namesRight=new Set(this.rows.right.filter(file=>!file.isDirectory).map(file=>file.name));
    const namesLeft=new Set(this.rows.left.filter(file=>!file.isDirectory).map(file=>file.name));
    const common=new Set([...namesLeft].filter(name=>namesRight.has(name)));
    for(const side of ['left','right']) {
      this.selectedPaths[side].clear();
      for(const file of this.rows[side]) {
        if(!file.isDirectory && common.has(file.name)) this.selectedPaths[side].add(file.relativePath);
      }
      const checkboxes=document.querySelectorAll('#panel-'+side+' tbody input.file-select');
      // The DOM also contains directory checkboxes; do not shift file indices.
      checkboxes.forEach((checkbox,index)=>{
        const file=this.rows[side][index];
        const checked=!!file && !file.isDirectory && this.selectedPaths[side].has(file.relativePath);
        checkbox.checked=checked;
        checkbox.closest('tr')?.classList.toggle('selected',checked);
      });
      this.selected[side]=null;
      this.updateSelectionState(side);
    }
    return {names:common.size,left:this.getSelectedFiles('left').length,right:this.getSelectedFiles('right').length};
  }
}
window.Files=Files;
