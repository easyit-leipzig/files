# V3 Prüfungen

Automatisch: `php tests/test_v3_duplicates.php`; `php tests/test_folder_operations_v1.php`; `php tests/test_v2_trash.php`.

Browser (Windows / PHP-Server):
1. Zwei verschiedene Ordner laden; nach Filterung, invertiertem Filter und rekursiven Dateien suchen.
2. Name allein, Name+SHA, Größe+SHA, Änderung+Größe ausprobieren.
3. Beide Behalten-Richtungen testen; die jeweils andere Panel-Seite muss selektiert werden.
4. Markierte Treffer nur nach V2-Vorschau in den Papierkorb verschieben; Restore testen.
5. Browser-Konsole prüfen, große Verzeichnisse und fehlende Leseberechtigung simulieren.

Noch nicht vollständig automatisiert: E2E Browser-/Windows-Tests, TOCTOU-Bestätigung von Hashes vor Papierkorbaktion, sehr große Dateibestände.
