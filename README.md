<img src="public/assets/img/logo.svg" alt="Blockwerk-Orange-Logo" width="72">

# Blockwerk Orange

**Blockwerk Orange** ist ein kleines, modernes und erweiterbares Content-Management-System — Inhalte werden aus Blöcken zusammengesteckt, per Drag & Drop, mit echter WYSIWYG-Ansicht. Ohne Framework, ohne Composer-Abhängigkeiten: einfach hochladen, Installations-Assistent durchklicken, loslegen.

## Funktionen

- **✨ KI-Assistent** – Seiten samt Texten, generierten Bildern und modernem Design einfach per Chat erstellen und ändern lassen (Claude-API über den zentralen Blockwerk-Dienst, Token-Guthaben pro Installation).
- **Ein-Datei-Installer** – `install.php` allein auf den Webspace laden und im Browser öffnen: Server-Check, das CMS-Paket wird automatisch heruntergeladen und entpackt, danach übernimmt der Einrichtungs-Assistent.
- **Install-Assistent** – Datenbank-Zugangsdaten eingeben, alle Tabellen und Beispieldaten werden automatisch angelegt (inkl. Admin-Konto).
- **Seitenverwaltung** – hierarchische Seitenstruktur; pro Seite wählbar, ob sie im Menü erscheint (inkl. Reihenfolge und Entwurfs-Status).
- **Layouts mit Design-Panel** – frei definierbare HTML-Grundgerüste mit Platzhaltern (`{{content}}`, `{{title}}`, `{{site_name}}`, `{{menu}}`, `{{template:key}}`, …). Pro Layout wählbar: Grundfarben per Color-Picker (Primär-, Akzent-, Text-, Hintergrund- und Flächenfarbe) sowie Schriften für Überschriften und Fließtext – alle Inhaltselemente richten sich automatisch danach (CSS-Variablen `--cms-primary` usw.).
- **Google Fonts, lokal** – Schriften werden einmalig von Google heruntergeladen und dauerhaft auf dem eigenen Server gespeichert (DSGVO-freundlich, keine Verbindung der Besucher zu Google).
- **Templates** – wiederverwendbare Bausteine (z. B. das Hauptmenü), die in Layouts oder anderen Templates eingebettet werden.
- **Mediathek** – Bild-/PDF-Upload mit Vorschau-Raster und Auswahldialog überall dort, wo Bilder gebraucht werden.
- **News & Events** – eigene Verwaltung mit Rich-Text-Editor, Beitragsbild, Kurzbeschreibung, Terminen und Orten; automatische Detailseiten unter `/news/…` und `/events/…`.
- **Drag-&-Drop-Inhalts-Editor** – Zeilen mit frei wählbaren Spalten im 12er-Raster, live in echten Proportionen dargestellt. Zeilen und Blöcke per Drag & Drop verschieben, Spaltenbreiten mit +/− anpassen, Blöcke per Klick bearbeiten.
- **Inhaltselemente** – Überschrift, Text (Rich-Text), Bild, Bildergalerie (Spaltenzahl, Lightbox, Bildunterschriften), Slider, vollbreiter Hero-Slider (Höhe, Abdunkelung, Overlay-Texte und Buttons), Button, Video (YouTube/Vimeo/MP4), Zitat, Akkordeon, News-Liste, Event-Liste, HTML, Trennlinie, Abstand – viele davon mit wählbaren **Designvorlagen**, die den Layout-Farben folgen.

## Voraussetzungen

- PHP 8.1 oder neuer (mit PDO-MySQL-Erweiterung)
- MySQL 5.7+ / MariaDB 10.3+
- Apache mit `mod_rewrite` (oder ein anderer Webserver mit entsprechender Rewrite-Regel)

## Installation

**Variante A – Ein-Datei-Installer (empfohlen):**

1. Nur die Datei `install.php` auf den Webspace hochladen (dorthin, wo die Domain hinzeigt).
2. Im Browser öffnen (`https://deine-domain.de/install.php`) — der Installer prüft den Server, lädt das CMS-Paket herunter und entpackt es.
3. Danach übernimmt der Einrichtungs-Assistent: Datenbank-Zugangsdaten, Website-Name, Admin-Konto. Fertig.

**Variante B – manuell:**

1. Projektdateien auf den Webserver hochladen.
2. Domain entweder direkt auf das Verzeichnis `public/` zeigen lassen **oder** auf das Projektverzeichnis — die mitgelieferte `.htaccess` leitet dann automatisch nach `public/` um.
3. Die Website im Browser öffnen → der Install-Assistent startet automatisch:
   - **Schritt 1:** Datenbank-Zugangsdaten eingeben (die Datenbank wird bei Bedarf angelegt).
   - **Schritt 2:** Namen der Website und Admin-Zugang festlegen.
4. Anmelden unter `/login` und im Admin-Bereich (`/admin`) loslegen.

## Lokale Entwicklung

```bash
php -S localhost:8000 -t public public/index.php
```

Danach im Browser `http://localhost:8000` öffnen (eine lokale MySQL/MariaDB-Instanz wird benötigt).

## Erweitern

- **Neuer Inhalts-Block:** Render-Logik in `app/Core/BlockRegistry.php` ergänzen und den passenden Eintrag in `blockDefs` in `public/assets/js/editor.js` anlegen (Label, Standardwerte, Eingabefelder) — fertig.
- **Neue Platzhalter:** in `app/Core/Renderer.php` (`replacePlaceholders()`) ergänzen.
- **Neue Admin-Bereiche:** Route in `app/Core/App.php` registrieren, Controller unter `app/Controllers/Admin/` (erbt von `AdminController` für den Login-Schutz) und View unter `app/Views/admin/` anlegen.
