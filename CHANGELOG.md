## 2026-11-05 – Fehlerkorrektur und vollständige Funktionsprüfung

- **Papierkorb-Fix:** Falsch berechnetes Webverzeichnis in `TrashManager.php` korrigiert; gültige private Speicherorte neben der Anwendung werden nicht mehr irrtümlich als öffentlich klassifiziert. Tatsächlicher `DOCUMENT_ROOT` wird berücksichtigt; Pfade innerhalb oder oberhalb des Webroots sind ausgeschlossen.
- **Laufwerksübergreifender Papierkorb:** Ist direktes Verschieben nicht möglich, wird zuerst eine verifizierte Kopie erstellt. Erst nach erfolgreicher Prüfung wird die Quelle entfernt. Für Wiederherstellungen gilt dasselbe Verhalten. Leere Unterordner bleiben erhalten. Fehler hinterlassen nicht stillschweigend eine ungesicherte Quelle.
- **Auswahl statt falscher Datei:** Bei Markierungen in beiden Panels bestimmt die explizite Auswahl „Quellpanel“, was in den Papierkorb verschoben wird. Sind nur in einem Panel Einträge markiert, wird dieses automatisch verwendet. Auswahlkästchen werden nach dem Leeren korrekt synchronisiert.
- **Dubletten:** Falsch zugeordnete Kontrollkästchen bei Verzeichniszeilen korrigiert. Ordnerzeilen verschieben jetzt nicht mehr den Auswahlindex der Dateien.
- **Sicherheit:** Symlink-Pfade werden in allen Pfadkomponenten geprüft, um das Löschen eines unmarkierten Linkziels zu verhindern. Privater Speicher darf auch kein übergeordneter Ordner des Webroots sein. Bereits vorhandene Speicherordner werden nicht pauschal per `chmod` verändert.
- **Audit:** Der private Protokollspeicher wird für weitere verändernde Dateiaktionen vor dem eigentlichen Schreibvorgang geprüft. Nach bereits erfolgreichem Papierkorbverschieben wird ein separater Protokollfehler als Warnung und nicht als angeblich fehlgeschlagene Löschung gemeldet.
- **Kontexthilfen:** Erweitert und berichtigt für Papierkorb, Rückgängig, Protokoll, Export, ZIP, Filterprofile, Vorschau, Navigation und Statistik.
- **Tests:** 29 HTTP-Integrationstests über die PHP-API, fünf Offline-Browserprüfungen der Auswahl, bestehende PHPUnit-freie PHP-Tests und neue Laufwerks-/Symlink-Regressionstests. Der ZIP-End-to-End-Test erfordert die PHP-Erweiterung `zip`, die in der lokalen Prüfumgebung nicht installiert ist.

## 2026-11-02 – Navigation per Doppelklick

- `..`-Zeile in beiden Dateipanels reagiert jetzt auf Doppelklick statt auf Einzelklick; Enter/Leertaste bleiben nutzbar.
- JavaScript-Cachekennung aktualisiert.

## 2026-11-01 – Ordnernavigation und Ordnerdialog

- In jedem Panel erscheint unterhalb des Wurzelverzeichnisses eine nicht selektierbare `..`-Zeile; Klick oder Doppelklick navigiert eine Ebene nach oben.
- Ordnerdialog zeigt Dateien ausdrücklich als nicht auswählbare Zeilen und zählt die Einträge.
- Versionsparameter für das JavaScript verhindert die Nutzung einer veralteten Browser-Cache-Datei.

# Files – Änderungen

## 2026-11-01 – Muster-Reihenumbenennung integriert

- Vollständiger Ausgangsstand `files(1).zip` erhalten.
- Pro Panel Schaltfläche **Reihenumbenennung mit Muster** ergänzt.
- Gemeinsamer Dialog mit Muster, Startzahl, Schrittweite und Vorschautabelle.
- Die Nummerierung übernimmt die aktuell sichtbare Sortierfolge.
- PHP-Klasse `BatchRename` eingebunden, dieselben Laufwerksfreigaben wie bisher.
- Die Aktionen `batchRenamePreview`/`batchRenameApply` sind Teil von `api.php`.
- Session-CSRF-Prüfung für die neuen Aktionen, Pfad-/Kollisionsprüfungen.
- `Unterordner einbeziehen` wird bei der Auswahl respektiert.
- Bestehende Funktionen und Dateien unverändert belassen, abgesehen von
  notwendigen Erweiterungen in `index.php` und `api.php`.
- 18 isolierte Funktionschecks und HTTP-Testlauf erfolgreich.

### Sicherheitshinweis
Das Ausgangsprojekt besitzt keine Anmeldung und erlaubt auf Unix standardmäßig
`/` als Laufwerksbasis. Vor einer öffentlichen Bereitstellung müssen
Login-/Rechteprüfung und Laufwerksfreigaben eingerichtet werden.

## Transferdialog für Kopieren und Verschieben (2026-11-01)
- Dialog mit Konfliktregeln: Quelle ersetzt Ziel, neuere/ältere/größere/kleinere Datei behalten, Ziel behalten, Quelle unter neuem Namen speichern sowie SHA-256-Vergleich.
- Vorschau jeder ausgewählten Datei vor Bestätigung, getrennte Angabe von Ersetzen/Überspringen/Umbenennen/Fehlern.
- Serverseitige Entscheidung und Übertragung mit temporärer Datei und Sicherung bestehender Ziele; übersprungene Quellen werden beim Verschieben nicht gelöscht.
- Dateien aus Unterordnern werden wie bisher in den ausgewählten Zielordner flach übertragen; gleichnamige Quellen innerhalb einer Auswahl werden als Konflikt gemeldet.

### Kontext-Hilfe
- Lokale, zugängliche Hilfedialoge für zentrale Funktionen in beiden Panels und den Transferdialog ergänzt.
- Einheitliche ?-Schaltflächen, Escape-Schließen und Fokus-Rückgabe; Hilfe verändert keine Dateidaten.
- README um Hinweise zur kontextbezogenen Hilfe erweitert.

## Ordneranzeige in beiden Dateipanels (2026-11-01)
- Die Dateitabelle zeigt jetzt neben Dateien auch Verzeichnisse, gekennzeichnet mit 📁.
- Doppelklick bzw. Enter auf einer Ordnerzeile öffnet das Verzeichnis im jeweiligen Panel.
- Mit „Unterordner einbeziehen“ werden auch tieferliegende Verzeichnisse und Dateien aufgelistet.
- Dateinamenfilter einschließlich „Filter umkehren“ betreffen nur Dateien; Verzeichnisse bleiben zur Navigation sichtbar.
- Verzeichnisse sind nicht per Datei-Checkbox selektierbar und werden nicht kopiert, verschoben, umbenannt oder gelöscht.
- Die bestehende Dublettensuche und die Auswahl „alle Dateien“ ignorieren Ordnerzeilen.

## Ordnerauswahldialog mit Dateien (2026-11-01)
- Zeigt Unterordner und Dateien gemeinsam in alphabetisch sortierten Gruppen an.
- Dateien dienen der Orientierung und sind nicht auswählbar.
- Zähler für Ordner/Dateien und deutliche Kennzeichnung leerer Ordner.
- Schaltfläche „Diesen Ordner auswählen“ ist für vollständig leere Ordner deaktiviert; serverseitige Antwort wird vor dem Bestätigen erneut geprüft.

## V2 – 2026-11-02
- Privater Papierkorb für markierte Dateien und Ordner inklusive Wiederherstellung und Konfliktsperre.
- „Letztes Löschen rückgängig“, Papierkorbdialog und Aktionsprotokoll.
- Einheitliche CSRF-Anforderung für alle POST-Mutationen, Sperre des alten unmittelbaren Löschendpunkts.
- Optionale Freigabe von Laufwerkspfaden über `FILES_ALLOWED_DRIVES_JSON`.
- Private Speicherung muss vom freigegebenen Dateibaum getrennt sein; siehe `tests/V2_TESTS.md`.

## V3 – Intelligente Dublettenverwaltung (2026-11-02)
- Separater Dialog **Erweiterter Dublettenvergleich** zusätzlich zur bisherigen Dateinamen-Suche.
- Kombinierbare UND-Kriterien: Dateiname, Größe, Änderungszeit (Sekunden), SHA-256.
- Zwei Panels unabhängig hinsichtlich Filter, Invertierung und Unterordnerbehandlung.
- SHA-256 erst nach Vorfilterung nach Größe und optionalen Metadaten.
- Treffergruppen-Vorschau mit Pfad und Größe; keine automatische Löschung.
- Benutzer wählt zu behaltendes Panel; Treffer des Gegenpanels werden nach explizitem Klick markiert.
- Kontrollierte Bereinigung verwendet den V2-Papierkorb mit Bestätigungsdialog.
- **Grenzen:** 5.000 Einträge je Panel (inklusive Verzeichniszeilen), Ausgabe maximal 1.000 Gruppen; kein dateiweiser Keeper innerhalb derselben Panel-Seite; keine automatische Änderungserkennung zwischen Vergleich und Papierkorbaktion.

## V4 – Navigation, Vorschau und bedienbare Übertragungen (2026-11-02)
- Pro Panel Vorwärts-/Rückwärts-Verzeichnisverlauf und browserlokale Favoriten.
- Begrenzt lesende Dateivorschau mit serverseitig abgesicherten Pfaden.
- Kopieren/Verschieben mit Fortschritt und Abbruch zwischen ausgewählten Einträgen.
- Drag & Drop zwischen Panels mit Kopieren-/Verschieben-Entscheidung und Konfliktvorschau.
- Tastaturkürzel F3, F5 und Rücktaste (außerhalb von Eingabefeldern).
- Siehe `tests/V4_TESTS.md` für Einschränkungen und Prüfplan.

## V5 – Archive, Export, Filterprofile, Statistik
- ZIP erstellen/entpacken mit Pfadprüfung; CSV/JSON-Export der Paneldaten; Browser-Filterprofile; serverseitige Verzeichnisstatistik.

## V5.1 – Kopieren/Verschieben (05.11.2026)
- Behebung der fälschlich sichtbaren Fortschrittsanzeige `0 / 0` vor dem Start (`[hidden]` gegenüber CSS Grid).
- Deaktivierte Übertragungsschaltflächen werden sichtbar abgeblendet; Vorschaufehler mit HTTP-/PHP-Hinweis angezeigt.
- Neue schnelle, schreibfreie Auswahlvorschau (`transferSelectionPreview`): Für Ordner werden zunächst nur die obersten Einträge geprüft. Die Dateien in Unterordnern werden **bei der tatsächlichen Ausführung** geprüft und nach der Konfliktregel behandelt.
- Bei `7` markierten gemischten Dateien/Ordnern erfolgt die Übertragung Eintrag für Eintrag mit Fortschritt, Abbruch zwischen Einträgen, vollständiger Fehlerliste und Panel-Aktualisierung.
- Quelle und Ziel bleiben während eines offenen Dialogs fixiert; Änderungen hinter dem Dialog beeinflussen den Auftrag nicht.
- Aufwendige Transferaktionen können bis zu 600 Sekunden PHP-Laufzeit erhalten (PHP-/Webserverlimits können dies verkürzen).
- Asset-URLs versioniert, damit Browser nicht die alte JavaScript-/CSS-Version weiterverwenden.
- Neue Tests: `tests/test_transfer_selection_preview.php`, `tests/test_transfer_dialog_ui.py`, Cross-Drive-HTTP-Test.
