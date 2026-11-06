# Files V4 – Prüfung und Grenzen

## Neue Funktionen
- Verzeichnisverlauf für jedes Panel mit Zurück/Vorwärts (bis zu 100 Stationen je Panel, nicht persistent).
- Favoriten im Browser-LocalStorage, maximal 50 Einträge; freigegebene Laufwerke werden weiterhin serverseitig geprüft.
- Dateivorschau: Text maximal 128 KiB, Bilder PNG/JPEG/GIF/WebP maximal 2 MiB, sonst Metadaten.
- Fortschrittsanzeige für Kopieren/Verschieben nach ausgewählten **Top-Level-Einträgen**.
- Abbruch **zwischen** solchen Einträgen. Bereits abgeschlossene Übertragungen werden nicht rückgängig gemacht. Rekursive Ordner werden serverseitig innerhalb eines Requests ausgeführt; ihr Fortschritt wird nicht dateiweise angezeigt, und sie sind nicht mitten im Lauf abbrechbar.
- Drag & Drop zwischen Panels: Auswahl von Kopieren oder Verschieben, danach bestehender Konfliktdialog.
- Tastatur: F3 Vorschau, F5 Panel neu laden, Rücktaste zum Elternordner (nur außerhalb von Formularfeldern und Dialogen).

## Tests

`php tests/test_v4_preview.php` prüft Text, Größenbegrenzung, Binärdateien, unerlaubte Pfade, nicht freigegebene Laufwerke und Symlinks.

Regressionstests: `php tests/test_v3_duplicates.php`, `test_v2_trash.php`, `test_folder_operations_v1.php`, `test_transfer.php`, `test_folder_picker_files.php`, `test_folder_rows.php`, `test_delete_folders.php`, `test_batch_rename.php`.

## Manuelle Prüfung unter Windows/PHP erforderlich

1. Favoriten hinzufügen/öffnen/löschen, Vorwärts und Zurück zwischen Laufwerken.
2. F3 auf `.txt` und `.png`, sehr große Dateien, Verweigerung für nicht freigegebene Pfade.
3. Drag & Drop eines Ordners nach rechts, Regel auswählen, Papierkorb und Wiederherstellung prüfen.
4. Kopierauftrag mit mehreren markierten Einträgen starten und nach einem Eintrag abbrechen. Prüfen, dass abgeschlossene Einträge erhalten bleiben und nachfolgende unbearbeitet.
5. Nach `Strg+F5` prüfen, dass V4-JavaScript geladen wird.

**Sicherheit:** `FILES_ALLOWED_DRIVES_JSON`, `FILES_PRIVATE_STORAGE` und serverseitige Authentisierung sind weiterhin für einen produktiven Einsatz erforderlich. Die Vorschau ist nur über POST mit CSRF-Token zugänglich; sie ersetzt keine Benutzerrechtekontrolle.
