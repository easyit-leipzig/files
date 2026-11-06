# Files V5 – Funktionsumfang und Grenzen

- ZIP-Erstellung mit PHP-Extension `zip`, maximal 10.000 Einträge und 1 GiB unkomprimierte Daten. Auswahl kann Dateien und Ordner umfassen; Pfade innerhalb des autorisierten Laufwerks werden bewahrt.
- ZIP-Entpacken nur in den aktuellen Panelordner; bestehende Dateien/Ordner werden nicht überschrieben; absolute Pfade, `..`, doppelte Einträge und Unix-Symlinks werden abgelehnt.
- Export CSV (UTF-8 BOM, Semikolon) und JSON enthält die gerade angezeigten, ggf. gefilterten Tabelleneinträge; nicht automatisch alle Dateien auf dem Datenträger.
- Filterprofile im browserlokalen `localStorage`; Namens- und Endungsfilter sind V4-bedingt global für beide Panels.
- Statistik zählt bis 100.000 Einträge und kann rekursiv sein; keine harten Ressourcenbudgets für einen PHP-Lauf.

## Manueller Windows-Test
1. Im linken Panel zwei Dateien und einen Ordner markieren, ZIP erstellen.
2. ZIP markieren und im leeren Zielordner entpacken; Wiederholung muss vor Überschreiben warnen.
3. ZIP mit `../`-Eintrag oder Unix-Symlink darf nicht entpackt werden.
4. CSV und JSON bei aktivem Filter exportieren.
5. Filterprofil speichern, verändern, wiederherstellen und löschen.
6. Statistik mit/ohne Unterordner prüfen.
7. V1–V4-Funktionen erneut manuell testen.
