/** Contextual, accessible, local help for Files. No server calls. */
(function () {
  'use strict';
  const topics = {
    overview: ['Hilfe zum Files-Manager', '<p>Wähle links und rechts Laufwerk und Ordner, filtere bei Bedarf die Dateien und markiere die gewünschten Einträge über die Kontrollkästchen. Im unteren Aktionsbereich kannst du anschließend kopieren, verschieben, umbenennen oder löschen.</p><p>Beide Panels haben eigene Filter, Spalten und Sortierungen. Aktionen auf Dateien ändern Daten auf dem PHP-Server. Kontrolliere die Auswahl und bestätige Aktionen erst nach Prüfung.</p>'],
    globalFilter: ['Dateien laden und globale Filter', '<p>Die Felder <strong>Dateiname</strong> und <strong>Endung</strong> oben dienen als zusätzliche Suchkriterien beim Laden. Der Dateiname kann eine Teilzeichenfolge enthalten. Für einen einzelnen Ordner verwende besser den jeweiligen Dateifilter im Panel.</p><p>Mit <strong>Dateien laden</strong> aktualisierst du die Ansicht.</p>'],
    duplicates: ['Dubletten finden', '<p>Vergleicht die aktuell geladenen Dateien in beiden Panels anhand ihres <strong>vollständigen Dateinamens einschließlich Endung</strong>. Übereinstimmungen werden in beiden Panels markiert.</p><p>Das ist <strong>kein Inhaltsvergleich</strong>: Gleichnamige Dateien können unterschiedliche Inhalte haben. Die Suche verändert oder löscht keine Dateien; die Markierung kann anschließend für Dateiaktionen verwendet werden.</p>'],
    drives: ['Laufwerk wählen', '<p>Wähle das Laufwerk auf dem Computer, auf dem PHP ausgeführt wird. Wenn verfügbar, steht hinter dem Laufwerksbuchstaben die Datenträgerbezeichnung, etwa <strong>D: (Archiv)</strong>.</p><p>Nach dem Wechsel werden Ordner und Dateiansicht des betreffenden Panels angepasst.</p>'],
    folders: ['Ordner wählen', '<p>Öffnet den Ordnerdialog für das ausgewählte Laufwerk. Navigiere zum gewünschten Ordner und bestätige mit <strong>Diesen Ordner auswählen</strong>. Links und rechts können unterschiedliche Ordner verwendet werden.</p><p>Es werden Ordner des PHP-Servers angezeigt, nicht zwingend die Ordner deines lokalen Browser-Rechners.</p>'],
    filter: ['Dateifilter und Platzhalter', '<p>Gib pro Panel einen Filter ein, beispielsweise <code>*.mp3</code> für MP3-Dateien oder <code>bericht*</code> für Namen, die mit „bericht“ beginnen. <code>*</code> steht für beliebig viele Zeichen, <code>?</code> für genau ein Zeichen.</p><p>Die Eingabe <code>*</code> zeigt alle passenden Dateien. Die Filterung lässt sich mit <strong>Anwenden</strong> auslösen und wird auch beim Tippen aktualisiert.</p>'],
    invert: ['Filter umkehren', '<p>Zeigt statt der zum Dateifilter passenden Dateien die <strong>nicht passenden</strong> Dateien an. Beispiel: Mit <code>*.pdf</code> und aktivierter Umkehrung werden keine PDF-Dateien angezeigt.</p><p>Die Option wirkt ausschließlich im jeweiligen Panel und lässt den eingegebenen Filter unverändert.</p>'],
    recursive: ['Unterordner einbeziehen', '<p>Bei Aktivierung werden auch Dateien in Unterordnern des ausgewählten Ordners gesucht und angezeigt. Filter und Dublettensuche berücksichtigen diese Dateien.</p><p>Beachte bei Dateiaktionen den <strong>relativen Pfad</strong> der ausgewählten Datei. Bei Kopieren und Verschieben werden Dateien aus Unterordnern in der aktuellen Version direkt im gewählten Zielordner abgelegt, nicht in derselben Unterordnerstruktur.</p><p>Bei sehr großen Verzeichnisbäumen kann der Ladevorgang länger dauern. Die Anzeige ist auf 5.000 Dateien pro Panel begrenzt.</p>'],
    columns: ['Spalten auswählen', '<p>Mit <strong>Spalten auswählen</strong> legst du unabhängig für jedes Panel fest, welche Dateieigenschaften angezeigt werden. Mindestens eine Datenspalte muss ausgewählt bleiben.</p><p>Die ausgewählten Spalten werden für dieses Panel im Browser gespeichert. Die Dateidaten selbst ändern sich dadurch nicht.</p>'],
    sorting: ['Spalten sortieren', '<p>Klicke in der Dateitabelle auf den <strong>Text eines Spaltenkopfs</strong>. Die Reihenfolge wechselt zwischen <strong>aufsteigend → absteigend → ohne Sortierung</strong>.</p><p>Die Spaltenauswahl erfolgt über den separaten Button „Spalten auswählen“. Bereits markierte Dateien bleiben markiert.</p>'],
    selection: ['Dateien auswählen', '<p>Markiere Dateien mit dem Kontrollkästchen in der jeweiligen Tabellenzeile. Über das Kästchen in der Tabellenüberschrift kannst du alle angezeigten Dateien markieren oder die Markierung aufheben.</p><p>Auch Ordner lassen sich markieren; bei einer Papierkorbaktion zählen ihre Unterordner mit. Das gewählte <strong>Quellpanel</strong> entscheidet, welche Markierung verarbeitet wird. Sind nur auf einer Seite Einträge ausgewählt, wird diese Seite automatisch verwendet. Eine Markierung im anderen Panel wird nicht gelöscht.</p>'],
    removeName: ['Text aus Dateinamen löschen', '<p>Gib den Text in das Eingabefeld oben im gewünschten Panel ein, markiere die betroffenen Dateien und klicke auf <strong>Aus Dateinamen löschen</strong>.</p><p>Der eingegebene Text wird aus den Dateinamen der markierten Dateien entfernt; die <strong>Dateien selbst werden nicht gelöscht</strong>. Kontrolliere die Vorschau vor der Bestätigung. Ungültige oder bereits belegte Zielnamen werden abgewiesen.</p>'],
    batchRename: ['Reihenumbenennung mit Muster', '<p>Ermöglicht die Umbenennung mehrerer ausgewählter Dateien anhand eines Musters. Die Funktion arbeitet nur mit den markierten Dateien des betreffenden Panels.</p><p>Prüfe die Vorschau sorgfältig und bestätige erst dann. Die Umbenennung verändert die Dateinamen auf dem Datenträger.</p>'],
    actions: ['Dateiaktionen und Quellpanel', '<p>Wähle zuerst das <strong>Quellpanel</strong> (Links oder Rechts) und markiere dort Dateien. <strong>Kopieren</strong> erhält die Quelle; <strong>Verschieben</strong> überträgt die Datei und entfernt die Quelle nur bei erfolgreicher Übertragung.</p><p><strong>Umbenennen</strong> ändert einen markierten Datei- oder Ordnernamen. <strong>In Papierkorb</strong> verschiebt nur die Einträge des ausgewählten Quellpanels in den privaten Papierkorb; sie lassen sich später wiederherstellen, wenn der ursprüngliche Name frei ist. Auch markierte Ordner einschließlich Inhalt sind betroffen. Vorher erscheint eine Zusammenfassung, bei Ordnern zusätzlich eine Bestätigung. Bei Konflikten während Kopieren/Verschieben erscheint ein Regel- und Vorschaudialog.</p>'],
    transfer: ['Kopieren/Verschieben: Konfliktregeln', '<p>Der Dialog zeigt die geplanten Dateiaktionen. Prüfe die Vorschau vor <strong>Aktion ausführen</strong>. Eine Regel entscheidet bei schon vorhandenem Zieldateinamen:</p><ul><li><strong>Quelle ersetzt Ziel:</strong> Zieldatei wird durch Quelle ersetzt.</li><li><strong>Neuere/ältere Datei behalten:</strong> vergleicht das Änderungsdatum.</li><li><strong>Größere/kleinere Datei behalten:</strong> vergleicht die Dateigröße.</li><li><strong>Ziel behalten:</strong> überspringt vorhandene Zieldateien.</li><li><strong>Quelle mit neuem Namen behalten:</strong> erzeugt einen freien Namen.</li><li><strong>Identische Inhalte überspringen:</strong> vergleicht SHA-256; verschiedene Inhalte werden ersetzt.</li></ul><p><strong>Bei Gleichstand bleibt das Ziel.</strong> Beim Verschieben werden übersprungene Quellen nicht gelöscht. Die gewählte Regel kann bestehende Dateien unwiderruflich ersetzen.</p>'],
    folderDialog: ['Ordnerdialog bedienen', '<p>Benutze die Verzeichnisliste zur Navigation. Die Zeile <strong>..</strong> wechselt in den übergeordneten Ordner. Dateien werden zur Orientierung angezeigt, können aber im Ordnerdialog nicht ausgewählt werden. Mit <strong>Diesen Ordner auswählen</strong> übernimmst du einen nichtleeren Ordner, mit <strong>Abbrechen</strong> verwirfst du die Auswahl.</p>'],
    trash: ['Papierkorb und Wiederherstellung', '<p>Die Aktion <strong>In Papierkorb</strong> verschiebt ausschließlich die markierten Einträge des gewählten Quellpanels. Im Papierkorb findest du Datum, Laufwerk und Originalpfad. Bei <strong>Wiederherstellen</strong> darf der Zielname noch nicht existieren. Das Quellverzeichnis muss erhalten sein.</p><p>Der private Papierkorb benötigt einen beschreibbaren Speicherort außerhalb des Webverzeichnisses. Er wird nicht automatisch geleert.</p>'],
    undo: ['Letztes Löschen rückgängig', '<p>Stellt den zuletzt im Papierkorb gespeicherten Eintrag wieder her. Bei mehreren gelöschten Einträgen werden sie einzeln wiederhergestellt. Existiert am Ursprungsort bereits ein gleichnamiger Eintrag, wird dieser nicht überschrieben.</p>'],
    audit: ['Aktionsprotokoll', '<p>Zeigt gespeicherte Dateiaktionen an, darunter Verschieben in den Papierkorb und Wiederherstellen. Die Einträge sind eine technische Hilfe bei der Kontrolle ausgeführter Aktionen.</p>'],
    zip: ['ZIP-Archive', '<p><strong>ZIP erstellen</strong> archiviert die im Quellpanel markierten Dateien oder Ordner. <strong>ZIP entpacken</strong> benötigt genau eine markierte ZIP-Datei. Vorhandene Zieldateien werden nicht überschrieben. Die PHP-Erweiterung <code>zip</code> muss auf dem Webserver aktiviert sein.</p>'],
    export: ['CSV- und JSON-Export', '<p>Exportiert die derzeit angezeigten und gefilterten Zeilen des gewählten Quellpanels. Eine CSV-Datei verwendet Semikolons als Spaltentrenner. Der Export verändert keine Dateien auf dem Server.</p>'],
    profiles: ['Filterprofile', '<p>Speichert Filtermuster, Umkehrung und Rekursion sowie die globalen Namens-/Endungsfilter im jeweiligen Browser. Gespeicherte Profile stehen nur in diesem Browser zur Verfügung und lassen sich laden oder löschen.</p>'],
    stats: ['Verzeichnisstatistik', '<p>Zeigt Anzahl, Gesamtgröße und häufige Dateiendungen des aktuellen Panelordners an. Mit <strong>Unterordner einbeziehen</strong> werden Unterverzeichnisse berücksichtigt.</p>'],
    navigation: ['Zurück / Vorwärts', '<p>Blättert durch bereits in diesem Panel besuchte Ordner. Das Verzeichnis <strong>..</strong> in der Dateiliste öffnet per Doppelklick den übergeordneten Ordner.</p>'],
    favorites: ['Favoriten', '<p>Der Stern speichert oder entfernt das aktuelle Laufwerk und Verzeichnis. Mit dem Favoritenfeld öffnest du gespeicherte Ordner. Favoriten werden im lokalen Browser gespeichert.</p>'],
    preview: ['Dateivorschau', '<p>Markiere genau eine Datei, dann wähle Vorschau. Text und unterstützte Bilder werden nur innerhalb begrenzter Größen gelesen. Die Vorschau verändert die Datei nicht.</p>'],
    createFolder: ['Ordner erstellen', '<p>Legt im aktuellen Verzeichnis des betreffenden Panels einen neuen Ordner an. Ein vorhandener Name wird niemals überschrieben.</p>'],
    columnDialog: ['Spaltenkonfiguration bedienen', '<p>Aktiviere die gewünschten Eigenschaften per Checkbox. Mit <strong>Übernehmen</strong> aktualisierst du nur die Spalten des Panels, für das du den Dialog geöffnet hast. <strong>Abbrechen</strong> übernimmt keine Änderung.</p>']
  };
  const mounts = [
    ['.toolbar', 'globalFilter', 'append'],
    ['#duplicates', 'duplicates', 'after'],
    ['#drive-left', 'drives', 'after'], ['#drive-right', 'drives', 'after'],
    ['#folder-left', 'folders', 'after'], ['#folder-right', 'folders', 'after'],
    ['#apply-filter-left', 'filter', 'after'], ['#apply-filter-right', 'filter', 'after'],
    ['.panel-filter-invert', 'invert', 'afterAll'],
    ['.panel-recursive', 'recursive', 'afterAll'],
    ['#columns-left', 'columns', 'after'], ['#columns-right', 'columns', 'after'],
    ['#batch-rename-left', 'batchRename', 'after'], ['#batch-rename-right', 'batchRename', 'after'],
    ['#remove-name-left', 'removeName', 'after'], ['#remove-name-right', 'removeName', 'after'],
    ['.actionbar-title', 'actions', 'append'],
    ['#trash-open', 'trash', 'after'], ['#action-delete', 'trash', 'after'],
    ['#trash-undo', 'undo', 'after'], ['#audit-open', 'audit', 'after'],
    ['#v5-zip-create', 'zip', 'after'], ['#v5-zip-extract', 'zip', 'after'],
    ['#v5-export-csv', 'export', 'after'], ['#v5-export-json', 'export', 'after'],
    ['#create-folder-left', 'createFolder', 'after'], ['#create-folder-right', 'createFolder', 'after'],
    ['#preview-left', 'preview', 'after'], ['#preview-right', 'preview', 'after'],
    ['#back-left', 'navigation', 'after'], ['#back-right', 'navigation', 'after'],
    ['#forward-left', 'navigation', 'after'], ['#forward-right', 'navigation', 'after'],
    ['#star-left', 'favorites', 'after'], ['#star-right', 'favorites', 'after'],
    ['#v5-profile-left', 'profiles', 'after'], ['#v5-profile-right', 'profiles', 'after'],
    ['#v5-stats-left', 'stats', 'after'], ['#v5-stats-right', 'stats', 'after'],
    ['#transfer-policy', 'transfer', 'after'],
    ['#folder-dialog .dialog-heading', 'folderDialog', 'append'],
    ['#column-dialog .dialog-heading', 'columnDialog', 'append']
  ];
  function init() {
    const help = document.createElement('dialog');
    help.id = 'files-help-dialog';
    help.className = 'files-help-dialog';
    help.setAttribute('aria-labelledby', 'files-help-title');
    help.innerHTML = '<div class="dialog-heading"><h2 id="files-help-title"></h2><button type="button" class="secondary" id="files-help-close" aria-label="Hilfe schließen">✕</button></div><div id="files-help-body" class="files-help-body"></div><div class="dialog-actions"><button type="button" id="files-help-done">Schließen</button></div>';
    document.body.append(help);
    const close = () => help.close();
    help.querySelector('#files-help-close').addEventListener('click', close);
    help.querySelector('#files-help-done').addEventListener('click', close);
    help.addEventListener('click', event => { if (event.target === help) close(); });
    let lastOpener = null;
    help.addEventListener('close', () => { if (lastOpener && lastOpener.isConnected) lastOpener.focus(); });
    function show(key, opener) {
      const topic = topics[key]; if (!topic) return;
      lastOpener = opener;
      help.querySelector('#files-help-title').textContent = topic[0];
      help.querySelector('#files-help-body').innerHTML = topic[1]; // strictly static built-in help text
      if (!help.open) help.showModal();
    }
    function add(target, topic, position) {
      if (!target) return;
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'files-help-trigger';
      b.textContent = '?'; b.setAttribute('aria-label', 'Hilfe: ' + topics[topic][0]);
      b.title = 'Hilfe: ' + topics[topic][0];
      b.addEventListener('click', event => { event.preventDefault(); event.stopPropagation(); show(topic, b); });
      if (position === 'append') target.append(b); else target.insertAdjacentElement('afterend', b);
    }
    const global = document.querySelector('header h1');
    add(global, 'overview', 'after');
    for (const [selector, topic, placement] of mounts) {
      if (placement === 'afterAll') document.querySelectorAll(selector).forEach(el => add(el, topic, 'after'));
      else add(document.querySelector(selector), topic, placement);
    }
    document.querySelectorAll('.panel-filter-options').forEach(el => add(el, 'sorting', 'append'));
    document.querySelectorAll('.panel .meta').forEach(el => add(el, 'selection', 'append'));
    // Extra help for the transfer preview is available inside the dialog without changing transfer events.
    const transferTitle = document.querySelector('#transfer-title');
    add(transferTitle, 'transfer', 'after');
    // Expose only a safe entrypoint for context help added later by other components.
    window.FilesHelp = { show: topic => show(topic, document.activeElement), topics: Object.keys(topics) };
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();
