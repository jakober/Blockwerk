# Changelog – Blockwerk Orange

Alle nennenswerten Änderungen pro Version. Das Format pro Eintrag: Version, Datum, Änderungen. Die installierte Version steht in der Datei `VERSION` und wird im Admin unter **Updates** angezeigt.

## 2.6.0 – 2026-08-07

Versandarten einfacher gestalten, KI-Assistent kennt jetzt den gesamten Shop-Ausbau.

- **Versandarten neu gestaltet.** Gewichtsstaffeln werden nicht mehr als Freitext (z. B. „5:20; 20:50") eingegeben, sondern Zeile für Zeile über ein geführtes Formular („bis X kg" + Preis, beliebig viele Zeilen hinzufügen/entfernen) – wie schon bei den Staffelpreisen im Produktformular. Jede Versandart lässt sich einzeln aufklappen.
- **KI-Assistent kennt den ganzen Shop.** Der KI-Assistent kann jetzt auch den Steuer-Modus einstellen (Kleinunternehmer §19 UStG oder Bruttopreise inkl. MwSt.), Gutscheincodes anlegen/ändern, Kategorien nachträglich bearbeiten sowie SEO-Felder und einen eigenen Steuersatz für Produkte setzen. Er weiß außerdem über automatische Rechnungen, Überverkaufsschutz, die automatisch angelegten Rechtstexte (AGB/Widerrufsbelehrung) sowie SEPA-Lastschrift als Zahlungsart Bescheid – Rückerstattungen, Versand-Tracking und Bewertungen bleiben bewusst reine Backend-Aktionen des Nutzers.

## 2.5.0 – 2026-08-07

Neue Zahlungsart: SEPA-Lastschrift ganz ohne Zahlungsdienstleister.

- **SEPA-Lastschrift.** Kund:innen zahlen per Lastschriftmandat (Kontoinhaber + IBAN, mit Prüfsummen-Validierung); die Bestellung merkt sich Mandatsreferenz und -datum. Unter „Shop → Lastschriften" sammelst du die offenen Lastschriften und lädst sie als SEPA-Sammeldatei (pain.008) herunter, die du direkt bei deiner Bank einreichst – ganz ohne Prozent-Gebühren eines Zahlungsdienstleisters. Voraussetzung: Gläubiger-ID und eigene IBAN in den Shop-Einstellungen hinterlegen.

## 2.4.0 – 2026-08-07

Dritter Teil des Shop-Ausbaus: Umsatz & Komfort (Gutscheine, Wunschliste, Bewertungen, Adressbuch, geräteübergreifender Warenkorb).

- **Gutscheincodes.** Prozentuale oder feste Rabatte, wahlweise mit Mindestbestellwert, Gültigkeitszeitraum und Nutzungslimit – verwaltbar unter „Shop → Gutscheine". Warenkorb und Kasse zeigen den Rabatt direkt an, die Bestellung merkt sich Code und Rabattbetrag dauerhaft.
- **Merkliste.** Eingeloggte Kund:innen können Produkte über das Herz-Symbol auf Karten und Produktseite merken und unter „Merkliste" wiederfinden.
- **Produktbewertungen.** Wer ein Produkt gekauft hat, kann es mit 1–5 Sternen und Text bewerten. Neue Bewertungen erscheinen erst nach Freigabe im Backend („Shop → Bewertungen", mit Hinweis-Badge bei offenen Bewertungen); die Produktseite zeigt die Durchschnittsbewertung.
- **Adressbuch.** Kund:innen können mehrere Lieferadressen speichern, eine davon als Standard markieren und beim Bestellen per Auswahl übernehmen.
- **Geräteübergreifender Warenkorb.** Der Warenkorb eingeloggter Kund:innen wird zusätzlich in der Datenbank gespiegelt – nach einem Login auf einem anderen Gerät wird er automatisch mit dem dortigen Warenkorb zusammengeführt.

## 2.3.0 – 2026-08-07

Zweiter Teil des Shop-Ausbaus: Kernfunktionen vervollständigt (Bestand, Rückerstattung, Versand, SEO, Paginierung).

- **Überverkaufsschutz.** Die Kasse prüft jetzt den Lagerbestand vor dem Anlegen einer Bestellung und lehnt mit klarer Fehlermeldung ab, wenn nicht genug auf Lager ist. Bei niedrigem Bestand (1–5 Stück) zeigen Produktseite und Kachel „nur noch N auf Lager" an. Wird eine Bestellung storniert, wird der Bestand automatisch zurückgebucht – wird die Stornierung rückgängig gemacht, wieder abgezogen.
- **Rückerstattung.** Neuer Bestellstatus „Erstattet" mit eigener Aktion im Bestelldetail: Bei PayPal-Zahlungen läuft die Rückerstattung direkt über die PayPal-API (ganz oder teilweise), bei Rechnung/Vorkasse ist es eine reine Status-Änderung. In beiden Fällen wird der Lagerbestand zurückgebucht und der Kunde per E-Mail informiert.
- **Versand-Tracking.** Sendungsnummer und Tracking-Link lassen sich im Bestelldetail hinterlegen; beim Wechsel auf „Versendet" bekommt der Kunde sie automatisch in der Status-E-Mail mitgeschickt.
- **SEO für Produkte und Kategorien.** Eigene SEO-Titel und Meta-Beschreibungen wie bei Seiten, inklusive Sitemap-Einträgen für alle aktiven Produkte und Kategorien.
- **Paginierung im Katalog.** Kategorieseiten mit vielen Produkten zeigen jetzt eine Seiten-Navigation statt alles auf einmal zu laden (Seitengröße einstellbar).

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
