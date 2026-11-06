# Backend-API: BatchRename

**Voraussetzung:** PHP >= 7.4, freigegebene Panelwurzeln, autorisierte Sitzung, gültiger CSRF-Token (separater Endpunkt).

## `new BatchRename(array $roots, int $maxFiles = 5000)`

Instanziiert eine Umbenennungsengine. `roots` ist ein serverseitig ermitteltes Mapping von `'left'` / `'right'` auf absolute Basisordner.

```php
$rename = new BatchRename(['left'=>'D:/Daten', 'right'=>'E:/Archiv']);
```

## `preview(string $panel, array $paths, string $pattern, int $start=1, int $step=1): array`

Validiert den Plan ohne Dateisystemänderung; Fehler durch `InvalidArgumentException`/`RuntimeException`.

```php
$plan = $rename->preview('left', ['sub/a.txt','b.txt'], 'Dok_{n:3}', 1, 1);
// sub/Dok_001.txt, Dok_002.txt
```

## `apply(string $panel, array $paths, string $pattern, int $start=1, int $step=1): array`

Validiert den Plan unter Ordnersperre erneut, dann staged und benennt Dateien um; versucht bei Fehler Rückabwicklung.

```php
$result = $rename->apply('left', ['sub/a.txt','b.txt'], 'Dok_{n:3}');
// ['ok'=>true,'count'=>2,'changed'=>2,'items'=>...]
```

## `listing(string $panel, bool $recursive=true, int $limit=5000): array`

Hilfsfunktion für die isolierte Testoberfläche; kann vom vorhandenen Files-Listenendpunkt getrennt bleiben.

```php
$list = $rename->listing('right', true);
```

## JSON-Endpunkt `POST assets/php/batch-rename-api.php`

Header: `Content-Type: application/json`, `X-CSRF-Token: <Sessiontoken>`.

### Vorschau

```json
{"action":"preview","panel":"left","paths":["sub/BildA.jpg","BildB.jpg"],"pattern":"Foto_{n:3}","start":1,"step":1}
```

### Umbenennung

```json
{"action":"apply","panel":"left","paths":["sub/BildA.jpg","BildB.jpg"],"pattern":"Foto_{n:3}","start":1,"step":1}
```

### Antwort

```json
{"ok":true,"count":2,"changed":2,"items":[{"before":"sub/BildA.jpg","after":"sub/Foto_001.jpg","changed":true},{"before":"BildB.jpg","after":"Foto_002.jpg","changed":true}]}
```

**Zusätzliche Einschränkungen:** keine absoluten/traversierenden Pfade, keine Symlinks, kein Umbenennen von Ordnern, kein Überschreiben existierender Zieldateien, max. 5000 Dateien je Aufruf (konfigurierbar), keine Zielordneränderung, ungültige und Windows-reservierte Zielnamen ausgeschlossen. Keine Garantie einer atomaren Operation über mehrere Dateien: Bei außergewöhnlichen I/O-Problemen ist eine nur teilweise erfolgreiche Rückabwicklung theoretisch möglich und wird gemeldet.

## Integrierter HTTP-Endpunkt (abweichend von der Standalone-Demo)

`POST assets/php/api.php` mit JSON `action` = `batchRenamePreview` oder
`batchRenameApply`. Weitere Felder: `drive` (serverseitig erlaubte Drive-ID),
`folder` (relativer Panelordner), `recursive` (Boolean), `paths` (relative
Dateipfade), `pattern` (Muster), `start` und `step` (Integer).
Das Feld `panel` ist lediglich eine UI-Angabe; maßgeblich ist die serverseitig
freigegebene Drive-ID. Erforderlicher Header: `X-CSRF-Token` aus `index.php`;
der PHP-Endpunkt validiert Sitzung, Drive-ID, Panelordner, relative Pfade und Muster.
Die Antwort ist `{ "ok": true, "data": { "ok": true, "items": [...] } }`.
Es wird **kein** separater `batch-rename-api.php` benötigt.
