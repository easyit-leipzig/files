# PHP Files API – Version 2.0

Klasse: `Files.php`, PHP 8.0+; lesender Dateizugriff. Die PHP-API ist ausschließlich unter vorab konfigurierten Roots erreichbar.

| Methode | Parameter | Rückgabe | Beschreibung |
|---|---|---|---|
| `__construct` | `array $roots` | `Files` | Zuordnung der zugelassenen Basisordner |
| `roots` | – | `string[]` | Bekannte Root-IDs |
| `fields` | – | `array` | Verfügbare Dateieigenschaften (Feld → Beschriftung) |
| `folders` | `string $side, string $folder=''` | `array` | Ordner, unmittelbare Unterordner und Elternordner |
| `list` | `string $side, string $name='', string $extension='', string $folder='', bool $recursive=true, array $columns=[], int $limit=5000` | `array` | Dateiliste, optional mit SHA-256 |
| `duplicates` | `string $left='left', string $right='right', string $leftFolder='', string $rightFolder='', bool $recursive=true` | `array` | Größen-/Hash-Dublettenpaare |

## HTTP-Endpunkte: `/assets/php/api.php` (GET)

- `action=config`: `fields`, `roots`.
- `action=folders&side=left&folder=Unterordner`: `side`, `folder`, `parent`, `directories`, `rootLabel`.
- `action=list&side=right&folder=Archiv&recursive=1&name=test&extension=pdf&columns=name,size,modified,sha256`: Array mit Dateieigenschaften.
- `action=duplicates&leftFolder=2025&rightFolder=2026&recursive=1`: Array mit `left`, `right`, `sha256`.

JSON-Format: Erfolg `{"ok":true,"data":...}`, Fehler `{"ok":false,"error":"..."}` und HTTP 400/500.

## Sicherheits- und Semantikhinweise

- Server konfiguriert `$roots`, Verzeichnisauswahl ist auf den jeweiligen Root begrenzt; keine freien Betriebssystempfade.
- Symlinks werden beim Durchlaufen ausgeschlossen. Ungültige Pfade werden abgewiesen.
- `created` bedeutet `ctime` (Statusänderungszeit, insbesondere auf Linux **nicht** verlässlich Dateierstellung).
- `accessed` kann wegen Mount-Optionen (noatime/relatime) veraltet sein.
- `sha256` nur bei Anforderung der Spalte bzw. bei Dublettenprüfung; das kann bei großen Dateien dauern.
- `list` liefert maximal 5000 Dateien; bei großen Beständen sind spätere Pagination/Indexierung erforderlich.
- API bleibt read-only; Upload/Umbenennen/Löschen sind nicht implementiert.

## Beispiel

```php
$files = new Files(['left'=>__DIR__.'/storage/left','right'=>__DIR__.'/storage/right']);
$folders=$files->folders('left','');
$items=$files->list('left','','pdf','',true,['name','size']);
```

## Version 4.0 – UI-Fehlerbehebung

Keine Änderung an PHP-Signaturen. Die JavaScript-Methode `folders(side, folder)` ruft weiterhin `action=folders` ab. Der Dialog zeigt ausschließlich Verzeichnisse innerhalb des konfigurierten serverseitigen Stammordners.

## Version 5.0 – Laufwerksauswahl

`Files::__construct(array $roots)` erhält eine **Allowlist** mit Laufwerks-IDs als Schlüsseln und absoluten Serverpfaden als Werten. `Files::drives(): array` liefert `[{'id','label','path'}, ...]` der freigegebenen Laufwerke. Alle Zugriffe verwenden die Laufwerks-ID statt eines frei übergebenen absoluten Pfads. `folders(string $side, string $folder='')` sowie `list(...)` und `duplicates(...)` verwenden den ersten Parameter nun als **Laufwerks-ID** (aus Kompatibilitätsgründen weiter `$side` benannt).

### GET `assets/php/api.php`

- `action=config`: liefert `data.fields` und `data.drives` (ID, Label, virtueller Pfad).
- `action=folders&drive=root&folder=`: Verzeichnisse direkt unter der freigegebenen Laufwerkswurzel; `folder` ist relativ.
- `action=list&drive=root&folder=&recursive=0`: Dateien unmittelbar unter der Wurzel; `recursive=1` berücksichtigt Unterordner bis zur bisherigen Grenze von 5000 Datensätzen.
- `action=duplicates&leftDrive=root&rightDrive=root&leftFolder=...&rightFolder=...&recursive=0`: Vergleich zweier gewählter Laufwerke/Ordner.

**Sicherheit:** Der Browser wählt nur Laufwerks-IDs aus der Allowlist; `realpath` und die Pfadgrenzenprüfung verweigern Zugriffe außerhalb der erlaubten Wurzel. Auf Unix ist `/` eine sehr weitgehende Freigabe: Nur in einer abgesicherten lokalen Installation nutzen, andernfalls die `$drives`-Konfiguration in `api.php` auf freigegebene Datenspeicher beschränken. Zugriffsrechte des PHP-Prozesses gelten weiterhin. Dateiaktionen sind nicht freigeschaltet.

## Version 6.0 – Dateinamenmuster (`pattern`)

`Files::list(string $side, string $name='', string $extension='', string $folder='', bool $recursive=true, array $columns=[], int $limit=5000, string $pattern='*'): array`

Der neue optionale letzte Parameter `pattern` filtert **Dateinamen** (nicht den gesamten Pfad) mit einem Wildcard-Muster:

- `*` – alle Dateinamen (Standard; auch ein leeres Muster wird wie `*` behandelt)
- `*.md` – Markdown-Dateien
- `bericht_*.pdf` – PDF-Dateien, deren Name mit `bericht_` beginnt
- `bild_??.png` – PNG-Dateien mit genau zwei Zeichen nach `bild_`

`*` entspricht beliebig vielen Zeichen (auch null), `?` genau einem Zeichen. Der Vergleich ist ohne Beachtung der Groß-/Kleinschreibung. Muster sind am gesamten Dateinamen verankert. Alle sonstigen bestehenden Filter (`name`, `extension`, `folder`, `recursive`) wirken zusätzlich. Die Begrenzung von 5.000 Ergebnissen bleibt bestehen. Ungültige Muster (länger als 255 Byte oder mit NUL-Zeichen) führen zu `InvalidArgumentException`.

HTTP-API: `GET assets/php/api.php?action=list&drive=root&folder=&pattern=%2A.md&recursive=0`. Die Laufwerks-ID muss freigegeben sein. Rückgabe: `{ "ok": true, "data": [ ...Dateiobjekte ] }` oder bei Fehler `{ "ok": false, "error": "..." }` mit entsprechendem HTTP-Status.

## Version 8.0 – Dateiaktionen

`perform(string $action, string $drive, array $paths, string $targetDrive='', string $targetFolder='', string $newName=''): array`

Führt `copy`, `move`, `rename` oder `delete` auf **explizit ausgewählten** relativen Dateipfaden aus. Für `copy`/`move` sind Ziel-Laufwerk und Zielordner erforderlich; für `rename` muss genau eine Datei übergeben werden und `newName` ist der reine Dateiname. `delete` entfernt Dateien unwiderruflich. Die API liefert für jede Datei `{path,ok,error?}` zurück. Grenzen: maximal 500 Dateien/Aufruf; keine Verzeichnisaktionen, keine symbolischen Links; vorhandene Ziele werden nicht überschrieben. Pfade müssen innerhalb des freigegebenen Laufwerks bleiben. Für produktive Einsätze Laufwerksfreigabe und Rechte serverseitig restriktiv konfigurieren.

**POST `assets/php/api.php`** mit `Content-Type: application/json`:

```json
{"action":"copy","drive":"Data","paths":["docs/a.md"],"targetDrive":"Archiv","targetFolder":"backup"}
```

`GET` akzeptiert keine mutierenden Aktionen. Hinweis: PHP-Prozessrechte bestimmen, ob eine Aktion tatsächlich erlaubt ist. Die API muss für Mehrbenutzer- oder öffentlich zugängliche Installationen mit Authentifizierung, Autorisierung und CSRF-Schutz hinterlegt werden.

### Laufwerksanzeige mit Volumenbezeichnung

`Files::drives()` liefert auf Windows für Laufwerks-IDs `C:`/`D:` das `label` im Format `C: (Windows)` / `D: (Daten)`, sofern die Volume-Bezeichnung abrufbar und nicht leer ist. `id` und `path` bleiben unverändert. Die Bezeichnung wird über Windows COM oder als Fallback über PowerShell ermittelt. Ohne verfügbare Systemabfrage wird nur der Laufwerksbuchstabe angezeigt.


### Filter umkehren (beide Panels unabhängig)
Das Kontrollkästchen **Filter umkehren** unter dem Dateifilter kehrt ausschließlich den Dateinamen-Platzhalterfilter im jeweiligen Panel um. Bei `*.md` werden ohne Häkchen alle `.md`-Dateien angezeigt, mit Häkchen alle Dateien außer `.md`. Bei `*` und aktivierter Umkehrung werden keine Dateien angezeigt. Globale Namens-/Endungsfilter, Verzeichniswahl und Rekursionsmodus bleiben unverändert; die Invertierung wird bereits beim serverseitigen Listing angewandt (maximal 5000 Ergebnisdateien). Sortierung und Auswahl der sichtbaren Dateien bleiben funktionsfähig.
