# Files v2.0 – Ordnerauswahl und konfigurierbare Dateitabellen

PHP 8.0+ Webserver starten, z. B. im Projektordner `php -S localhost:8000`, dann `http://localhost:8000` öffnen.

Die Panels können unabhängig voneinander einen Ordner unter `storage/left` und `storage/right` auswählen. Ordnerauswahl über Dropdown (Unterordner und Elternordner). In jedem Panel werden Dateieigenschaften tabellarisch angezeigt. Der Konfigurationsbereich schaltet Spalten ein/aus. Dateiname, Endung und rekursiver Scan sind filterbar. Dubletten werden über Dateigröße und SHA-256 ermittelt.

Die serverseitig erlaubten Roots stehen in `assets/php/api.php` im Array `$roots` und können dort auf andere Ordner zeigen. **Keine** ungeschützten Dateisystempfade aus HTTP-Parametern!

API-Dokumentation liegt neben jeder Klasse: `assets/php/classes/API.md` und `assets/javascript/classes/API.md`. Der Zugriff ist read-only.


## Version 3.0 – Ordnerdialoge
Klick auf die Ordner-Auswahlfelder öffnet einen modalen Verzeichnisdialog für das jeweilige Panel. Unterverzeichnisse lassen sich öffnen, „Diesen Ordner auswählen“ bestätigt die Auswahl und lädt die Dateien ausschließlich im entsprechenden Panel. Die Ordner liegen auf dem **PHP-Server** und sind jeweils auf die beiden in `assets/php/api.php` konfigurierten Roots beschränkt. Browser dürfen aus Sicherheitsgründen nicht über PHP beliebige Windows-Ordner auf dem Client lesen. Filter und die Option „Unterordner einbeziehen“ wirken weiterhin auf die Dateianzeige.

## Version 4.0 – funktionierende Ordnerauswahl

Beide Ordnerauswahlfelder sind zugängliche Schaltflächen und öffnen den serverseitigen Ordnerdialog. Die Kollision `this.folders`/`folders()` ist behoben (`currentFolders`). Nach Bestätigung lädt das jeweilige Panel die Dateitabelle. Für einen Funktionstest Dateien und Unterordner in `storage/left` und `storage/right` anlegen. Die Anwendung muss über PHP/HTTP laufen, nicht über `file://`.

## Version 5.0 – Laufwerk über Ordner wählen

Über jeder Ordnerauswahl steht jetzt eine **Laufwerksauswahl**. Beim Wechsel wird der Ordner automatisch auf die Wurzel des ausgewählten Laufwerks gesetzt und die Dateitabelle des zugehörigen Panels neu geladen. Die beiden Panels sind unabhängig.

Auf Windows erkennt PHP vorhandene und lesbare Laufwerksbuchstaben (beispielsweise `C:` und `D:`); auf Linux/Unix ist `/` als Wurzel konfiguriert. **Die Laufwerke gehören zum PHP-Server, nicht zwingend zum PC des Browsernutzers.** In `assets/php/api.php` kann `$drives` als explizite Allowlist konfiguriert werden. In einer öffentlich erreichbaren Webanwendung darf `/` aus Datenschutz- und Sicherheitsgründen nicht pauschal freigegeben werden. Freigegebene Pfade begrenzen, Webanwendung authentifizieren und PHP-Rechte einschränken.

Beim erstmaligen Laden werden nur Dateien direkt aus dem Wurzelordner angezeigt (Option „Unterordner einbeziehen“ standardmäßig aus). Über die Option können Unterverzeichnisse zusätzlich durchsucht werden; die API begrenzt Ergebnisse auf 5000 Dateien.

## Version 6.0 – Dateifilter pro Panel

Unter der Ordnerwahl befindet sich in beiden Panels ein eigenes Dateifilterfeld. `*` zeigt alle Dateien, `*.md` nur Markdown-Dateien, `bericht_*.pdf` entsprechende PDF-Dateien. Zusätzlich wird `?` als Platzhalter für genau ein Zeichen unterstützt. Der Filter berücksichtigt nur Dateinamen und wirkt unabhängig je Panel; die ausgewählte Ordner-/Laufwerkszuordnung bleibt erhalten. Eingaben laden automatisch nach einer kurzen Pause neu; Enter lädt sofort. Serverseitig wird das Muster auf den Dateinamen angewendet; die vorhandene Obergrenze von 5.000 Dateien bleibt aktiv. Die Dublettenanzeige ist momentan unabhängig von den Panel-Filtern.

Die API-Dokumentation findet sich in `assets/php/classes/API.md` und `assets/javascript/classes/API.md`.

## Version 7.0 – Dateiauswahl

Die Dateiübersichten in beiden Panels besitzen individuelle Auswahlkästchen sowie ein „Alle auswählen“-Feld in der Tabellenüberschrift. Ein Zähler informiert über die markierten Dateien. Der Auswahlzustand ist je Panel unabhängig, wird bei Ordner-/Laufwerkswechsel zurückgesetzt und bei Tabellenaktualisierungen auf weiterhin angezeigte Dateien beschränkt. Die Auswahl führt keine Server-Aktionen aus. Siehe `assets/javascript/classes/API.md` für Methoden und Beispiele. An der PHP-API hat sich in dieser Version nichts geändert.

## Version 8.0 – Fensterfüllende Panels und Dateiaktionen

Die Panels nehmen auf Desktop-Bildschirmen den freien vertikalen Platz bis zur Aktionsleiste ein; ihre Dateitabellen scrollen unabhängig. Unter beiden Panels befinden sich Quellpanel-Auswahl sowie die Aktionen **Kopieren**, **Verschieben**, **Umbenennen**, **Löschen**. Aktionen wirken nur auf markierte Dateien; Kopieren und Verschieben verwenden das gegenüberliegende Panel als Ziel. Eine Bestätigung ist erforderlich; Zieldateien werden nie überschrieben.

**Sicherheit:** Dateiveränderungen sind ausschließlich für vertrauenswürdige lokale/serverseitige Installationen gedacht. Vor produktivem oder öffentlich erreichbarem Einsatz Laufwerke auf explizit freigegebene Verzeichnisse beschränken und Authentifizierung, rollenbezogene Berechtigungen und CSRF-Schutz ergänzen. Löschen ist nicht rückgängig zu machen.


### Version 9.0 – Panelspalten
Klick auf den blauen Kopfbereich „Panel links“ oder „Panel rechts“ (oder eine Spaltenüberschrift) öffnet den eigenen Dialog zur Auswahl der angezeigten Dateieigenschaften. Die Einstellungen werden getrennt nach Panel im Browser gespeichert; mindestens eine Datenspalte muss aktiv bleiben.

### Dateinamen-Dubletten auswählen

Der Button **Dubletten finden** vergleicht die vollständigen Dateinamen (inklusive Erweiterung) der aktuell sichtbaren Dateien aus zwei verschiedenen Ordnern. Treffer werden in beiden Tabellen per Checkbox ausgewählt; alle Tabellenzeilen bleiben sichtbar. Bestehende Auswahlen werden zurückgesetzt. Die aktiven Dateifilter und die Option „Unterordner einbeziehen“ gelten weiterhin. Dateien werden dabei nicht geändert. Gleiche Namen mit unterschiedlicher Größe oder unterschiedlichem Inhalt gelten als Treffer.

### Laufwerksbezeichnungen anzeigen

Die Laufwerksauswahl zeigt unter Windows nun beispielsweise `C: (Windows)` oder `D: (Daten)` an. Die Bezeichnungen werden auf dem **PHP-Server** gelesen (zuerst über COM / `Scripting.FileSystemObject`, bei deaktivierter COM-Erweiterung über PowerShell / `Win32_LogicalDisk`). Ist eine Laufwerksbezeichnung leer oder nicht abrufbar, bleibt die Anzeige bei `C:`. Die Laufwerks-ID im HTML-`value` bleibt unverändert, sodass Ordnernavigation und Dateiaktionen weiterhin funktionieren. Unter Linux/Unix bleibt die Darstellung `/` erhalten.

### Sortieren per Spaltenkopf
In beiden Panels lässt sich jede angezeigte Spalte per Klick sortieren: **aufsteigend ▲**, **absteigend ▼**, **ohne Sortierung ↕**. Größen und Datumswerte werden numerisch behandelt. Beim dritten Klick wird die ursprüngliche Serverreihenfolge wiederhergestellt. Die Auswahl von Dateien bleibt bestehen. Die Spaltenkonfiguration erfolgt weiterhin über die Panelüberschrift (⚙).


### Filter umkehren (beide Panels unabhängig)
Das Kontrollkästchen **Filter umkehren** unter dem Dateifilter kehrt ausschließlich den Dateinamen-Platzhalterfilter im jeweiligen Panel um. Bei `*.md` werden ohne Häkchen alle `.md`-Dateien angezeigt, mit Häkchen alle Dateien außer `.md`. Bei `*` und aktivierter Umkehrung werden keine Dateien angezeigt. Globale Namens-/Endungsfilter, Verzeichniswahl und Rekursionsmodus bleiben unverändert; die Invertierung wird bereits beim serverseitigen Listing angewandt (maximal 5000 Ergebnisdateien). Sortierung und Auswahl der sichtbaren Dateien bleiben funktionsfähig.

## Korrektur: Panel-Dateifilter
Die Filter beider Panels besitzen jetzt zusätzlich die Schaltfläche „Anwenden“. Änderungen wirken automatisch beim Tippen (250 ms), bei Feldwechsel, mit Enter und durch Klick auf „Anwenden“. Das sichtbare `*` bedeutet alle Dateien; der graue Text `z. B. *.md` ist nur ein Platzhalter. Ein leeres Filterfeld wird zu `*` normalisiert. Die serverseitige Dateinamenfilterung bleibt bestehen.

## Text aus markierten Dateinamen entfernen
In jedem Panelkopf befindet sich **Aus Dateinamen löschen**. Dateien per Checkbox auswählen,
Schaltfläche klicken, exakten Suchtext eingeben und die Vorschau bestätigen. Es wird jede
Fundstelle im vollständigen Dateinamen (einschließlich Erweiterung) ersetzt. Die Aktion
gilt ausschließlich für die markierten Dateien im betreffenden Panel. Unveränderte Namen
werden übersprungen. Ungültige Namen, doppelte Zielnamen und vorhandene Zieldateien
werden vor der Ausführung abgewiesen; es findet kein Überschreiben statt.

### Spaltenauswahl im Panelkopf (aktuell)

Im Kopf **jedes Panels** gibt es den eigenen Button **⚙ Spalten auswählen**. Er öffnet den bestehenden Spaltendialog für genau dieses Panel. Die Spaltenüberschriften in der Dateitabelle dienen ausschließlich der Sortierung (aufsteigend → absteigend → ohne); die Dateinamen-Löschfunktion bleibt separat.

### Unterordner einbeziehen (panelweise)

Direkt neben „Filter umkehren“ steht für jedes Panel eine eigene Checkbox „Unterordner einbeziehen“. Sie ist zunächst ausgeschaltet und aktualisiert beim Umschalten nur das jeweilige Panel. Bei eingeschalteter Option werden alle Dateien unterhalb des gewählten Ordners bis zur serverseitigen Obergrenze (5.000 Einträge) durchlaufen. Dateifilter und Filterumkehr gelten dabei auch für Dateien aus Unterordnern. In der Spalte „Relativer Pfad“ sind gleichnamige Dateien unterscheidbar. Markieren, Kopieren, Verschieben und Umbenennen verwenden unverändert den vollständigen relativen Dateipfad. Der Dateinamen-Dublettenvergleich arbeitet mit den in beiden Panels aktuell sichtbaren Dateien; deshalb können die Optionen links/rechts unterschiedlich sein.


## Reihenumbenennung mit Muster (integriert am 01.11.2026)

Die Funktion ist **in beiden Panels** über „Reihenumbenennung mit Muster“ erreichbar.
Zunächst im entsprechenden Panel Dateien markieren, dann Muster/Start/Schritt setzen,
„Vorschau erstellen“ wählen und erst anschließend „Umbenennung ausführen“ anklicken.
Die aktuelle **sichtbare Sortierung** bestimmt die Nummerierungsreihenfolge.

Beispiele: `Foto_{n:3}` → Foto_001.jpg, Foto_002.jpg; `{name}_{n:2}` → Bericht_01.pdf;
`{date}_{name}` → 2026-11-01_Bericht.pdf. Die Dateiendung bleibt erhalten.
Unterordner bleiben erhalten. Vorherige Namen werden nicht überschrieben, Kollisionen
werden erkannt; ein Tausch von zwei Namen erfolgt mit temporären Zwischennamen.

Die Erweiterung verwendet dieselbe serverseitige Laufwerksfreigabe in
`assets/php/api.php` wie die bestehenden Aktionen. Die beiden neuen POST-Aktionen
`batchRenamePreview` und `batchRenameApply` benötigen einen Sitzungs-CSRF-Token;
`index.php` erzeugt ihn für die Seite. Alle anderen Dateiaktionen bleiben unverändert.
Bei der Umbenennung überprüft PHP die gewählten relativen Dateipfade und den
gewählten Panelordner (inklusive der Einstellung „Unterordner einbeziehen“).

**Sicherheit:** Die ursprüngliche Installation erlaubt auf Unix standardmäßig die
Serverwurzel `/` und besitzt keine eigene Benutzeranmeldung. Vor öffentlichem Einsatz
müssen `api.php` und `index.php` durch eine serverseitige Zugriffskontrolle geschützt
und die Laufwerke auf freigegebene Verzeichnisse begrenzt werden. Das CSRF-Token ist
kein Ersatz für Anmeldung/Zugriffsrechte.

Tests: `php tests/test_batch_rename.php` (isolierte Dateien), außerdem die
PHP-Syntaxprüfung und HTTP-Testbeschreibung in `tests/INTEGRATION_TESTS.md`.

### Konfliktregeln beim Kopieren und Verschieben

Nach Auswahl der Dateien im Quellpanel öffnet sich beim Kopieren oder Verschieben ein Dialog mit Vorschautabelle und folgenden Regeln:

- **Quelle ersetzt Ziel:** Eine bereits existierende Zieldatei wird durch den Inhalt der Quelle ersetzt.
- **Neuere Datei behalten (Standard):** Nach dem Änderungsdatum wird nur dann ersetzt, wenn die Quelle neuer ist.
- **Ältere Datei behalten:** Es wird nur dann ersetzt, wenn die Quelle älter ist.
- **Größere Datei behalten:** Es wird nur dann ersetzt, wenn die Quelle größer ist.
- **Kleinere Datei behalten:** Es wird nur dann ersetzt, wenn die Quelle kleiner ist.
- **Ziel behalten:** Vorhandene Zieldateien werden übersprungen.
- **Quelle unter neuem Namen behalten:** Bei Namenskonflikt wird `name (1).ext`, `name (2).ext` usw. gewählt.
- **Identische Inhalte überspringen:** Bei identischem SHA-256-Hash wird übersprungen, sonst ersetzt.

Bei gleichen Größen oder Änderungszeiten bleibt jeweils das Ziel erhalten. Übersprungene Dateien werden auch beim Verschieben **nicht** aus der Quelle gelöscht. Das Vorschaufenster zeigt die geplante Aktion je Datei. Der Server prüft vor der Ausführung erneut, ob die Dateien noch vorhanden sind. Dateien aus Unterordnern werden wie bisher direkt in den Zielordner eingefügt (ohne Unterordnerstruktur). Gleiche Basisnamen innerhalb der Auswahl werden vorsorglich als Fehler markiert.

Hinweis: Das Ersetzen vorhandener Dateien kann Daten vernichten. Vor umfangreichen Aktionen empfiehlt sich eine externe Sicherung.

## Kontextbezogene Hilfe

In der Oberfläche sind kleine **?**-Schaltflächen an den relevanten Funktionen verfügbar. Sie öffnen einen modalen Hilfedialog, ohne die aktuellen Eingaben oder Markierungen zu verändern. Das Hilfefenster lässt sich über **Schließen**, **✕** oder **Esc** verlassen. Alle Hilfeschaltflächen sind per Tastatur erreichbar und besitzen zugängliche Beschriftungen.

Die Hilfe deckt die allgemeine Bedienung, Laufwerke, Ordner, globale und panelbezogene Dateifilter, Filterumkehr, Unterordner, Spaltenauswahl, Sortierung, Dateimarkierung, Dublettensuche, Textentfernung aus Dateinamen, Musterumbenennung, Dateiaktionen sowie Kopier-/Verschieberegeln ab. Die Inhalte liegen lokal in `assets/javascript/classes/FilesHelp.js`; dafür sind keine zusätzlichen PHP-Endpunkte oder externen Bibliotheken notwendig.

### Ordner in Dateipanels
Beide Dateitabellen enthalten jetzt Dateien und Ordner. Ordnerzeilen sind am Symbol 📁 erkennbar und lassen sich durch Doppelklick oder Enter öffnen. Der Dateifilter wirkt nur auf Dateien; Ordner bleiben sichtbar, damit die Navigation auch bei `*.md` und bei umgekehrter Filterung möglich ist. Ist „Unterordner einbeziehen“ aktiv, werden Ordner aus weiteren Ebenen ebenfalls angezeigt. Dateiaktionen, Auswahlkästchen und Dublettensuche berücksichtigen **nur Dateien**. Die Anzahl der angezeigten Ordner wird neben der Dateianzahl angezeigt.

### Navigation zum übergeordneten Ordner

Die Zeile `..` in beiden Dateipanels öffnet das übergeordnete Verzeichnis per **Doppelklick** (wie normale Ordner). Die Tastaturbedienung mit Enter oder Leertaste bleibt erhalten. Ein einzelner Klick navigiert nicht.

## Version 1 – Ordnerverwaltung (2026-11-02)

- **Ordner erstellen:** `+ Ordner links` und `+ Ordner rechts` legen im aktuellen Panelordner einen neuen Unterordner an.
- **Ordner umbenennen:** Ordner-Checkbox aktivieren, `Datei / Ordner umbenennen` wählen, neuen Namen bestätigen. Die Umbenennung gilt für den gesamten Verzeichnisbaum.
- **Ordner kopieren / verschieben:** Ordner markieren und im Aktionsbereich kopieren oder verschieben. Der ausgewählte Ordner wird einschließlich aller Unterordner (auch leerer) im Gegenpanel angelegt; bei bereits existierendem Zielordner werden die Inhalte zusammengeführt.
- **Konflikte:** Die vorhandenen acht Regeln wirken pro enthaltene Datei. Die Vorschau zeigt die Einzeldatei-Entscheidungen vor der Ausführung. Bei übersprungenen Quelldateien bleiben diese beim Verschieben im Ausgangsordner erhalten. Leere Quellordner werden nach erfolgreichem Verschieben entfernt, nicht leere bleiben bestehen.
- **Gemischte Auswahl:** Gleichzeitig markierte Dateien und Ordner werden unterstützt. Einzeln markierte Dateien landen direkt im Zielordner, markierte Ordner behalten ihre Verzeichnisstruktur.
- **Sicherheit:** Nicht freigegebene Pfade, `..`, Laufwerkswurzeln, symbolische Links und das Übertragen eines Ordners in sich selbst sind gesperrt. Dateinamenskollisionen überschreiben nicht außerhalb der ausgewählten Regel. Aktionen erfordern das bestehende CSRF-Token.

**Grenze:** Größere Verzeichnisübertragungen werden derzeit synchron bearbeitet. Es gibt noch keine vollständige Transaktions-Rückabwicklung bereits erfolgreich kopierter Einzeldateien bei späteren Einzelfehlern. Ein vollständiger Browser-End-to-End-Test auf Windows muss separat erfolgen.

**Automatischer Test:** `php tests/test_folder_operations_v1.php`.

## Version 2: Papierkorb und Sicherheitskonfiguration

Version 2 ergänzt Papierkorb, Wiederherstellung, Rückgängig für das **letzte Löschen** und Aktionsprotokoll. Der alte endgültige Löschweg über die Oberfläche wird ersetzt. Um Verwechslungen zu vermeiden: Ein allgemeines transaktionales Undo für Kopier-, Verschiebe- oder Umbenennungsaktionen existiert noch nicht.

**Vor produktivem Einsatz:** Zugangsschutz am Webserver konfigurieren, `FILES_ALLOWED_DRIVES_JSON` auf freigegebene Arbeitsordner und `FILES_PRIVATE_STORAGE` auf einen privaten persistenten Ordner außerhalb des Webroots **und der freigegebenen Verzeichnisse** setzen. Sind komplette Laufwerkswurzeln freigegeben, muss die Berechtigungskonfiguration eingeschränkt werden, bevor der sichere Papierkorb verwendet werden kann. Ausführliche Hinweise: `tests/V2_TESTS.md`.

### V3: Intelligente Dublettensuche
1. Unterschiedliche Ordner in linkem und rechtem Panel wählen; Filter und „Unterordner einbeziehen“ nach Wunsch setzen.
2. **Erweiterter Dublettenvergleich** anklicken und mindestens eines der Kriterien aktivieren: **Dateiname**, **Größe**, **Änderungsdatum** (Unix-Sekunden), **SHA-256**.
3. **Suchen** anklicken. Alle gewählten Kriterien müssen übereinstimmen. SHA-256 bestimmt den Inhalt, nicht die Ähnlichkeit der Dateien.
4. Treffergruppen und ihre tatsächlichen Pfade prüfen. **Behalten: Links/Rechts** wählen und **Andere Treffer markieren** klicken.
5. Die Markierung im Gegenpanel noch einmal kontrollieren. Optional **Markierte Treffer in Papierkorb …** klicken, die V2-Löschvorschau durchlesen und gesondert bestätigen. Alternativ die normalen Panelaktionen verwenden.

**Achtung:** Die Auswahl der zu behaltenden Seite gilt für alle Gruppen, nicht individuell pro Datei. Falls Duplikate mehrfach auf einer Seite vorkommen, bleiben diese ebenfalls bestehen. Die alte Schaltfläche „Dubletten finden“ (nur Name) bleibt unverändert. Die Anzeige ist auf 5.000 Einträge je Panel und 1.000 Gruppen beschränkt; insbesondere bei vielen Dateien können Treffer fehlen. Bei nicht vertrauenswürdigen/veränderlichen Verzeichnissen nach der Suche vor dem Löschen nochmals verifizieren. Auf sehr großen Dateien kann SHA-256 Zeit benötigen.

Test: `php tests/test_v3_duplicates.php` (weitere Regressionstests in `tests/`).

## V4: Bedienkomfort

Neu: Navigation Zurück/Vorwärts, lokale Favoriten, Dateivorschau für Text/Bilder, Drag & Drop zwischen Panels und Fortschrittsanzeige bei Kopieren/Verschieben. Der Abbruch erfolgt zwischen ausgewählten Dateien/Ordnern; ein gerade bearbeiteter Ordner wird erst abgeschlossen. Details, Grenzen und Prüfbefehle stehen in `tests/V4_TESTS.md`.


## Files V5
ZIP-Erstellung und sicheres Entpacken benötigen die PHP-Erweiterung `zip`. Für ZIP-Aktionen gelten Maximalwerte von 1 GiB (ungepackt) und 10.000 Einträgen. Dateien werden nicht überschrieben. CSV/JSON exportieren angezeigte Paneldaten, Filterprofile werden browserlokal gespeichert. Details: `tests/V5_TESTS.md`.


## Fehlerkorrektur vom 05.11.2026 – Papierkorb und Komplettprüfung

Das Paket verwendet ausschließlich PHP, JavaScript, HTML und CSS. Python wird **nicht** zum Betrieb benötigt; die optionalen Testskripte unter `tests/` können auf dem Entwicklerrechner ausgeführt werden.

**Löschen:** Auswahl im linken/rechten Panel per Kontrollkästchen setzen, im Aktionsbereich das Quellpanel einstellen und **In Papierkorb** wählen. Sind beide Panels markiert, gilt ausschließlich die Auswahl des Quellpanels. Für Dateien erscheint eine Sicherheitsabfrage, für enthaltene Ordner zusätzlich die Eingabe `PAPIERKORB`. Anschließend werden **beide Panels aktualisiert**. Über „Papierkorb / Wiederherstellen“ können die Einträge zurückgeholt werden, wenn deren ursprüngliche Pfade verfügbar und frei sind.

**Wichtige Serverkonfiguration:** Der private Papierkorb muss außerhalb des tatsächlichen Web-Dokumentenstamms liegen. Unter Apache/XAMPP beispielsweise `FILES_PRIVATE_STORAGE=C:\ProgramData\EasyIT\FilesTrash` verwenden (vorausgesetzt, der PHP-Webserver hat Schreibrechte). `FILES_ALLOWED_DRIVES_JSON` nur für explizit freigegebene Arbeitsordner setzen; keine öffentlich erreichbaren Voll-Laufwerke ohne Anmeldung freigeben. Der Papierkorb nutzt nötigenfalls eine verifizierte laufwerksübergreifende Kopie.

**Prüfungen:** `php tests/test_trash_cross_volume.php` und sämtliche `php tests/test_*.php` ergänzen die bisherigen Einzeltests. Unter `tests/PRUEFPROTOKOLL_2026-11-05.md` stehen Ergebnis, HTTP-Prüfungen, Browserprüfung und offene Umgebungsabhängigkeiten. Die Tests werden mit isolierten temporären Testdateien durchgeführt, nicht mit Produktivdaten. Für ZIP erstellen/entpacken ist weiterhin `extension=zip` in PHP erforderlich.


## V5.1: Schnelle Kopiervorschau und sichtbarer Fortschritt
Nach Auswahl einer oder mehrerer Dateien/Ordner auf **Kopieren** oder **Verschieben** klicken. Der Dialog zeigt nach einer kurzen Prüfung der markierten **obersten Einträge** die Schaltfläche **Aktion ausführen**. Unterordner und Konflikte innerhalb von Ordnern werden erst beim eigentlichen Transfer ausführlich geprüft. Bei einer ausgedehnten Ordnerübertragung bleibt der Fortschritt auf dem aktuellen Ordner, bis der PHP-Aufruf abgeschlossen ist. **Abbrechen** stoppt vor dem nächsten markierten Eintrag; bereits erledigte Schritte bleiben bestehen.

Nach dem Server-Update Browserseite mit **Strg+F5** neu laden, damit die korrigierten CSS-/JS-Dateien aktiv werden. Sollte der Transfer trotz der neuen Vorschau scheitern, zeigt der Dialog jetzt den PHP-/HTTP-Fehler an. Test-Szenario: von Laufwerk C: nach E: mit sieben gemischten markierten Einträgen. Tests: `php tests/test_transfer_selection_preview.php` (und optional `python tests/test_transfer_dialog_ui.py`).
