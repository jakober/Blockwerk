# CMS

Ein kleines, modernes und erweiterbares Content-Management-System — ohne Framework, ohne Composer-Abhängigkeiten. Einfach hochladen, Installations-Assistent durchklicken, loslegen.

## Funktionen

- **Install-Assistent** – Datenbank-Zugangsdaten eingeben, alle Tabellen und Beispieldaten werden automatisch angelegt (inkl. Admin-Konto).
- **Seitenverwaltung** – hierarchische Seitenstruktur; pro Seite wählbar, ob sie im Menü erscheint (inkl. Reihenfolge und Entwurfs-Status).
- **Layouts** – frei definierbare HTML-Grundgerüste mit Platzhaltern (`{{content}}`, `{{title}}`, `{{site_name}}`, `{{menu}}`, `{{template:key}}`, …).
- **Templates** – wiederverwendbare Bausteine (z. B. das Hauptmenü), die in Layouts oder anderen Templates eingebettet werden.
- **Drag-&-Drop-Inhalts-Editor** – Zeilen mit frei wählbaren Spalten im 12er-Raster, live in echten Proportionen dargestellt. Inhalts-Blöcke (Überschrift, Text, Bild, Button, HTML, Trennlinie, Abstand) per Drag & Drop in die Spalten ziehen, per Klick bearbeiten, Spaltenbreiten mit +/− anpassen.

## Voraussetzungen

- PHP 8.1 oder neuer (mit PDO-MySQL-Erweiterung)
- MySQL 5.7+ / MariaDB 10.3+
- Apache mit `mod_rewrite` (oder ein anderer Webserver mit entsprechender Rewrite-Regel)

## Installation

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
