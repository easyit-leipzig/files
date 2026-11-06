# Files – Prüfprotokoll vom 05.11.2026

## Ausgangsfehler und Abhilfe

- **Reproduziert:** `deletePreview` lieferte HTTP 200, aber `deleteApply` lieferte HTTP 500 („Privater Papierkorb nicht beschreibbar ... innerhalb des Webverzeichnisses“). Ursache war eine zu hoch angesetzte Webroot-Grenze. Nach der Korrektur laufen Vorschau, Papierkorb und Rückgängig über dieselbe PHP-HTTP-Schnittstelle mit HTTP 200.
- **Auswahlfehler:** Beide Panels mit markierten Einträgen blockierten das Löschen; nun wird die explizite Quelle berücksichtigt, die andere Auswahl bleibt unberührt. Ein abweichender Checkbox-Index bei Ordnerzeilen in der einfachen Dublettenmarkierung wurde beseitigt.
- **Datensicherheit:** Symlink-Auswahl durfte nicht zum unmarkierten Linkziel aufgelöst werden. Dies wird nun schon in der Löschvorschau und erneut vor der Papierkorbaktion abgelehnt.

## Durchgeführte Prüfungen

- **29 HTTP-Integrationstests** gegen PHP 8.4 mit einer abgeschlossenen Testfreigabe. Getestet: Session/CSRF, Laufwerke, Ordnerdialogdaten, Wildcards/Invertierung, Statistik, Dateivorschau, Ordner erstellen, Einzel-/Teilstück-/Reihenumbenennung, Kopiervorschau, Kopieren, Konfliktregel Ziel behalten, Ordner-Kopierplanung und Anwendung, SHA-256-Dublettenvergleich, Verschieben, Löschvorschau, Löschen exakt markierter Einträge, Papierkorbliste, Wiederherstellung, Undo, Audit und Symlink-Schutz.
- **Offline-Chromium mit simulierten API-Antworten:** Fünf Browser-/UI-Prüfungen erfolgreich: korrekte Zuordnung bei Dubletten mit Ordnerzeilen; gewählt bleibt das Quellpanel auch bei beidseitiger Markierung; der Lösch-POST enthält nur den beabsichtigten Pfad; beide Panels laden neu; keine unbehandelten JS-Ausnahmen.
- **PHP-Klassentests:** alle bestehenden `tests/test_*.php` plus `tests/test_trash_cross_volume.php` bestanden. Letzterer prüft eine andere Dateisystempartition (`/dev/shm`) sowie Datei-/Ordner-Restore, leere Unterordner und Symlinks.
- **Syntax:** `php -l` für alle PHP-Dateien, `node --check` für alle bearbeiteten JavaScript-Dateien erfolgreich.

## Grenzen und Serverabhängigkeiten

- Die lokale PHP-Installation enthält **keine Erweiterung `zip`**. Die ZIP-Erstellung liefert die korrekte Fehlermeldung, aber ein kompletter Lauf mit ZIP-Erstellung und -Entpacken ist hier nicht möglich. Vor Einsatz `php -m` prüfen und Erweiterung `zip` aktivieren.
- Ein realer Browser-End-to-End-Test **auf dem Ziel-Windows-/XAMPP-Server** ist zusätzlich nötig; die Offline-Browserprüfung nutzt simulierte API-Antworten und ersetzt die Serverabnahme nicht.
- Die PHP-Anwendung enthält keine eigene allgemeine Benutzeranmeldung/Rechteverwaltung. Unbedingt Webserver-Zugriffsschutz und eng begrenzte Laufwerksfreigaben einsetzen.
- Kein vollständiger transaktionaler Rollback aller Kopier-/Verschiebe-Massenaktionen; erfolgreich übertragene Einzeldateien können bei späteren Fehlern erhalten bleiben.

## Wiederholen

```bash
php tests/test_trash_cross_volume.php
php tests/test_selected_trash.php
php tests/test_folder_operations_v1.php
php tests/test_transfer.php
php tests/test_v3_duplicates.php
php tests/test_v4_preview.php
php tests/test_v5_statistics.php
```

Die optionalen HTTP- und Browser-Testskripte sind `tests/test_http_integration.py` und `tests/test_browser_ui_mock.py`. Diese Testprogramme benötigen Python nur auf dem **Prüfrechner**, nicht auf dem Webserver oder innerhalb des Files-Projekts. Für den HTTP-Test muss eine abgesicherte temporäre Testfreigabe als `Test` bereitgestellt sein.
