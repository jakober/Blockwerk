# Changelog – Blockwerk Orange

Alle nennenswerten Änderungen pro Version. Das Format pro Eintrag: Version, Datum, Änderungen. Die installierte Version steht in der Datei `VERSION` und wird im Admin unter **Updates** angezeigt.

## 2.2.0 – 2026-08-07

Erster Teil des Shop-Ausbaus: Rechtssicherheit (Steuern, AGB/Widerruf, automatische Rechnung).

- **Mehrwertsteuer.** Neue Shop-Einstellung „Steuern": entweder keine Umsatzsteuer (Kleinunternehmer, § 19 UStG) oder Bruttopreise inkl. Mehrwertsteuer mit einstellbarem Standard-Steuersatz. Produkte können einen eigenen Steuersatz bekommen (sonst gilt der Standardsatz). Bei „inkl. MwSt." steht der Hinweis „inkl. MwSt., zzgl. Versandkosten" an Produkt, Warenkorb und Kasse, und die Rechnung weist den enthaltenen Steuerbetrag je Satz aus. Der Steuersatz einer Bestellung wird beim Kauf eingefroren – spätere Änderungen wirken sich nicht auf bereits ausgestellte Rechnungen aus. Wechselt der Shop zu „Kleinunternehmer" und es steht noch kein eigener Rechnungshinweis, wird der gesetzliche Hinweistext automatisch vorbefüllt.
- **AGB und Widerrufsbelehrung.** Wird der Shop aktiviert, legt das CMS automatisch zwei Beispielseiten mit Muster-Rechtstexten an (nach dem Vorbild von Impressum/Datenschutz, mit „[Bitte ergänzen]"-Markierungen). Die Kasse verlangt jetzt zwingend die Zustimmung zu AGB und Widerrufsbelehrung, bevor eine Bestellung abgeschickt werden kann.
- **Automatische Rechnung.** Jede Bestellung erhält ab sofort direkt eine fortlaufende Rechnungsnummer, und die Bestellbestätigung wird automatisch mit der Rechnung als PDF-Anhang verschickt – ganz ohne manuellen Klick im Backend.

## 2.1.0 – 2026-07-28

- **Zeilen-Vorlagen per Drag & Drop.** Die Spalten-Layouts oben in der Werkzeugleiste (1 Spalte, 2 gleiche, 3 gleiche, Breit/Schmal …) lassen sich jetzt genau wie Inhalts-Blöcke direkt an die gewünschte Stelle im Editor ziehen – zwischen zwei Zeilen oder auf eine bestehende Zeile (oberer/unterer Bereich entscheidet, ob davor oder danach eingefügt wird). Der Klick zum Anhängen am Ende bleibt weiterhin möglich.
- **Spalten innerhalb einer Zeile per Drag & Drop verschieben.** Über den neuen Ziehgriff (⠿) in der Spalten-Leiste lässt sich die Reihenfolge der Spalten einer Zeile ändern – z. B. „Bild links, Text rechts" zu „Text links, Bild rechts" tauschen, ohne die Inhalte einzeln neu einzufügen.

## 2.0.0 – 2026-07-28

Erste Veröffentlichung als eigenständiges Content-Management-System.

- **Block-Editor** – Seiten aus Zeilen, Spalten (12er-Raster) und Blöcken zusammenstellen, per Drag & Drop und in echter WYSIWYG-Vorschau. Über 25 Inhaltselemente von Überschrift und Text über Bildergalerie, Slider, Hero, Team, Preistabelle, Akkordeon und Karte bis zum Kontaktformular – viele mit Designvorlagen, die den Layout-Farben folgen.
- **KI-Assistent** – beschreibt man, was entstehen soll, plant die KI die Schritte und setzt sie um: Seiten samt Texten, generierten Bildern, Layouts, Designs und Beiträgen. Pflicht dabei: vollständige Websites statt Fragmente, Impressum und Datenschutzerklärung, barrierefreie Gestaltung (Alternativtexte, saubere Überschriften, ausreichender Kontrast) und ein abschließender Selbst-Check. Abgerechnet wird über ein Token-Guthaben, das an den Lizenzschlüssel gebunden ist (erhältlich unter blockwerk-orange.de/ki-guthaben).
- **Designs & Layouts** – Gesamt-Designs mit Farbschema und Gestaltungs-Bausteinen (Rundungen, Hero-Höhe, Abstände, Schriftstil, Button-Form) sowie frei definierbare Layouts mit Platzhaltern. Google-Schriften werden lokal gespeichert (DSGVO-freundlich).
- **Logo für jede Website** – Platzhalter `{{logo}}`, der in klassischen wie visuell gebauten Layouts funktioniert; einstellbar unter Einstellungen oder direkt durch die KI.
- **Inhalte** – Seitenbaum mit Menüsteuerung, News und Events mit Detailseiten, globale Blöcke, wiederverwendbare Templates, Mediathek mit Ordnern und Suche, Formular-Eingänge, Mehrsprachigkeit, Suche und Sitemap.
- **Shop (optional)** – Produkte mit Varianten und Staffelpreisen, Kategorien, Versandarten, Warenkorb, Kasse, Kundenkonten, Bestellverwaltung und Rechnungs-PDF; Zahlung per Rechnung, Vorkasse oder PayPal.
- **Betrieb** – Ein-Datei-Installer, Installations-Assistent, Seiten-Cache, Backup und Wiederherstellung, Update-Funktion direkt im Backend (auf Wunsch aus einer privaten Quelle mit Zugriffsschlüssel).
