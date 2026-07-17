# Changelog – Blockwerk

Alle nennenswerten Änderungen pro Version. Das Format pro Eintrag: Version, Datum, Änderungen. Die installierte Version steht in der Datei `VERSION` und wird im Admin unter **Updates** angezeigt.

## 1.4.0 – 2026-07-17

- **Das CMS hat jetzt einen Namen: Blockwerk!** Der Name erscheint im Admin-Bereich, im Login, im Installer und in der Dokumentation.
- **E-Mail-Versand konfigurierbar:** Standardmäßig läuft der Versand wie bisher über den Mailserver des Hosters (PHP `mail()`). Neu: In den Einstellungen kann ein **eigener SMTP-Server** hinterlegt werden (Host, Port, SSL/STARTTLS, Benutzername, Passwort) – umgesetzt als schlanker SMTP-Client ohne Abhängigkeiten. Absender-Adresse und -Name sind frei wählbar.
- **Testmail mit einem Klick:** „Speichern & Testmail senden“ prüft den gewählten Versandweg sofort live – bei Fehlern wird die genaue Ursache angezeigt (z. B. die SMTP-Antwort bei falschem Passwort).
- Kontaktformulare nutzen automatisch den konfigurierten Versandweg.

## 1.3.0 – 2026-07-17

- **Kontaktformular-Block:** Formular mit Name/E-Mail/Telefon/Nachricht, frei wählbarem Empfänger (oder zentral aus den Einstellungen), eigenem Betreff, Button-Text und Erfolgsmeldung. Versand per E-Mail mit Reply-To des Absenders, Honeypot-Spamschutz und CSRF-Schutz.
- **Automatische Bildoptimierung:** Hochgeladene Bilder werden auf max. 1920 px Breite verkleinert (schnellere Website) und bekommen ein Thumbnail; Mediathek und Auswahldialog laden dadurch deutlich schneller.
- **SEO pro Seite:** Neuer Bereich in den Seiten-Eigenschaften – SEO-Titel, Meta-Beschreibung und „noindex“. Die Metatags (inkl. Open Graph) werden automatisch in den Seitenkopf eingefügt.
- **Einstellungen:** Neues Feld „E-Mail-Empfänger für Kontaktformulare“.
- **Datenbank-Migrationen:** Updates können jetzt auch neue Spalten in bestehenden Tabellen ergänzen (nicht mehr nur neue Tabellen) – läuft automatisch bei Installation und Update.

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
