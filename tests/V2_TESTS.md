# Version 2 – Papierkorb, Wiederherstellung, Schutzmaßnahmen

## Installation / sicherer Betrieb

1. Die Anwendung muss **durch Server-Authentifizierung** geschützt werden (z. B. Webserver-Zugangsschutz oder EnterpriseManager). V2 enthält keine eigene Benutzerverwaltung.
2. `FILES_ALLOWED_DRIVES_JSON` auf explizit freigegebene Ordner setzen, etwa unter Windows `{"Daten":"D:\\Freigabe","Archiv":"E:\\Archiv"}`. Ohne diese Einstellung gilt weiterhin die alte automatische Laufwerksliste.
3. `FILES_PRIVATE_STORAGE` auf ein dauerhaftes, vom Webserver schreibbares Verzeichnis **außerhalb des Webroots und außerhalb aller freigegebenen Verzeichnisbäume** setzen, etwa `C:\\ProgramData\\EasyIT\\FilesTrash` (sofern C: nicht als Wurzel freigegeben ist). Der Start einer Papierkorbaktion wird blockiert, wenn sich der private Speicher innerhalb eines freigegebenen Verzeichnisbaums befindet.
4. Das private Verzeichnis darf über HTTP nicht erreichbar sein. Die enthaltenen `.htaccess`/`web.config` sind zusätzliche Schutzmaßnahmen, kein Ersatz für eine sichere Serverkonfiguration.
5. Webserver-PHP-Benutzer braucht Lese-/Schreib- und Umbenennungsrechte. Für die verlustfreie Papierkorbverschiebung müssen Quelle und Papierkorb auf einem kompatiblen Dateisystem liegen. Andernfalls wird sicher abgebrochen; kein automatisches endgültiges Löschen.
6. Der Papierkorb liegt nicht im Windows-Papierkorb und wird nicht automatisch geleert. Für Speichergrenzen und Aufbewahrungsfristen ist eine Administration nötig.

## Umfang

- Dateien und vollständige Ordner via Rename in einen persistenten privaten Papierkorb verschieben.
- Wiederherstellung mit Kollisionsschutz. Der ursprüngliche Elternordner muss existieren.
- „Letztes Löschen rückgängig“ = Wiederherstellung des neuesten Papierkorbeintrags; **kein allgemeines Undo für Kopieren, Verschieben, Überschreiben oder Umbenennen**.
- Serverseitiges Protokoll (JSONL) für Löschen, Wiederherstellen, Umbenennen, Kopieren, Verschieben und Ordneraktionen. Protokoll ersetzt keine transaktionale Rollback-Engine.
- CSRF-Prüfung für alle schreibenden API-Aktionen. Optionale Laufwerks-Allowlist.
- Direkter alter `delete`-API-Endpunkt gesperrt.

## Tests

`php tests/test_v2_trash.php`

Weitere Regressionstests: `test_folder_operations_v1.php`, `test_transfer.php`, `test_folder_rows.php`.

**Einschränkung:** Ausführung im Browser und auf Windows muss auf dem Zielsystem geprüft werden; Tests laufen auf temporären Verzeichnissen unter Linux. Eine SQL-Datenbank oder Anbindung an den Lizenzserver ist für V2 nicht implementiert.
