# Prüfprotokoll (lokale, isolierte Tests)

1. `php -l index.php` und `php -l assets/php/api.php` prüfen Syntax.
2. `php tests/test_batch_rename.php` prüft Muster, Unterordner, Kollisionen, Namentausch.
3. `node --check assets/javascript/classes/Files.js` und `node --check assets/javascript/classes/BatchRename.js`.
4. HTTP-Smoke-Test: PHP-Built-in-Server lokal starten, `index.php` laden, aus der
   Session den CSRF-Token auslesen, ausgewählte Testdateien mit `batchRenamePreview`
   und `batchRenameApply` bearbeiten. Kein produktives Dateisystem für Tests verwenden.
5. Manuell im Browser: Beide Panels unabhängig, Sortierung, Filter umkehren,
   Unterordner einbeziehen, Spaltenauswahl, Dubletten und Dateiaktionen prüfen.

Eine echte Browserabnahme auf dem Zielserver wird hier nicht behauptet.


## Zusätzliche Regressionstests vom 05.11.2026

- `php tests/test_trash_cross_volume.php`: nur ausgewählte Einträge, keine Symlinkziel-Löschung, rekursive Ordner, Wiederherstellung und (falls verfügbar) anderes Dateisystem `/dev/shm`.
- `python tests/test_http_integration.py` (nur Testrechner): 29 HTTP-Integrationstests; benötigt eine Testfreigabe mit Laufwerks-ID `Test`, `FILES_TEST_BASE_URL` und `FILES_TEST_DRIVE_ROOT`, sowie auf dem Testrechner `requests`.
- `python tests/test_browser_ui_mock.py` (nur Testrechner): echte JS-Eventhandler in Offline-Chromium, simulierte API; benötigt `playwright`, `beautifulsoup4`, `requests` und gerendertes PHP-HTML über `FILES_TEST_BASE_URL`.
- `tests/PRUEFPROTOKOLL_2026-11-05.md`: vollständige Ergebnisse und Umgebungsgrenzen.

Die Anwendung selbst arbeitet weiterhin ausschließlich mit PHP und JavaScript, ohne Python-Laufzeit.
