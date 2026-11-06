# easyIT Files V5.1 – Prüfprotokoll Kopieren/Verschieben

Datum: 05.11.2026

## Ausgangssituation

Im Dialog „Dateien und Ordner kopieren“ verharrte die Anzeige bei „Vorschau wird berechnet …“. Darunter war eine irreführende Fortschrittsleiste `0 / 0` sichtbar. Die Schaltfläche „Aktion ausführen“ sah aktiv aus, war aber bis zur vollständigen rekursiven Vorschau deaktiviert. Bereits bei einem großen markierten Ordner konnte die rekursive PHP-Vorschau lang dauern oder an Berechtigungen scheitern, ohne dass die UI einen sinnvollen Startstatus zeigte.

## Korrektur

1. Die CSS-Klasse `.v4-progress` überschreibt nicht mehr das native HTML-Attribut `[hidden]`.
2. Deaktivierte Schaltflächen werden erkennbar dargestellt.
3. Das neue POST-API `transferSelectionPreview` prüft nur die obersten markierten Einträge; die rekursive Ordnerverarbeitung erfolgt bei `transferTreeApply`.
4. Die Vorschau zeigt konkrete Fehler statt dauerhaft auf „wird berechnet“ zu verbleiben. Bei einem ungültigen JSON des PHP-Servers wird eine verständliche Meldung angezeigt.
5. Kopieren/Verschieben verarbeitet jeden markierten Haupteintrag einzeln mit Fortschritt `n / Gesamtzahl`; Abbruch wirkt zwischen Einträgen. Eine gestartete Ordnerübertragung wird abgeschlossen.
6. Quellen/Ziele bleiben auch bei Änderungen der Panelnavigation hinter dem Dialog unverändert.
7. Externe CSS/JS-URLs tragen neue Versionsparameter gegen Browsercache.
8. Für PHP-Transferaktionen wird das Ausführungslimit, soweit serverseitig erlaubt, auf 600 Sekunden erhöht.

## Durchgeführte Tests

- Alle 13 PHP-CLI-Testdateien inklusive neuem `test_transfer_selection_preview.php`: bestanden.
- 29 HTTP-Integrationstests über einen echten PHP-Testserver: bestanden.
- HTTP-Test mit zwei freigegebenen Testlaufwerken `C:` und `E:`, sieben markierten Einträgen (sechs Einzeldateien, ein Ordner einschließlich Unterordner): Vorschau, sieben getrennte Transfers, Datenprüfung und Quellerhaltung erfolgreich.
- HTTP-Test mit existierenden Zielen: `merge` für Ordner, `skip` für Einzeldateien erfolgreich.
- Browser-Regressionsprüfung des Transferdialogs mit simulierten API-Antworten: fünf Prüfgruppen bestanden (Vorschau, versteckter Fortschritt, aktivierte Schaltfläche, sieben Requests, Panel-Aktualisierung).
- Bestehende Browser-Regressionsprüfung von Auswahl/Löschen: fünf Prüfungen bestanden.
- Hilfe-Integrierungstest: bestanden (Test erkennt auch versionierte Script-URL).
- JavaScript- und PHP-Syntaxprüfung: bestanden.

## Einschränkungen

Keine direkte Ausführung auf dem Windows-Produktivsystem mit seinen tatsächlichen Laufwerken `C:` und `E:`. Für sehr umfangreiche Ordner ist die Prüfung vor dem Start bewusst nur oberflächlich; eine einzelne umfangreiche Ordnerübertragung bleibt während ihres PHP-Requests in Bearbeitung und kann nicht mitten im Ordner abgebrochen werden. Netzwerk-/Server-/PHP-FPM-Zeitlimits und Benutzerrechte können einen Transfer weiterhin einschränken. Auf dem Testserver war PHP `zip` nicht geladen; ZIP-Archivfunktionen wurden nicht produktiv geprüft.

Das PHP-Programm benötigt kein Python. Python-Tests sind nur optionale Entwicklerwerkzeuge.
