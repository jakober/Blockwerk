# Changelog

Alle nennenswerten Änderungen pro Version. Das Format pro Eintrag: Version, Datum, Änderungen. Die installierte Version steht in der Datei `VERSION` und wird im Admin unter **Updates** angezeigt.

## 1.2.0 – 2026-07-17

- **WYSIWYG-Editor:** Die Seite sieht im Editor jetzt genauso aus wie im Frontend – Blöcke werden serverseitig mit der echten Render-Engine dargestellt (inkl. Layout-Farben und Schriften). Bearbeitungs-Leisten erscheinen erst beim Überfahren mit der Maus.
- **Gestaltung ohne CSS:** Jeder Block hat ein „Gestaltung“-Panel (Abstand oben/unten, Innenabstand, Ausrichtung, Text- und Hintergrundfarbe, Eckenrundung).
- **Zeilen-Einstellungen:** Klick auf die Zeilen-Leiste öffnet Zeilen-Gestaltung – vollbreite Hintergrundfarbe (farbige Sektion) und Innenabstände.
- **Eigenes CSS:** optional pro Layout (Design-Panel) und pro Seite (CSS-Knopf im Editor), live in der Vorschau.
- **Update-Funktion:** Neuer Admin-Bereich „Updates“ – prüft die verfügbare Version im Repository und aktualisiert die Installation per Klick. `config/` und `public/uploads/` bleiben dabei unangetastet, neue Datenbank-Tabellen werden automatisch angelegt.
- Fehlerbehebung: Standardwerte von Auswahl-Optionen (z. B. News-Darstellung) griffen nicht, wenn die Option fehlte.

## 1.1.0 – 2026-07-17

- **Mediathek:** Upload für Bilder/PDFs mit Vorschau-Raster, URL-Kopieren und Auswahldialog überall, wo Bilder gebraucht werden.
- **Rich-Text-Editor** mit Toolbar für Textblöcke und Beitragsinhalte.
- **Neue Inhaltselemente:** Bildergalerie (Spalten, Lightbox, Bildunterschriften), Slider, vollbreiter Hero-Slider (Höhe, Abdunkelung, Overlay-Texte/Buttons), Video (YouTube/Vimeo/MP4), Zitat, Akkordeon, News-Liste, Event-Liste; Bild- und Button-Block erweitert.
- **Designvorlagen** pro Element (z. B. Überschrift mit Akzentlinie, Galerie als Karten) – folgen automatisch den Layout-Farben.
- **Layout-Design-Panel:** Grundfarben per Color-Picker und Schriftwahl für Überschriften/Fließtext (CSS-Variablen `--cms-*`).
- **Google Fonts lokal:** Schriften werden einmalig heruntergeladen und DSGVO-freundlich vom eigenen Server ausgeliefert.
- **News & Events** mit eigener Verwaltung und automatischen Detailseiten (`/news/…`, `/events/…`).
- **Editor:** Zeilen per Drag & Drop verschieben, Element-Listen (Galerie/Slides/Abschnitte) im Inspektor.
- **Ein-Datei-Installer** `install.php`: Server-Check, lädt und entpackt das CMS-Paket, leitet in den Assistenten weiter und löscht sich selbst.

## 1.0.0 – 2026-07-17

- Erstes Release: Install-Assistent (Datenbank → Website → Admin-Konto), hierarchische Seitenverwaltung mit Menü-Option, Layouts mit Platzhaltern, Templates (z. B. Hauptmenü), Drag-&-Drop-Editor mit frei wählbaren Spalten im 12er-Raster und Live-Ansicht, Session-Login, CSRF-Schutz.
