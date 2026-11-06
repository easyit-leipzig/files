<?php
/** PHP-Session für die sichere Bestätigung der Muster-Reihenumbenennung. */
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['files_batch_rename_csrf'])) {
    $_SESSION['files_batch_rename_csrf'] = bin2hex(random_bytes(32));
}
?>
<!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Files – Dateivergleich</title><link rel="stylesheet" href="assets/css/files.css?v=20261105-transfer-1"><link rel="stylesheet" href="assets/css/batch-rename.css"></head>
<body><main><header><h1>Files – Dateivergleich</h1><p>Ordner auswählen und Dateieigenschaften vergleichen</p></header>
<div class="toolbar"><label>Dateiname <input id="name" placeholder="Filtern ..."></label><label>Endung <input id="extension" placeholder="z. B. pdf"></label>

<button id="filter">Dateien laden</button><button id="duplicates">Dubletten finden</button><button type="button" id="duplicates-advanced">Erweiterter Dublettenvergleich</button></div>

<p id="status" role="status" aria-live="polite"></p>
<div class="panels"><section class="panel"><h2><span class="panel-heading">Panel links</span><div class="panel-rename-controls"><input type="text" id="remove-name-input-left" class="remove-name-input" placeholder="Zu löschender Text" aria-label="Zu löschender Text im Panel links" autocomplete="off"><button type="button" class="remove-name-text" id="remove-name-left" title="Eingegebenen Text aus markierten Dateinamen links löschen">Aus Dateinamen löschen</button></div></h2><div class="v4-navigation" aria-label="Navigation left"><button type="button" id="back-left" title="Zurück" disabled>◀</button><button type="button" id="forward-left" title="Vorwärts" disabled>▶</button><button type="button" id="star-left" title="Aktuellen Ordner zu Favoriten hinzufügen">☆ Favorit hinzufügen</button><select id="favorite-left" aria-label="Favoriten left"><option value="">★ Favoriten öffnen …</option></select><button type="button" id="preview-left" disabled>Vorschau</button></div><label for="drive-left">Laufwerk wählen</label><select id="drive-left" aria-label="Laufwerk links"></select><label for="folder-left">Ordner wählen</label><button type="button" class="folder-picker" id="folder-left" aria-haspopup="dialog" aria-controls="folder-dialog">📁 Ordner wählen … <span aria-hidden="true">▾</span></button><label for="file-filter-left">Dateifilter (* = Platzhalter)</label><div class="panel-filter-row"><input type="text" class="panel-file-filter" id="file-filter-left" value="*" placeholder="z. B. *.md" aria-label="Dateien im Panel left filtern" autocomplete="off"><button type="button" class="panel-filter-apply" id="apply-filter-left">Anwenden</button></div><div class="panel-filter-options"><label class="panel-filter-invert"><input type="checkbox" id="invert-filter-left"> Filter umkehren</label><label class="panel-recursive"><input type="checkbox" id="recursive-left"> Unterordner einbeziehen</label><button type="button" class="panel-columns-button" id="columns-left" aria-haspopup="dialog" aria-controls="column-dialog" title="Sichtbare Tabellenspalten für Panel links auswählen">⚙ Spalten auswählen</button><button type="button" class="panel-batch-rename" id="batch-rename-left" aria-haspopup="dialog" aria-controls="batch-rename-dialog">Reihenumbenennung mit Muster</button><button type="button" id="v5-profile-left" title="Filterprofile speichern und anwenden">Filterprofile</button><button type="button" id="v5-stats-left" title="Verzeichnisstatistik">Statistik</button></div><div class="meta"><span id="path-left"></span><span id="count-left"></span><span id="selected-count-left" aria-live="polite">0 ausgewählt</span></div><div id="panel-left" class="table-wrap"></div></section>
<section class="panel"><h2><span class="panel-heading">Panel rechts</span><div class="panel-rename-controls"><input type="text" id="remove-name-input-right" class="remove-name-input" placeholder="Zu löschender Text" aria-label="Zu löschender Text im Panel rechts" autocomplete="off"><button type="button" class="remove-name-text" id="remove-name-right" title="Eingegebenen Text aus markierten Dateinamen rechts löschen">Aus Dateinamen löschen</button></div></h2><div class="v4-navigation" aria-label="Navigation right"><button type="button" id="back-right" title="Zurück" disabled>◀</button><button type="button" id="forward-right" title="Vorwärts" disabled>▶</button><button type="button" id="star-right" title="Aktuellen Ordner zu Favoriten hinzufügen">☆ Favorit hinzufügen</button><select id="favorite-right" aria-label="Favoriten right"><option value="">★ Favoriten öffnen …</option></select><button type="button" id="preview-right" disabled>Vorschau</button></div><label for="drive-right">Laufwerk wählen</label><select id="drive-right" aria-label="Laufwerk rechts"></select><label for="folder-right">Ordner wählen</label><button type="button" class="folder-picker" id="folder-right" aria-haspopup="dialog" aria-controls="folder-dialog">📁 Ordner wählen … <span aria-hidden="true">▾</span></button><label for="file-filter-right">Dateifilter (* = Platzhalter)</label><div class="panel-filter-row"><input type="text" class="panel-file-filter" id="file-filter-right" value="*" placeholder="z. B. *.md" aria-label="Dateien im Panel right filtern" autocomplete="off"><button type="button" class="panel-filter-apply" id="apply-filter-right">Anwenden</button></div><div class="panel-filter-options"><label class="panel-filter-invert"><input type="checkbox" id="invert-filter-right"> Filter umkehren</label><label class="panel-recursive"><input type="checkbox" id="recursive-right"> Unterordner einbeziehen</label><button type="button" class="panel-columns-button" id="columns-right" aria-haspopup="dialog" aria-controls="column-dialog" title="Sichtbare Tabellenspalten für Panel rechts auswählen">⚙ Spalten auswählen</button><button type="button" class="panel-batch-rename" id="batch-rename-right" aria-haspopup="dialog" aria-controls="batch-rename-dialog">Reihenumbenennung mit Muster</button><button type="button" id="v5-profile-right" title="Filterprofile speichern und anwenden">Filterprofile</button><button type="button" id="v5-stats-right" title="Verzeichnisstatistik">Statistik</button></div><div class="meta"><span id="path-right"></span><span id="count-right"></span><span id="selected-count-right" aria-live="polite">0 ausgewählt</span></div><div id="panel-right" class="table-wrap"></div></section></div>
<section class="actionbar" aria-label="Dateiaktionen"><div class="actionbar-title"><strong>Dateiaktionen</strong><span id="action-selection">Links: 0 · Rechts: 0 ausgewählt</span></div><div class="action-buttons"><label>Quellpanel <select id="action-source"><option value="left">Links</option><option value="right">Rechts</option></select></label><button type="button" id="action-copy">Kopieren → Gegenpanel</button><button type="button" id="action-move">Verschieben → Gegenpanel</button><button type="button" id="action-rename">Datei / Ordner umbenennen</button><button type="button" id="create-folder-left">+ Ordner links</button><button type="button" id="create-folder-right">+ Ordner rechts</button><button type="button" class="danger" id="action-delete">In Papierkorb</button><button type="button" id="trash-open">Papierkorb / Wiederherstellen</button><button type="button" id="trash-undo">Letztes Löschen rückgängig</button><button type="button" id="audit-open">Aktionsprotokoll</button><button type="button" id="v5-zip-create">ZIP erstellen</button><button type="button" id="v5-zip-extract">ZIP entpacken</button><button type="button" id="v5-export-csv">CSV exportieren</button><button type="button" id="v5-export-json">JSON exportieren</button></div><span class="action-hint">Löschen verschiebt markierte Dateien und Ordner einschließlich Unterordnern in den privaten Papierkorb. Wiederherstellung ist möglich, sofern der Zielpfad frei ist. Kopieren, Verschieben und Umbenennen unterstützen auch Ordner; Konfliktregeln gelten dateiweise. Kopieren und Verschieben verwenden den ausgewählten Ordner des Gegenpanels. Bei Namenskonflikten erscheint ein Dialog zur Auswahl der Konfliktregel.</span></section>
<dialog id="v5-dialog" aria-labelledby="v5-dialog-title"><h2 id="v5-dialog-title">Files V5</h2><div id="v5-dialog-body"></div><div class="dialog-actions"><button type="button" id="v5-dialog-close">Schließen</button></div></dialog>
<dialog id="folder-dialog" aria-labelledby="folder-dialog-title">
  <div class="dialog-heading"><h2 id="folder-dialog-title">Ordner auswählen</h2><button type="button" id="folder-close" class="secondary" aria-label="Schließen">✕</button></div>
  <p id="folder-dialog-hint">Ordner und enthaltene Dateien anzeigen. Nur nichtleere Ordner sind auswählbar.</p>
  <div id="folder-breadcrumb" class="folder-breadcrumb"></div>
  <div id="folder-entries" class="folder-entries" role="listbox" aria-label="Ordner und Dateien"></div>
  <div class="dialog-actions"><button type="button" id="folder-cancel" class="secondary">Abbrechen</button><button type="button" id="folder-confirm">Diesen Ordner auswählen</button></div>
</dialog>
<dialog id="column-dialog" aria-labelledby="column-dialog-title">
  <div class="dialog-heading"><h2 id="column-dialog-title">Tabellenspalten auswählen</h2><button type="button" id="column-close" class="secondary" aria-label="Schließen">✕</button></div>
  <p>Welche Dateieigenschaften sollen in diesem Panel angezeigt werden?</p>
  <div id="column-dialog-options" class="column-dialog-options"></div>
  <div class="dialog-actions"><button type="button" id="column-cancel" class="secondary">Abbrechen</button><button type="button" id="column-save">Übernehmen</button></div>
</dialog>
<dialog id="transfer-dialog" aria-labelledby="transfer-title" class="transfer-dialog"><div class="dialog-heading"><h2 id="transfer-title">Dateien übertragen</h2></div>
<p id="transfer-context"></p><label for="transfer-policy">Regel bei gleichem Dateinamen</label>
<select id="transfer-policy"><option value="source">Quelle ersetzt Ziel (immer)</option><option value="newer" selected>Neuere Datei behalten</option><option value="older">Ältere Datei behalten</option><option value="larger">Größere Datei behalten</option><option value="smaller">Kleinere Datei behalten</option><option value="target">Zieldatei behalten (überspringen)</option><option value="rename">Quelle mit neuem Namen behalten</option><option value="identical">Identische Inhalte überspringen, andere ersetzen (SHA-256)</option></select>
<p class="transfer-note">Bei Gleichstand bleibt das Ziel erhalten. Beim Verschieben werden übersprungene Quelldateien nicht gelöscht. Bei ausgewählten Ordnern bleibt deren Unterordnerstruktur erhalten; einzeln gewählte Dateien landen direkt im Zielordner.</p>
<div id="transfer-summary" role="status" aria-live="polite"></div><div id="transfer-progress" class="v4-progress" hidden><label id="transfer-progress-label" for="transfer-progress-bar">0 / 0</label><progress id="transfer-progress-bar" value="0" max="1"></progress><button type="button" id="transfer-abort" class="secondary" hidden>Nach aktuellem Eintrag abbrechen</button><small>Ein laufender Ordnertransfer wird abgeschlossen; danach stoppt der Auftrag. Bereits abgeschlossene Änderungen bleiben bestehen.</small></div><div class="transfer-table-wrap"><table class="transfer-table"><thead><tr><th>Quelldatei</th><th>Aktion</th><th>Hinweis / neuer Name</th><th>Zielgröße</th></tr></thead><tbody id="transfer-preview"></tbody></table></div>
<div class="dialog-actions"><button type="button" id="transfer-cancel" class="secondary">Abbrechen</button><button type="button" id="transfer-apply" disabled>Aktion ausführen</button></div></dialog>
<dialog id="v4-drag-dialog" aria-labelledby="v4-drag-title"><h2 id="v4-drag-title">Dateien übertragen</h2><p id="v4-drag-context"></p><p>Welche Aktion soll durchgeführt werden? Die Konfliktregeln werden danach angezeigt.</p><div class="dialog-actions"><button type="button" id="v4-drag-copy">Kopieren</button><button type="button" id="v4-drag-move">Verschieben</button><button type="button" id="v4-drag-cancel" class="secondary">Abbrechen</button></div></dialog>
<dialog id="file-preview-dialog" aria-labelledby="file-preview-title" class="v4-preview-dialog"><div class="dialog-heading"><h2 id="file-preview-title">Dateivorschau</h2><button type="button" onclick="document.getElementById('file-preview-dialog').close()">✕</button></div><div id="file-preview-content"></div><p class="transfer-note">Text maximal 128 KiB; PNG/JPEG/GIF/WebP maximal 2 MiB. Andere Dateien werden nur mit Metadaten angezeigt.</p><div class="dialog-actions"><button type="button" onclick="document.getElementById('file-preview-dialog').close()">Schließen</button></div></dialog>
<dialog id="trash-dialog" aria-label="Papierkorb"><h2>Papierkorb und Wiederherstellung</h2><p>Es werden keine vorhandenen Dateien überschrieben. Für die Wiederherstellung muss der übergeordnete Ordner noch existieren.</p><div id="trash-rows"></div><div class="dialog-actions"><button type="button" onclick="document.getElementById('trash-dialog').close()">Schließen</button></div></dialog>
<dialog id="audit-dialog" aria-label="Aktionsprotokoll"><h2>Aktionsprotokoll</h2><pre id="audit-rows" class="audit-rows"></pre><div class="dialog-actions"><button type="button" onclick="document.getElementById('audit-dialog').close()">Schließen</button></div></dialog>
<dialog id="duplicate-dialog" aria-labelledby="duplicate-title" class="duplicate-dialog">
 <div class="dialog-heading"><h2 id="duplicate-title">Intelligente Dublettensuche – V3</h2><button id="duplicate-close" type="button">✕</button></div>
 <p>Die Kriterien werden gemeinsam angewendet (UND). Nur Treffer in beiden Panels. Bestehende Dateifilter und Unterordneroptionen gelten weiter.</p>
 <div class="duplicate-options"><label><input type="checkbox" id="dup-name" checked> Dateiname</label><label><input type="checkbox" id="dup-size"> Dateigröße</label><label><input type="checkbox" id="dup-modified"> Änderungsdatum (sekundengenau)</label><label><input type="checkbox" id="dup-sha256"> Identischer Inhalt (SHA-256)</label></div>
 <div class="dialog-actions"><button id="duplicate-scan" type="button">Suchen</button><label>Behalten: <select id="duplicate-keep"><option value="left">Links</option><option value="right">Rechts</option></select></label><button type="button" id="duplicate-mark" disabled>Andere Treffer markieren</button><button id="duplicate-trash" type="button" disabled>Markierte Treffer in Papierkorb …</button></div>
 <div id="duplicate-summary" aria-live="polite"></div><div id="duplicate-results" class="duplicate-results"></div>
 <div class="dialog-actions"><button type="button" id="duplicate-cancel">Schließen</button></div>
 </dialog>
 <dialog id="batch-rename-dialog" aria-label="Reihenumbenennung mit Muster"></dialog></main>
<script src="assets/javascript/nojquery.min.js"></script>
<script src="assets/javascript/classes/Files.js?v=20261105-transfer-1"></script>
<script src="assets/javascript/classes/BatchRename.js"></script>
<script src="assets/javascript/classes/FilesHelp.js?v=20261105-reviewed"></script>
<script>window.FILES_RENAME_CSRF = <?= json_encode($_SESSION['files_batch_rename_csrf'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script>nj.ready(()=>{const app=new Files();const status=nj('#status').e;
const run=async(fn)=>{try{status.textContent='Lade …';await fn();if(status.textContent==='Lade …') status.textContent='Bereit';}catch(e){status.textContent='Fehler: '+e.message;console.error('Files-Aktion fehlgeschlagen',e);if(e.message && /Papierkorb|Löschen|Verschieben fehlgeschlagen|Schreibrechte/i.test(e.message))window.alert('Files – Löschfehler: '+e.message);}};
const batchRenamer=new FilesBatchRename({
  mount:document.getElementById('batch-rename-dialog'),
  endpoint:'assets/php/api.php',
  csrfToken:window.FILES_RENAME_CSRF,
  getSelectedPaths:()=>app.getSelectedFiles(batchRenamer.opt.panel).map(file=>file.relativePath),
  getContext:()=>({drive:app.currentDrives[batchRenamer.opt.panel],folder:app.currentFolders[batchRenamer.opt.panel],recursive:app.recursive[batchRenamer.opt.panel]}),
  onRenamed:async result=>{
    await Promise.all(['left','right'].map(side=>app.refreshSide(side)));
    status.textContent=result.changed+' Datei(en) per Muster umbenannt';
  }
});
for(const side of ['left','right']) {
  nj('#back-'+side).e.addEventListener('click',()=>run(()=>app.navigateHistory(side,-1)));
  nj('#forward-'+side).e.addEventListener('click',()=>run(()=>app.navigateHistory(side,1)));
  nj('#star-'+side).e.addEventListener('click',()=>app.toggleFavorite(side));
  nj('#favorite-'+side).e.addEventListener('change',e=>run(async()=>{await app.openFavorite(side,e.target.value);e.target.value='';}));
  nj('#preview-'+side).e.addEventListener('click',()=>run(()=>app.previewSelected(side)));
  nj('#columns-'+side).e.addEventListener('click',()=>app.showColumnDialog(side));
  nj('#batch-rename-'+side).e.addEventListener('click',()=>batchRenamer.open(side));
  nj('#remove-name-'+side).e.addEventListener('click',()=>run(()=>app.removeNameText(side,nj('#remove-name-input-'+side).e.value)));
  nj('#drive-'+side).e.addEventListener('change',e=>run(()=>app.selectDrive(side,e.target.value)));
  nj('#folder-'+side).e.addEventListener('click',()=>run(()=>app.showFolderDialog(side)));
  nj('#invert-filter-'+side).e.addEventListener('change',e=>run(()=>app.setInvertPanelFilter(side,e.target.checked)));
  nj('#recursive-'+side).e.addEventListener('change',e=>run(()=>app.setRecursive(side,e.target.checked)));
  const filter=nj('#file-filter-'+side).e;
  let debounce;
  const applyFilter=()=>{clearTimeout(debounce);return run(()=>app.setPanelFilter(side,filter.value));};
  filter.addEventListener('input',()=>{clearTimeout(debounce);debounce=setTimeout(applyFilter,250);});
  filter.addEventListener('change',applyFilter);
  filter.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();applyFilter();}});
  nj('#apply-filter-'+side).e.addEventListener('click',applyFilter);
}
nj('#trash-open').e.addEventListener('click',()=>run(()=>app.showTrashDialog()));
nj('#trash-undo').e.addEventListener('click',()=>run(()=>app.undoLastTrash()));
nj('#audit-open').e.addEventListener('click',()=>run(()=>app.showAudit()));
for(const side of ['left','right']){
 nj('#v5-profile-'+side).e.addEventListener('click',()=>run(()=>app.v5ProfileDialog(side)));
 nj('#v5-stats-'+side).e.addEventListener('click',()=>run(()=>app.v5Statistics(side)));
}
nj('#v5-zip-create').e.addEventListener('click',()=>run(()=>app.v5ZipCreate(nj('#action-source').e.value)));
nj('#v5-zip-extract').e.addEventListener('click',()=>run(()=>app.v5ZipExtract(nj('#action-source').e.value)));
nj('#v5-export-csv').e.addEventListener('click',()=>run(()=>app.v5Export(nj('#action-source').e.value,'csv')));
nj('#v5-export-json').e.addEventListener('click',()=>run(()=>app.v5Export(nj('#action-source').e.value,'json')));
nj('#v5-dialog-close').e.addEventListener('click',()=>nj('#v5-dialog').e.close());

for(const side of ['left','right']) nj('#create-folder-'+side).e.addEventListener('click',()=>run(()=>app.createFolder(side)));
for(const action of ['copy','move','rename','delete']) nj('#action-'+action).e.addEventListener('click',()=>run(()=>{
  let side=nj('#action-source').e.value;
  if(action==='delete') {
    const left=app.getSelectedEntries('left').length;
    const right=app.getSelectedEntries('right').length;
    // The source dropdown is authoritative when both panels have selections.
    // Only auto-detect a source when exactly one panel has marked entries.
    if(left && !right) side='left';
    else if(right && !left) side='right';
    nj('#action-source').e.value=side;
  }
  return app.performAction(action,side);
}));
nj('#column-close').e.addEventListener('click',()=>app.closeColumnDialog());
nj('#column-cancel').e.addEventListener('click',()=>app.closeColumnDialog());
nj('#column-save').e.addEventListener('click',()=>run(()=>app.applyColumnDialog()));
nj('#column-dialog').e.addEventListener('close',()=>{app.columnDialogSide=null;});
nj('#folder-close').e.addEventListener('click',()=>app.closeFolderDialog());
nj('#folder-cancel').e.addEventListener('click',()=>app.closeFolderDialog());
nj('#folder-confirm').e.addEventListener('click',()=>run(()=>app.confirmFolderDialog()));
nj('#filter').e.addEventListener('click',()=>run(()=>app.refresh({name:nj('#name').e.value,extension:nj('#extension').e.value})));
nj('#duplicates-advanced').e.addEventListener('click',()=>app.openDuplicateDialog());
nj('#duplicates').e.addEventListener('click',()=>run(async()=>{const result=await app.showDuplicates();status.textContent=result.names+' übereinstimmende Dateinamen – links '+result.left+', rechts '+result.right+' Dateien ausgewählt';}));
document.addEventListener('keydown',event=>{
  if(event.ctrlKey||event.altKey||event.metaKey||event.target.closest('input,textarea,select,dialog'))return;
  const side=nj('#action-source').e.value;
  if(event.key==='F3'){event.preventDefault();run(()=>app.previewSelected(side));}
  if(event.key==='F5'){event.preventDefault();run(()=>app.refreshSide(side));}
  if(event.key==='Backspace'){event.preventDefault();const f=app.currentFolders[side];if(f)run(()=>app.openFolder(side,f.includes('/')?f.slice(0,f.lastIndexOf('/')):''));}
});
run(()=>app.initialize());});</script></body></html>
