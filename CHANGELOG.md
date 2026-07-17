# Changelog – Blockwerk

Alle nennenswerten Änderungen pro Version. Das Format pro Eintrag: Version, Datum, Änderungen. Die installierte Version steht in der Datei `VERSION` und wird im Admin unter **Updates** angezeigt.

## 1.7.0 – 2026-07-17

- **Menü-Vorlagen:** Das Menü (Baumstruktur aus den Seiten, beliebig tiefe Unterpunkte über „Übergeordnete Seite“) kann jetzt in vier Darstellungen ausgegeben werden: `{{menu}}` Hover-Dropdown (Standard, mit Aufklapp-Pfeilen und Flyout für tiefere Ebenen), `{{menu:mega}}` **Mega-Menü** (breites Panel, Unterseiten als Spalten mit ihren Unterpunkten), `{{menu:vertical}}` vertikale Baum-Liste für Sidebar/Footer, `{{menu:simple}}` nur oberste Ebene.
- Im Template-Formular gibt es dafür Einfüge-Knöpfe (Klick setzt den Platzhalter an die Cursor-Position); die Styles folgen automatisch den Design-Farben und funktionieren in allen Themes.

## 1.6.4 – 2026-07-17

- **Fehlerbehebung:** „Neues Template“ und „Template bearbeiten“ führten zu einem Fehler 500. Ursache war ein interner Variablen-Namenskonflikt in der View-Engine (der Daten-Schlüssel `template` kollidierte mit der internen Pfad-Variable). Zusätzlich wurden alle Admin-Seiten einmal komplett durchgetestet.

## 1.6.3 – 2026-07-17

- **Verständliche Datenbank-Fehlermeldungen im Install-Assistenten:** Häufige MySQL-Fehler (1130 „Host is not allowed to connect“, 1045 „Access denied“, 1044 fehlende Rechte, Server nicht erreichbar) werden jetzt in Klartext erklärt – inklusive konkreter Lösungsschritte statt kryptischer SQLSTATE-Meldungen.

## 1.6.2 – 2026-07-17

- Das Repository hat jetzt einen `main`-Branch als stabile Release-Quelle. Installer und Updater zeigen wieder auf die dauerhaften `main`-URLs.

## 1.6.1 – 2026-07-17

- **Fehlerbehebung Installation/Updates:** Der Ein-Datei-Installer und der Updater zeigten auf den GitHub-Branch `main`, den es im Repository (noch) nicht gibt – der Download schlug deshalb mit „konnte nicht heruntergeladen werden“ fehl. Die Standard-URLs zeigen jetzt auf den tatsächlichen Branch.

## 1.6.0 – 2026-07-17

- **Design-Galerie:** Neuer Admin-Bereich „Designs“ mit sechs mitgelieferten Gesamt-Designs, die die komplette Optik der Website ändern – Kopfbereich, Menü, Farben, Formen, Schriftstil. Die Inhalte bleiben unverändert und passen sich automatisch an. Mit einem Klick aktiviert (ersetzt das Standard-Layout, mit Sicherheitsabfrage), Vorschaukarten zeigen jedes Design vorab.
- Die sechs Designs: **Klar** (hell, Indigo – Standard), **Mitternacht** (dunkel, Violett/Türkis), **Magazin** (Zeitungs-Stil mit Serifen), **Natur** (Erd-/Grüntöne, weiche Formen), **Studio** (Schwarz-Weiß, minimalistisch), **Ozean** (Blau mit Farbverlauf).

## 1.5.0 – 2026-07-17

- **Benutzerverwaltung:** Neuer Admin-Bereich „Benutzer“ – weitere Benutzer anlegen, Benutzernamen ändern, Passwörter zurücksetzen und Benutzer löschen. Schutzmechanismen: Das eigene Konto und der letzte verbleibende Benutzer können nicht gelöscht werden.
- **Eigenes Profil:** Klick auf den eigenen Namen oben rechts öffnet das eigene Konto – dort lässt sich das Admin-Passwort jederzeit ändern (leer lassen = unverändert).

## 1.4.1 – 2026-07-17

- **Blockwerk-Logo:** Schlichtes SVG-Logo (vier Blöcke, einer wird gerade „eingesetzt“) – eingebunden in Admin-Seitenleiste, Login, Installations-Assistent, Ein-Datei-Installer und README, außerdem als Favicon im Admin-Bereich.

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
