# Frontend-API: FilesBatchRename

Klasse: `window.FilesBatchRename`, Datei `BatchRename.js`; ohne jQuery, verwendbar neben noJquery.

## Konstruktor

```javascript
const batch = new FilesBatchRename({
  mount: document.querySelector('#panel_left .actions'),
  panel: 'left',
  endpoint: 'assets/php/batch-rename-api.php',
  csrfToken: window.FILES_RENAME_CSRF,
  getSelectedPaths: () => files.getSelectedFiles('left').map(file => file.relativePath),
  onRenamed: result => files.loadPanel('left')
});
```

`mount`: Elterncontainer für den Dialog. `getSelectedPaths()`: liefert aktuelle ausgewählte Dateipfade relativ zur **serverseitigen Root**, in Nummerierungsreihenfolge. `onRenamed(result)`: Callback zum Panel-Refresh.

## `open()`

Öffnet den Dialog und erzeugt automatisch eine Vorschau aus der aktuellen Auswahl.

```javascript
batch.open();
```

## `close()`

Schließt den Dialog.

```javascript
batch.close();
```

## `invalidate()`

Verwirft die letzte Vorschau und sperrt die Ausführung, wenn sich Daten oder Auswahl geändert haben.

```javascript
batch.invalidate();
```

## `preview()` und `apply()`

Asynchrone Methoden, die über den HTTP-Endpunkt planen bzw. ausführen.

```javascript
await batch.preview();
await batch.apply();
```

## Muster

| Muster | Beispiel bei `Datei.txt`, Nummer 12 |
|---|---|
| `{name}_{n}` | `Datei_12.txt` |
| `Scan_{n:4}` | `Scan_0012.txt` |
| `{date}_{name}` | `2026-11-01_Datei.txt` *(abhängig vom Serverdatum)* |

Die Endung wird unverändert angehängt. Die Vorschau ist verbindlich und entspricht der an den Server übergebenen Anzeigereihenfolge. Eine geänderte Auswahl erfordert eine neue Vorschau.

## Anbindung im integrierten Files-Projekt

Die Seite `index.php` erstellt **eine** Instanz `FilesBatchRename` im Dialog
`#batch-rename-dialog`. `open('left')` beziehungsweise `open('right')` legt das
aktive Panel fest. `getSelectedPaths` benutzt die sichtbare, sortierte Auswahl von
`Files.getSelectedFiles(side)`; `getContext` liefert aktuelle Drive-ID, Ordner und
Unterordner-Option. Endpunkt ist `assets/php/api.php`; die Antwort trägt Daten
unter `data`. Nach erfolgreicher Ausführung werden beide Panels aktualisiert.

Beispiel: `batchRenamer.open('left')`
