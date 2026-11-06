# JavaScript Files API – Version 4.0

`Files.js` stellt `window.Files` bereit. Benötigt noJquery 4 (`nj`) für DOM-Initialisierung/Selektion und die native Fetch-API.

| Methode/Eigenschaft | Parameter | Rückgabe | Bedeutung |
|---|---|---|---|
| `new Files(endpoint)` | API-URL (optional) | Instanz | Controller erzeugen |
| `initialize()` | – | Promise<void> | Spaltenkonfiguration laden und beide Panels initialisieren |
| `request(action,params)` | Aktion, Objekt | Promise<data> | JSON-Antwort auswerten |
| `folders(side,folder)` | `left`/`right`, relativer Ordner | Promise<object> | Ordnerbaum für Auswahl |
| `showFolderDialog(side)` | `left` / `right` | Promise<void> | Modalen Ordnerauswahldialog öffnen |
| `browseFolder(folder)` | relativer Ordner | Promise<void> | Unterverzeichnisse im Dialog anzeigen, ohne Panel zu verändern |
| `confirmFolderDialog()` | – | Promise<void> | Auswahl bestätigen und Panel neu laden |
| `closeFolderDialog()` | – | void | Dialog ohne Übernahme schließen |
| `openFolder(side,folder)` | Seite, relativer Ordner | Promise<void> | Panel-Ordner wechseln und Dateien laden |
| `list(side,filters)` | Seite, `{name,extension}` | Promise<file[]> | Ein Panel laden |
| `refreshSide(side)` | Seite | Promise<file[]> | Eine Tabelle aktualisieren |
| `refresh(filters)` | optionales Filterobjekt | Promise<void> | Beide Tabellen aktualisieren |
| `duplicates()` | – | Promise<pair[]> | Dubletten für aktive Ordner laden |
| `showDuplicates()` | – | Promise<pair[]> | Dubletten in Panels gegenüberstellen |
| `render(side,items)` | Seite, Dateiliste | void | HTML-Tabelle erzeugen |
| `renderColumnControls()` | – | void | Spalten-Checkboxen erzeugen |
| `format(key,value)` | Feld, Wert | string | Tabellenwert lesbar formatieren |
| `currentFolders` | `{left,right}` | Objekt | Aktuell ausgewählte relative Ordner; bewusst getrennt von der Methode `folders()` |
| `columns` | `string[]` | Array | Angezeigte Spalten / Hashanforderung |
| `recursive` | bool | bool | Unterordner mit einbeziehen |
| `filters` | `{name,extension}` | Objekt | Filterstatus |
| `selected` | `{left,right}` | Objekt | Aktuell markierte Datei |
| `rows` | `{left,right}` | Objekt | Aktuelle Dateireihen je Panel |

## Beispiel

```javascript
nj.ready(async () => {
  const files = new Files();
  await files.initialize();
  await files.openFolder('left', 'Berichte');
  files.columns = ['name','size','modified'];
  await files.refresh();
});
```

Jede Änderung an öffentlichen Methoden oder Feldern ist zeitgleich in dieser `API.md` zu dokumentieren.

## Auswahl-Dialog (Version 4.0)

Klick auf die echten Schaltflächen `#folder-left` oder `#folder-right` öffnet `#folder-dialog`. Der Dialog navigiert **nur innerhalb des zugehörigen serverseitigen Stammverzeichnisses**. Durch „Diesen Ordner auswählen“ wird `openFolder(side, folder)` ausgeführt und die Tabelle des gewählten Panels aktualisiert. „Abbrechen“ lässt dessen bisherigen Ordner unverändert. Ein nativer Windows-Ordnerdialog oder Zugriff auf beliebige lokale PC-Pfade wird damit bewusst nicht behauptet.

```javascript
await app.showFolderDialog('left');
// Im Dialog klicken; Bestätigung ruft app.confirmFolderDialog() auf.
```

## Fehlerbehebung Version 4.0

Die Instanzeigenschaft `folders` wurde in `currentFolders` umbenannt, damit sie die API-Methode `folders(side,folder)` nicht verdeckt. `#folder-left` und `#folder-right` sind jetzt echte Buttons mit `click`-Handler statt einwertigen `<select>`-Elementen. Nach Bestätigung wird ausschließlich das ausgewählte Panel durch `openFolder()` und `refreshSide()` neu geladen. Die Filter und die Rekursiv-Einstellung bleiben erhalten.

## Version 5.0 – Laufwerksauswahl

- `availableDrives`: durch `config.drives` gelieferte freigegebene Serverlaufwerke.
- `currentDrives`: Laufwerks-ID je Panel (`left` / `right`).
- `selectDrive(side, drive): Promise<void>`: prüft erlaubte ID, setzt den Panelordner auf `''` (Wurzel) und lädt die Dateiliste neu.
- `folders(side, folder)` / `list(side, filters)` / `duplicates()`: übergeben die ausgewählten Laufwerks-IDs an die PHP-API.
- HTML-Elemente `#drive-left` / `#drive-right`: Laufwerk-Selects über `#folder-left` / `#folder-right`.

**Beispiel:** `await app.selectDrive('left', 'root')` zeigt auf Unix die direkt in `/` enthaltenen Dateien. Mit aktivierter rekursiver Option werden Dateien aus Unterverzeichnissen bis zur serverseitigen Ergebnisgrenze berücksichtigt. Serverlaufwerke sind nicht automatisch die lokalen Laufwerke des Besuchers.

## Version 6.0 – Panel-spezifische Wildcard-Filter

**Eigenschaft:** `panelPatterns: {left: string, right: string}` (Anfangswert jeweils `'*'`).

**Methode:** `async setPanelFilter(side: 'left'|'right', pattern: string): Promise<Array<FileRecord>>`

Legt den Filter für genau ein Panel fest und lädt dessen aktuelle Dateiliste neu. Ein leeres Muster wird zu `*`. Beispiele: `await app.setPanelFilter('left','*.md')` und `await app.setPanelFilter('right','bericht_*.pdf')`. Bei unbekanntem Panel wird ein Fehler ausgelöst; API-Fehler werden weitergegeben. `list(side)` überträgt `pattern` an die PHP-API. `refreshSide(side)` verwirft verspätete Antworten älterer Filteranfragen, damit diese neuere Ergebnisse nicht überschreiben.

Die HTML-Eingaben `#file-filter-left` und `#file-filter-right` befinden sich **unter** der jeweiligen Ordnerauswahl. Bei Eingaben erfolgt nach 250 ms ohne weitere Eingabe ein Reload; Enter lädt sofort. Die Verwendung des Filters ist unabhängig von der Laufwerks- und Ordnerauswahl. Die Wildcard-Interpretation erfolgt serverseitig in `Files::list()`; `*` steht für beliebig viele, `?` für genau ein Zeichen (Groß-/Kleinschreibung wird ignoriert). Das Anzeigen von Dubletten nutzt weiterhin den vollständigen Ordnervergleich ohne diese beiden Filter.

## Version 7.0 – Mehrfachauswahl und „Alle auswählen“

Jedes Tabellen-Row hat links eine Checkbox (`.file-select`). Zusätzlich besitzt jede Tabelle in der Kopfzeile eine Checkbox (`#select-all-left` / `#select-all-right`) zum Markieren bzw. Abwählen **aller aktuell angezeigten Dateien** des jeweiligen Panels. Der Zähler steht in `#selected-count-left` bzw. `#selected-count-right`. Klick auf die Tabellenzeile schaltet ihre Markierung um.

- `selectedPaths: {left: Set<string>, right: Set<string>}`: ausgewählte relative Dateipfade je Panel.
- `getSelectedFiles(side): FileRecord[]`: selektierte Records aus der momentan angezeigten Tabelle (Fehler bei ungültigem Panel).
- `selectAll(side, checked=true): void`: alle aktuell angezeigten Dateien setzen bzw. entfernen; aktualisiert Zeilen, Checkboxen und Zähler.
- `clearSelection(side): void`: Selektion des Panels löschen.
- `updateSelectionState(side): void`: Header-Checkbox (`checked`, `indeterminate`, `disabled`) und Zähler synchronisieren.
- `assertSide(side): void`: Panelkennung validieren.
- `render(side, items)`: erzeugt die Checkboxspalte, erhält Selektionen für weiterhin sichtbare Dateien, entfernt solche für ausgefilterte Dateien.

**Auswahlregeln:** Die Panels bleiben strikt getrennt. Ein Ordner- oder Laufwerkswechsel verwirft die Auswahl des betreffenden Panels. Die Auswahl beim bloßen Neuladen bleibt für weiterhin sichtbare Pfade erhalten; ausgefilterte Dateien werden abgewählt. „Alle auswählen“ wirkt ausschließlich auf die angezeigten Zeilen (maximale Ergebnisanzahl der API). Die Auswahl ist nur clientseitig und löst keine Dateiaktion aus.

```javascript
app.selectAll('left', true);
const links = app.getSelectedFiles('left');
app.selectAll('right', false);
```

## Version 8.0 – Dateiaktionsleiste

- `mutate(action, payload): Promise<Array>`: sendet eine Dateiaktion als JSON-POST an PHP.
- `performAction(action, side): Promise<void>`: ermittelt die angekreuzten Zeilen des Quellpanels, fordert bei Umbenennen einen neuen Namen an, verlangt eine Bestätigung und aktualisiert beide Panels nach der Serveraktion. Unterstützte Werte: `copy`, `move`, `rename`, `delete`. Beim Kopieren/Verschieben ist der aktuelle Ordner des jeweils anderen Panels das Ziel.
- `updateActionSelection(): void`: aktualisiert den gemeinsamen Auswahlzähler unter den Panels.

Die Tabellen beanspruchen per CSS den verbleibenden Viewport-Bereich (`.panels {flex:1;min-height:0}`), während `.table-wrap` im jeweiligen Panel scrollbar bleibt. Die Aktionsleiste `.actionbar` bleibt darunter sichtbar.


## Version 9.0 – Spaltenauswahl pro Panel

Die Spaltenauswahl wird über die klickbare Panel-Kopfzeile oder eine Tabellen-Spaltenüberschrift geöffnet.

- `panelColumns: {left: string[], right: string[]}` – unabhängig konfigurierte Spaltenlisten.
- `showColumnDialog(side: 'left'|'right'): void` – öffnet die Auswahl mit den aktuell aktivierten Feldern.
- `applyColumnDialog(): Promise<void>` – prüft mindestens ein ausgewähltes Feld, übernimmt die Spalten und lädt nur das betroffene Panel neu.
- `closeColumnDialog(): void` – verwirft nicht bestätigte Änderungen.
- `setPanelColumns(side, columns: string[]): Promise<void>` – programmatische Konfiguration mit Validierung.

Die Auswahllisten werden unter `files.columns.left` und `files.columns.right` im Browser-`localStorage` gespeichert, sofern verfügbar. Nicht mehr unterstützte Feldnamen werden beim Initialisieren entfernt. Die Checkbox zur Dateiauswahl bleibt immer sichtbar und ist keine konfigurierte Datenspalte. Verfügbare Felder liefert die PHP-API `config.fields`.

Beispiel: `await app.setPanelColumns('left', ['name','size','modified']);`

## Sortierung der Paneltabellen – Drei Zustände

Ein Klick auf **einen Dateispaltenkopf** wechselt unabhängig je Panel durch **aufsteigend (▲) → absteigend (▼) → ohne Sortierung (↕)**. Bei Wechsel auf eine andere Spalte beginnt diese aufsteigend. Die unsortierte Ansicht verwendet exakt die zuletzt vom Server gelieferte Ausgangsreihenfolge. Größen und Zeitangaben werden numerisch sortiert, Dateinamen und andere Textwerte mit natürlicher deutscher Sortierung (z. B. `Datei2` vor `Datei10`). Fehlende Werte stehen am Ende.

- `sortState`: pro Panel `{key, direction}`; `direction` ist `asc`, `desc` oder `null`.
- `originalRows`: ursprüngliche API-Reihenfolge je Panel.
- `sortPanel(side,key)`: nächster Sortierzustand und Tabellenneudarstellung. Beispiel: `app.sortPanel('left','size')`.
- `sortedRows(side,items)`: liefert sortierte Kopie ohne Änderung der Originaldaten.
- `render(side,items,saveOriginal=true)`: bewahrt bei regulärem Datenladen die Originalreihenfolge, beim Umsortieren mit `false` bleibt sie erhalten.

Die Sortierung bleibt beim Aktualisieren/Filtern erhalten und ändert weder die ausgewählten Dateien noch den Dublettenvergleich. Der Dialog zur **Spaltenauswahl** bleibt über die Überschrift »Panel links/rechts ⚙« erreichbar.


### Filter umkehren (beide Panels unabhängig)
Das Kontrollkästchen **Filter umkehren** unter dem Dateifilter kehrt ausschließlich den Dateinamen-Platzhalterfilter im jeweiligen Panel um. Bei `*.md` werden ohne Häkchen alle `.md`-Dateien angezeigt, mit Häkchen alle Dateien außer `.md`. Bei `*` und aktivierter Umkehrung werden keine Dateien angezeigt. Globale Namens-/Endungsfilter, Verzeichniswahl und Rekursionsmodus bleiben unverändert; die Invertierung wird bereits beim serverseitigen Listing angewandt (maximal 5000 Ergebnisdateien). Sortierung und Auswahl der sichtbaren Dateien bleiben funktionsfähig.

### Spaltenauswahl: eigener Button

`#columns-left` und `#columns-right` sind die expliziten Schaltflächen „⚙ Spalten auswählen“ in den Panelköpfen und rufen `showColumnDialog(side)` auf. Ein Klick auf den Tabellenspaltenkopf sortiert ausschließlich.
