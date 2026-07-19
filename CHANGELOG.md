# Changelog – Blockwerk Orange

Alle nennenswerten Änderungen pro Version. Das Format pro Eintrag: Version, Datum, Änderungen. Die installierte Version steht in der Datei `VERSION` und wird im Admin unter **Updates** angezeigt.

## 1.54.0 – 2026-07-19

- **Schutz beim Löschen verwendeter Bilder.** Beim Löschen in der Mediathek prüft das CMS jetzt, ob das Bild noch irgendwo eingebunden ist – in **Seiten, globalen Blöcken, News/Events, Layouts (inkl. Logo), Templates sowie Shop-Produkten und -Kategorien**. Ist das der Fall, listet der Bestätigungsdialog die Fundstellen auf („Seite: Startseite", „Layout: Standard", …) und fragt ausdrücklich nach, bevor gelöscht wird. Zusätzlich verhindert der Server das versehentliche Löschen ohne Bestätigung. (Hinweis zur Beruhigung: **Umbenennen und in Ordner verschieben** ändern nur den Anzeigenamen bzw. die Ordnerzuordnung – sie berühren die Datei nie und können eingebundene Bilder daher nicht brechen. Nur Löschen entfernt die Datei wirklich.)

## 1.53.12 – 2026-07-19

- **Testrelease** zur endgültigen Bestätigung der automatischen Update-Anzeige (ab 1.53.11): sollte innerhalb von ~2 Minuten von selbst im Dashboard erscheinen – ohne „Nach Updates suchen" und ohne Neuladen-Tricks. (Keine funktionalen Änderungen.)

## 1.53.11 – 2026-07-19

- **Automatische Update-Anzeige jetzt endgültig zuverlässig.** Die Hintergrund-Prüfung wurde vom Browser zwischengespeichert – dadurch kam dauerhaft die alte Antwort „kein Update", obwohl der Server das Update längst erkannt hatte. Der Prüf-Aufruf wird jetzt mit „nicht zwischenspeichern"-Vorgaben und einem Cache-Buster ausgeliefert, sodass immer frisch geprüft wird. (Die Diagnose unter `…/admin/update/status?force=1` hatte bestätigt: Server und GitHub-Abruf funktionieren – es lag nur am Browser-Cache.)

## 1.53.10 – 2026-07-19

- **Testrelease** zur Prüfung der automatischen Update-Anzeige (ab 1.53.9): sollte innerhalb von ~2 Minuten von selbst im Dashboard erscheinen – ohne „Nach Updates suchen". (Keine funktionalen Änderungen.)

## 1.53.9 – 2026-07-19

- **Update-Prüfung reagiert jetzt schnell.** Die Online-Prüfung wird nun höchstens alle 2 Minuten (statt alle 6 Stunden) im Hintergrund wiederholt – ein neues Release erscheint damit zeitnah von selbst im Dashboard, ohne „Nach Updates suchen".
- **Zuverlässigerer Abruf:** Die Versionsnummer wird jetzt zuerst direkt von der Datei (`raw.githubusercontent.com`) gelesen (kein Rate-Limit); die GitHub-API dient nur noch als Reserve. Ein Cache-Buster sorgt für frische Werte.
- **Diagnose-Ausgang:** `…/admin/update/status?force=1` liefert (für Administratoren) eine Klartext-Übersicht, ob die Update-Quelle erreichbar ist – hilfreich zur Fehlersuche.

## 1.53.8 – 2026-07-19

- **Kleines Testrelease** zur Prüfung der automatischen Update-Anzeige (ab 1.53.7): Dieses Update sollte beim nächsten Dashboard-Aufruf von selbst erscheinen – ohne „Nach Updates suchen". (Keine funktionalen Änderungen.)

## 1.53.7 – 2026-07-19

- **Update-Anzeige zeigt jetzt auch nach einem Update zuverlässig neue Versionen.** Bisher merkte sich das System die zuletzt online gefundene Version bis zu 6 Stunden. Direkt nach einem Update stand dort die gerade installierte Version – also „aktuell", obwohl es schon eine neuere geben konnte. Neu: Nach jedem Update wird der Prüf-Cache geleert, und sobald sich die installierte Version geändert hat, wird sofort erneut online geprüft. So erscheint ein verfügbares Update ab jetzt automatisch – ohne 6-Stunden-Verzögerung und ohne „Nach Updates suchen".
- Der System-Block im Dashboard aktualisiert seinen Status („aktuell" ↔ „Update verfügbar") jetzt auch live per Hintergrund-Prüfung.

## 1.53.6 – 2026-07-19

- **Kleines Testrelease** zur Prüfung der neuen automatischen Update-Anzeige: Dieses Update sollte ab Version 1.53.5 von selbst im Dashboard und am Menüpunkt „Updates" erscheinen – ohne „Nach Updates suchen". (Keine funktionalen Änderungen.)

## 1.53.5 – 2026-07-19

- **Automatische Update-Anzeige jetzt zuverlässig.** Die Prüfung „beim Betreten des Backends" lief zuletzt über einen verzögerten Server-Hintergrundprozess, der auf manchen Servern (nginx/php-fpm-Konstellationen) nicht durchlief – dann erschien trotz vorhandenem Update nichts. Neu: Die Seite lädt sofort, und direkt danach fragt der Browser im Hintergrund den Status ab (`/admin/update/status`). Ist ein Update verfügbar, erscheinen **Banner im Dashboard und die Markierung am Menüpunkt „Updates" sofort – ohne Neuladen und ohne „Nach Updates suchen"**. Solange noch keine Version bekannt ist, wird die Prüfung zügig wiederholt; sobald sie klappt, höchstens alle 6 Stunden.

## 1.53.4 – 2026-07-19

- **Kleines Testrelease**, um die neue automatische Update-Anzeige zu prüfen: Nach dem Betreten des Backends sollte dieses Update ohne „Nach Updates suchen" im Dashboard und am Menüpunkt „Updates" erscheinen. (Keine funktionalen Änderungen.)

## 1.53.3 – 2026-07-19

- **Admin-Listen auf dem Handy: kein horizontales Scrollen mehr.** Die als Karten gestapelten Tabellen erbten noch die Mindestbreite (560px) der normalen Tabellen – dadurch war die Karte breiter als der Bildschirm. Das ist behoben; lange, umbruchlose Werte (z. B. Lizenzschlüssel, Template-Keys) brechen jetzt sauber um.
- **Menü öffnet auch per Klick aufs Logo.** Im Backend öffnet/schließt sich das Menü auf schmalen Bildschirmen jetzt nicht nur über das Menü-Symbol, sondern auch mit einem Tipp auf das Logo „Blockwerk Orange".
- **Automatische Update-Prüfung repariert (und entzerrt).** Die Prüfung lief bisher direkt beim Seitenaufbau und mit sehr kurzem Timeout – auf manchen Servern schlug sie deshalb fehl (kein Update sichtbar) und machte das Backend zäh. Jetzt läuft der Online-Check **im Hintergrund, nachdem die Seite ausgeliefert wurde** (blockiert also keinen Klick mehr) und mit ausreichend Zeit. Das Ergebnis erscheint beim nächsten Seitenaufruf automatisch im Dashboard und am Menüpunkt „Updates" – ohne „Nach Updates suchen" zu drücken. Nach einem Fehlversuch wird alle 15 Minuten erneut geprüft, im Erfolgsfall höchstens alle 6 Stunden.

## 1.53.2 – 2026-07-19

- **Spalten im visuellen Layout richtig ausgerichtet.** In Zeilen mit mehreren Spalten (z. B. drei Karten nebeneinander) stand die inhaltsreichste Spalte weiter oben als die anderen, weil der Seiteninhalt versehentlich vertikal zentriert wurde (diese Zentrierung ist eigentlich nur für die Kopfzeile gedacht). Der Seiteninhalt fließt jetzt – wie im Editor – von oben; alle Spalten beginnen auf gleicher Höhe. Damit stimmt die Frontend-Ansicht wieder mit dem Editor überein.
- **Kein doppelter Abstand vor farbigen Abschnitten.** Zwischen einem Block (z. B. Hero) und einem direkt folgenden farbigen Abschnitt gab es einen zu großen Zwischenraum (Zeilen-Abstand + Abschnitts-Innenabstand). Der Zeilen-Abstand entfällt jetzt vor farbigen Abschnitten – der Abschnitt bringt seinen eigenen Innenabstand mit.

## 1.53.1 – 2026-07-19

- **Alle Admin-Listen jetzt voll mobiltauglich.** Wie bereits die Seitenübersicht werden nun auch **News, Events, Formulare, Produkte, Kategorien, Bestellungen, Layouts, Templates, Schriften** und die **Kunden-Lizenzen** (KI-Verwaltung) auf schmalen Bildschirmen als übersichtliche Karten dargestellt: Statt einer überlaufenden Tabelle steht jede Zeile als eigene Karte, mit der Spaltenüberschrift jeweils über dem Wert. Kein horizontales Scrollen und keine überlappenden Buttons mehr. Am Desktop bleibt die gewohnte Tabellenansicht unverändert.

## 1.53.0 – 2026-07-19

- **Automatische Update-Prüfung.** Beim Betreten des Backends wird (höchstens alle 6 Stunden, im Hintergrund und mit kurzem Timeout) geprüft, ob eine neue Version verfügbar ist. Ist das der Fall, erscheint im **Dashboard** ein deutlicher Hinweis mit Direktlink zu den Updates, und der Menüpunkt **Updates** bekommt eine kleine Markierung.
- **Updates-Seite zeigt die verfügbare Version sofort** – ohne erst „Nach Updates suchen" klicken zu müssen (der Knopf bleibt für eine erneute Prüfung).
- **Dashboard überarbeitet:** Der „Schnellstart"-Block ist raus. Stattdessen gibt es einen **Schnellzugriff** (KI-Assistent, Neue Seite, Mediathek, Website ansehen), einen **System**-Block (installierte Version + Update-Status + PHP-Version) und – bei aktivem Shop – zusätzliche Kennzahlen (Produkte, Bestellungen).

## 1.52.1 – 2026-07-19

- **Hilfe-Knopf (unten rechts) wieder entfernt** – auf Wunsch. Das schnelle Modell für die Planung und einfache Schritte bleibt erhalten.

## 1.52.0 – 2026-07-19

- **Plan-Modus wählt das Modell pro Schritt.** Schon bei der Planung entscheidet die KI je Schritt, ob ein **schnelles Modell** ausreicht (kurze Texte, kleine Änderungen) oder das starke Modell nötig ist (ganze Seite, Bilder, komplexe Struktur). Einfache Schritte werden dann automatisch mit dem schnellen Modell umgesetzt – schneller und günstiger. In der Checkliste sind solche Schritte mit einem ⚡ markiert.

## 1.51.0 – 2026-07-19

- **Hilfe-Knopf im Backend.** Unten rechts gibt es jetzt einen festen ✨-Knopf. Beim Anklicken öffnet sich ein kleines Hilfe-Fenster, das direkt erklärt, was man auf der **aktuellen Seite** tun kann – und in dem man weitere Fragen zur Seite stellen kann. (Sichtbar für Admins, wenn der KI-Assistent eingerichtet ist.)
- **Schnelleres Modell für einfache Aufgaben.** Für leichte Aufgaben (die Planungs-Phase des KI-Assistenten und die neue Hilfe) nutzt der KI-Dienst jetzt automatisch ein schnelleres, günstigeres Modell. Es ist in der **KI-Verwaltung** unter „Schnelles Modell" einstellbar (leer = immer das Hauptmodell).

## 1.50.0 – 2026-07-19

- **KI kann Versandarten anlegen und ändern.** Der KI-Assistent versteht jetzt Versandkosten: er kann Versandarten anlegen/aktualisieren – pauschal, **gewichtsabhängig** (Staffeln „bis X kg → Preis") und auf bestimmte **Länder** begrenzt. Beispiel-Anweisung: „Lege eine Versandart an: bis 5 kg 20 €, bis 20 kg 50 €, nur nach Deutschland und Österreich."

## 1.49.0 – 2026-07-19

- **Länder-Auswahl mit Suchfeld statt Komma-Liste.** Bei den Versandarten wählst du die Länder jetzt über eine **durchsuchbare Mehrfach-Auswahl** (tippen, anklicken, als Chips hinzufügen/entfernen) statt sie kommagetrennt einzutippen. Auch an der **Kasse** wählt der Kunde sein Land über dasselbe durchsuchbare Feld – **Deutschland ist vorausgewählt**. Die Liste ist alphabetisch, wobei **Deutschland, Österreich, Schweiz** immer oben stehen. Das Feld funktioniert auch ohne JavaScript (normales Mehrfach-/Auswahlfeld). An der Kasse stehen nur Länder zur Wahl, in die tatsächlich geliefert wird (bzw. alle, wenn eine weltweite Versandart existiert).

## 1.48.0 – 2026-07-19

- **KI-Assistent arbeitet jetzt mit Plan.** Bevor etwas umgesetzt wird, zerlegt die KI deine Anfrage zuerst in einen **Plan aus einzelnen Schritten** (ohne dabei schon etwas zu ändern). Anschließend werden die Schritte **automatisch nacheinander** als jeweils **eigene Anfrage** abgearbeitet. Im Chat erscheint eine **Checkliste**, die live mitläuft: aktueller Schritt mit Ladeanzeige, erledigte mit ✓ – inklusive der Ergebnis-Karten je Schritt (z. B. **generiertes Bild als Vorschau** und „Ansehen"-Link). Bricht ein Schritt ab, kannst du ihn **erneut versuchen** oder **überspringen**. Nebeneffekt: Das frühere „zu viele Arbeitsschritte" tritt praktisch nicht mehr auf, weil jeder Schritt sein eigenes Budget hat.

## 1.47.0 – 2026-07-19

- **Gewichts- und länderabhängige Versandkosten.** Versandarten können jetzt **Gewichtsstaffeln** haben (z. B. bis 5 kg 20 €, bis 20 kg 50 €) und auf bestimmte **Länder** beschränkt werden. Für unterschiedliche Länderpreise legt man einfach mehrere Versandarten an (z. B. „Versand Deutschland" und „Versand EU"). An der Kasse wählt der Kunde sein Land, woraufhin nur passende Versandarten mit dem zum Warenkorbgewicht passenden Preis erscheinen. Ohne Staffeln gilt weiter der Pauschalpreis; „gratis ab" bleibt möglich.
  - **Produktgewicht** wird jetzt in **kg** eingegeben (vorher Gramm) und fließt ins Warenkorbgewicht ein. Produkte ohne Gewicht zählen zur niedrigsten Staffel.
  - Eingabe der Staffeln kompakt als „kg:€" je Stufe, per Semikolon getrennt (z. B. `5:20; 20:50`); Länder kommagetrennt (leer = alle Länder).
  - Der KI-Assistent kann beim Anlegen/Ändern von Produkten nun auch das **Gewicht (in kg)** setzen.

## 1.46.0 – 2026-07-19

- **KI kann jetzt Staffelpreise und Varianten anlegen.** Der Shop unterstützte Staffelpreise, Produkteigenschaften/Varianten (mit Preisaufschlag) sowie Cross-Selling/Zubehör längst – der KI-Assistent konnte sie beim Anlegen/Ändern von Produkten aber nicht setzen und meldete daher fälschlich, das ginge nicht. Die Produkt-Werkzeuge kennen nun `tier_prices`, `variants`, `cross_sell` und `accessories`; die KI kann Staffelpreise (ab Menge X günstiger), Varianten wie Größe/Farbe (optional mit Aufschlag) und verknüpfte Produkte selbst einpflegen.
- **Bugfix:** Beim Ändern eines Produkts über die KI wurden zuvor vorhandene Staffelpreise/Varianten versehentlich gelöscht, wenn sie nicht mitübergeben wurden. Nicht angegebene Felder bleiben jetzt erhalten.

## 1.45.1 – 2026-07-19

- **Fehler 500 im KI-Assistenten behoben.** Auf Installationen, bei denen die neue Verlaufs-Tabelle noch nicht angelegt war, warf der KI-Assistent einen Serverfehler (500). Der Verlaufs-Speicher legt seine Tabelle jetzt bei Bedarf selbst an und arbeitet grundsätzlich absturzsicher – im schlimmsten Fall fehlt kurz der gespeicherte Verlauf, aber der KI-Assistent lädt normal. Auch die Installations-Übersicht in der KI-Verwaltung ist gegen Datenbankfehler abgesichert.

## 1.45.0 – 2026-07-19

- **KI-Assistent merkt sich das Gespräch.** Der Chat-Verlauf wird jetzt pro Backend-Nutzer serverseitig gespeichert: Die KI erinnert sich an frühere Anweisungen, und die Nachrichten sind auch nach dem Neuladen der Seite noch da. Über „🗑 Neues Gespräch" lässt sich der Verlauf löschen (die KI beginnt dann ohne alten Kontext).
- **Installations-Übersicht in der KI-Verwaltung.** Auf der Anbieter-Domain zeigt die KI-Verwaltung jetzt pro Lizenz an, auf **welchem System (Domain)** das CMS läuft und **wann es zuletzt aktiv** war. Dazu meldet jede Installation ihre Domain beim Aufruf des KI-Dienstes mit (nur an den Anbieter-Dienst).

## 1.44.2 – 2026-07-19

- **Seiten per Drag & Drop zuverlässig umsortieren.** Bisher wurde die Einfüge-Linie zwar angezeigt, aber beim Loslassen passierte oft nichts – weil das „Ablegen" genau über der Linie technisch nicht ausgelöst wurde. Jetzt lässt sich überall im Baum sicher ablegen (auch direkt auf der Linie).
- **Neue Seiten kommen ans Ende.** Eine neu angelegte Seite reiht sich jetzt unten auf ihrer Ebene ein (statt ganz oben).

## 1.44.1 – 2026-07-19

- **Seitenverwaltung mobil repariert.** Auf schmalen Bildschirmen überlappten in der Seitenliste die Aktions-Buttons (Inhalt/Eigenschaften/Löschen) den Seitentitel. Jetzt stehen Titel/Slug in der ersten Zeile und die Buttons darunter in voller Breite (mit Umbruch) – nichts überlappt mehr.

## 1.44.0 – 2026-07-19

- **KI kann das Logo jetzt selbst setzen.** Neues Werkzeug **`set_logo`**: der KI-Assistent legt ein Bild als Website-Logo im Kopf (l-brand-Block des Layouts) fest – gezielt, ohne das komplette Layout neu zu bauen. Optional lässt sich zugleich der Website-Name neben dem Logo ein-/ausblenden. Damit funktioniert „Nimm das als Logo und passe die Farben an" in einem Rutsch (zusammen mit `set_layout_design`).

## 1.43.1 – 2026-07-19

- **KI-Assistent: mehr Arbeitsschritte pro Anfrage.** Die Obergrenze wurde von 8 auf 16 Runden angehoben – mehrteilige Aufgaben (z. B. „Logo hinterlegen und die Farben passend ändern") laufen jetzt durch, statt vorzeitig mit „Zu viele Arbeitsschritte" abzubrechen.
- **Ehrliche Rückmeldung am Limit:** Wird die Schrittgrenze doch erreicht, gilt das nicht mehr als Fehler – die bereits ausgeführten Schritte (z. B. erstelltes Design, geladenes Bild) werden als erledigt angezeigt, mit dem Hinweis, den Rest kurz nachzufassen.

## 1.43.0 – 2026-07-19

- **KI weist Schriften jetzt selbst dem Layout zu.** Bisher konnte der KI-Assistent eine Google-Schrift zwar herunterladen (`load_font`), aber nicht dem Layout zuordnen – er musste den Nutzer bitten, das manuell zu tun. Neu: das Werkzeug **`set_layout_design`** setzt Schriften je Slot (alle Überschriften, Fließtext oder einzeln H1–H6) und optional die Design-Farben (Primär/Akzent/Text/Hintergrund/Fläche) direkt im Layout. Noch nicht installierte Schriften werden dabei automatisch geladen. So kann die KI ein Layout jetzt komplett gestalten – inklusive passender Typografie – ohne manuellen Zwischenschritt.

## 1.42.4 – 2026-07-19

- **nginx: Timeout für lange KI-Anfragen dokumentiert.** Bei Installationen hinter nginx brachen längere KI-Aktionen (Agent-Loop mit mehreren Runden + Bildgenerierung) mit „Verbindung fehlgeschlagen" ab, weil nginx PHP-Anfragen standardmäßig nach 60 s abschneidet (Apache erlaubt 300 s). Die mitgelieferte `nginx.conf.example` und die `ai-server`-nginx-Vorlage enthalten jetzt `fastcgi_read_timeout 300s;` im PHP-Block – passend zum internen `set_time_limit(300)` des KI-Assistenten.

## 1.42.3 – 2026-07-19

- **Hinweis für Plesk/cPanel ergänzt:** `nginx.conf.example` und README erklären jetzt, dass bei Verwaltungspanels die nginx-Konfiguration nicht von Hand angelegt werden darf (das Panel erzeugt die vhosts selbst und ignoriert manuelle Blöcke). Empfohlener Weg: in den PHP-Einstellungen der Domain „PHP ausführen als: FPM von Apache" wählen – dann wirkt die mitgelieferte `.htaccess`; bei reinem nginx den Dokumentenstamm auf `public/` setzen und eine `try_files`-Anweisung ergänzen.

## 1.42.2 – 2026-07-19

- **nginx-Unterstützung dokumentiert:** Neue Beispiel-Konfiguration `nginx.conf.example` im Projektverzeichnis plus Abschnitt „Installation auf nginx" in der README. Hintergrund: nginx wertet keine `.htaccess` aus (die gilt nur für Apache) und führt PHP nur über PHP-FPM aus – fehlt der `location ~ \.php$`-Block, wird `install.php`/`index.php` heruntergeladen statt ausgeführt. Die Vorlage zeigt den korrekten Serverblock (docroot = `public/`, PHP-FPM, Front-Controller) samt Hinweis zum Ein-Datei-Installer bei SSH-Zugang.

## 1.42.1 – 2026-07-19

- **Anbieter-/Super-Admin-Domain umgestellt:** Die zentrale Anbieter-Verwaltung (Super-Admin-Rechte für KI-Token, Lizenzen usw. unter **KI-Verwaltung** / `/admin/ai-admin`) sowie die Auslieferung des KI-Dienstes beim Update laufen jetzt über **blockwerk-orange.de**. Die bisherige Domain **blockwerk.bairle.de** wurde entfernt. Auch die Standard-Adresse des KI-Dienstes zeigt nun auf die neue Domain (weiterhin in den Einstellungen überschreibbar).

## 1.42.0 – 2026-07-19

- **Designs unterscheiden sich jetzt in JEDEM Element.** Jedes Design hat eine eigene „Stilrichtung", die alle Inhaltselemente prägt – Überschriften, Text/Infoboxen, Buttons, Zitate, Akkordeon, News-/Event-Karten, Preise, Team, Trenner und Hero sehen pro Design komplett anders aus (nicht mehr nur Farbe/Rundung):
  - **Blockwerk Orange** – klare Karten mit feiner Umrandung und Akzentlinien.
  - **Kontrast** – blockig & kantig: dicke Rahmen, Versal-Überschriften, harte Kanten, versetzte Schatten.
  - **Atelier** – weich & rund: große Rundungen, weiche Schatten, Pillen-Datums-Badges.
  - **Journal** – Magazin/redaktionell: Serifen-Überschriften, feine Linien, Initial-Buchstabe (Drop-Cap), News als Linien-Liste, großes zentriertes Zitat.
- **Neues Design „Diagonal — schräg & dynamisch":** Hier kommen überall Schrägen vor – farbige Bereiche und der Hero bekommen **automatisch** eine schräge Unterkante, Karten haben angeschnittene Ecken, Buttons und Akzente sind schräg. (Nutzt dieselbe Schräg-Mechanik wie die optionale Zeilen-Form.)
- **KI wählt die Stilrichtung mit:** Beim Erstellen eines individuellen Designs kann der KI-Assistent jetzt auch die Stilrichtung (weich / kantig / redaktionell / schräg / klar) passend zur Beschreibung wählen – so bekommen auch KI-Designs eine eigene Element-Handschrift.

## 1.41.1 – 2026-07-19

- **Eigene Designs löschen:** Eigene bzw. von der KI erstellte Designs lassen sich unter **Designs** über den Knopf „Löschen" (nur bei eigenen Designs, mit „Eigenes"-Kennzeichnung) wieder entfernen. Löscht man das gerade aktive Design, wird automatisch sauber auf „Blockwerk Orange" zurückgeschaltet. Mitgelieferte Designs sind nicht löschbar.

## 1.41.0 – 2026-07-19

- **KI kann online nachschauen & Bilder herunterladen.** Der KI-Assistent kann jetzt eine öffentliche Webseite als **Vorlage** abrufen (Titel, Überschriften, Texte, Bild-Liste) und daraus eine ähnliche Seite mit **eigenen, umformulierten Texten** bauen. Auf Wunsch lädt er einzelne **Bilder herunter** und legt sie in der Mediathek ab, um sie direkt einzubinden. Beispiele: „Schau dir die Seite XY an und bau sie ähnlich nach" oder „lade die Bilder von dort und binde sie ein". Neue Werkzeuge `fetch_url` und `download_image`.
  - **Urheberrechts-Hinweis:** In der KI-Oberfläche und in jeder Antwort weist die KI darauf hin, dass fremde Inhalte und Bilder urheberrechtlich geschützt sein können und die **Verantwortung für die Verwendung beim Nutzer** liegt. Texte werden grundsätzlich selbst umformuliert, nicht 1:1 kopiert.
  - **Sicherheit:** Nur öffentliche http/https-Adressen werden abgerufen; interne/private Netzwerkadressen sind gesperrt (SSRF-Schutz), Downloads sind größenbegrenzt und Bilder werden per MIME-Typ geprüft.

## 1.40.0 – 2026-07-19

- **Designs verändern jetzt die komplette Optik – nicht nur Farben.** Ein Design steuert über neue „Design-Tokens" die gesamte Gestaltung: Eckenrundung, Hero-Höhe, Inhaltsbreite, Abstände, Basis-Schriftgröße, Überschriften-Stil (Stärke, Laufweite, Groß-/Kleinschreibung, Serif/Sans), Button-Form (rund/Pille/kantig) und Schatten. So sehen Designs wirklich grundverschieden aus, aber in sich stimmig.
  - **Neue mitgelieferte Designs:** „**Blockwerk Orange**" (Hausdesign), „**Kontrast — groß & mutig**" (Vollbild-Hero 100 vh, kantig, große Versal-Überschriften), „**Atelier — weich & rund**" (sehr rund, Pillen-Buttons, sanfte Verläufe & Schatten) und „**Journal — Magazin**" (Serifen-Überschriften, schmale Lesebreite, zentrierter Kopf). Die bisherigen reinen Farbvarianten wurden entfernt.
  - Der **Hero-Slider** übernimmt die Höhe des Designs automatisch (z. B. 100 vh im großen Design); pro Hero weiterhin überschreibbar (Höhe 0 = automatisch).
- **KI erstellt individuelle Designs:** Der KI-Assistent kann per Beschreibung ein komplettes Design bauen („mach ein minimalistisches, großes, kantiges Design in Blau") – es wird unter **Designs** gespeichert, aktiviert und ist dort löschbar. Neues Werkzeug `create_design`.
- **Zeilen: Schräge, Welle & Eckenrundung.** Farbige Sektionen (Zeilen mit Hintergrundfarbe) können jetzt oben und/oder unten eine **Schräge** oder **Welle** in derselben Farbe bekommen (diagonaler bzw. geschwungener Übergang). Zusätzlich lässt sich die **Eckenrundung** pro Zeile und pro Block mit Hintergrundfarbe einstellen – standardmäßig automatisch nach dem gewählten Design, aber frei änderbar (0 = eckig). Funktioniert im Inhalts-Editor und im visuellen Layout-Baukasten.

## 1.39.0 – 2026-07-19

- **Produkte: Staffelpreise, Varianten und Cross-Selling.** Das Produktformular hat drei neue Bereiche:
  - **Staffelpreise (Mengenrabatt):** Ab einer wählbaren Menge gilt ein günstigerer Stückpreis (z. B. „ab 5 Stück: 15,90 €"). Auf der Produktseite erscheint eine Rabatt-Tabelle; im Warenkorb wird der passende Preis automatisch berechnet.
  - **Eigenschaften/Varianten:** Frei definierbare Eigenschaften wie „Größe" mit Ausprägungen (M, L, XL …), jeweils optional mit **Aufpreis** (auch negativ). Der Kunde wählt sie auf der Produktseite; der Preis aktualisiert sich live. Dieselbe Variante ist im Warenkorb eine eigene Position (z. B. „T-Shirt (Größe: XL)").
  - **Cross-Selling &amp; Zubehör:** In den Produkteinstellungen lassen sich weitere Produkte als „Passt dazu" (Cross-Selling) und als „Zubehör" auswählen – sie werden auf der Produktseite angezeigt.
  - Preise werden intern in Cent geführt; die neuen Angaben liegen als JSON am Produkt und wandern korrekt in Warenkorb, Kasse und Bestellung (inkl. Varianten-Bezeichnung in der Bestellposition).

## 1.38.0 – 2026-07-19

- **Shop standardmäßig aus – zentral aktivierbar:** Der Shop ist jetzt klar opt-in. Der **Ein/Aus-Schalter liegt in den allgemeinen Einstellungen** (Einstellungen → „🛒 Shop"). Erst wenn er aktiviert ist, erscheinen:
  - der **Shop-Bereich im Backend-Menü** (Produkte, Kategorien, Bestellungen, Shop-Einstellungen),
  - die **Shop-Seiten auf der Website** (Startseite, Kategorien, Produkte, Warenkorb, Kasse),
  - die **Shop-Werkzeuge des KI-Assistenten** (Produkte/Kategorien anlegen).
  - Ist der Shop aus, sind die Shop-Verwaltungsseiten nicht erreichbar (ein direkter Aufruf leitet mit Hinweis in die Einstellungen). Die eigentlichen Shop-Detaileinstellungen (Hauptseite, Zahlungs- &amp; Versandarten) bleiben unter „Shop → Shop-Einstellungen".

## 1.37.0 – 2026-07-19

- **KI-Assistent kann jetzt den Shop pflegen:** Der KI-Assistent kann **Produkte und Kategorien anlegen** (und Produkte aktualisieren). Beispiele: „Lege eine Kategorie ‚Accessoires' an", „Erstelle 3 Produkte für Kaffee mit Beschreibung und Preis" oder „Erhöhe den Preis von Produkt X auf 24,90 €". Auf Wunsch generiert die KI dabei auch passende Produktbilder und ordnet die Produkte den richtigen Kategorien zu. Neue Werkzeuge: `list_shop_categories`, `create_shop_category`, `list_shop_products`, `create_shop_product`, `update_shop_product`. Hinweis der KI: Der Shop muss unter „Shop-Einstellungen" aktiviert sein, damit die Produkte auf der Website erscheinen.

## 1.36.0 – 2026-07-18

- **Shop-Funktion (optional zuschaltbar):** Blockwerk Orange kann jetzt einen vollständigen Online-Shop betreiben. Neuer Bereich **„Shop"** im Backend mit:
  - **Produkten** (Name, Preis, Streichpreis, Artikelnummer, Bild, Kurz- &amp; Langbeschreibung, Lagerbestand, Kategorie, „empfohlen") und **Kategorien** (mit Unterkategorien).
  - **Shop-Einstellungen:** Shop aktivieren, **Hauptseite wählen** (darunter liegen automatisch Kategorie- und Produktseiten, z. B. `/shop/kategorie/…`), Währung, **Zahlungsarten** (Kauf auf Rechnung, Vorkasse mit Bankverbindung, **PayPal**) und **Versandarten** (mit „gratis ab"-Grenze).
  - **Storefront** im Design der Website: Startseite mit Kategorien &amp; empfohlenen Produkten, Kategorieseiten mit **Filtern** (Suche, Preis von/bis, Sortierung), Produktdetailseiten, **Warenkorb** und **Kasse** mit Adresse, Versand- und Zahlungsart-Auswahl sowie Bestellbestätigung.
  - **PayPal** über die offiziellen PayPal-Buttons (Client-ID &amp; Secret in den Shop-Einstellungen, Sandbox- oder Live-Modus).
  - **Bestellverwaltung** im Backend: Bestellungen ansehen, Status setzen (Neu/Bezahlt/Versendet/Storniert), Kundendaten und Positionen einsehen. Optionale Benachrichtigungs-E-Mail bei neuen Bestellungen.
  - Ist der Shop nicht aktiviert, ändert sich nichts an der Website.

## 1.35.0 – 2026-07-18

- **Spalten auch auf dem Smartphone nebeneinander:** In den Zeilen-Einstellungen (Inhalts-Editor **und** visueller Layout-Baukasten) gibt es jetzt die klare Auswahl **„Spalten auf dem Smartphone"** mit drei Optionen: „Automatisch untereinander (empfohlen)", „Nebeneinander lassen" (die Spalten bleiben auch auf schmalen Handys in einer Reihe) und „Ab eigener Breite stapeln …" (eigener Umbruchpunkt). Standardmäßig stapeln die Spalten wie bisher – nur wo gewünscht bleiben sie nebeneinander. Die **Smartphone-Vorschau** im Editor zeigt das Verhalten jetzt korrekt an (bislang wurden dort immer alle Spalten gestapelt). Ersetzt das frühere, wenig verständliche Zahlenfeld.

## 1.34.0 – 2026-07-18

- **Schriftart je Überschriften-Ebene wählbar:** In den Layout-Design-Einstellungen (dort, wo auch die Farben eingestellt werden) lässt sich jetzt zusätzlich zur allgemeinen Schrift „für Überschriften" und „für Fließtext" optional für **jede einzelne Ebene H1–H6** eine eigene installierte Schrift festlegen (aufklappbarer Bereich „Einzelne Überschriften separat"). Wird für eine Ebene nichts gewählt, gilt die allgemeine Überschriften-Schrift; ist auch die nicht gesetzt, die Standard-/Systemschrift. Gilt sowohl im Frontend als auch in der Editor-Vorschau. Für eigenes CSS stehen die Ebenen als Variablen `--cms-font-h1` … `--cms-font-h6` bereit.

## 1.33.0 – 2026-07-18

- **Einheitliche Bestätigungs-Dialoge im Backend:** Überall, wo bisher die schlichten Browser-Fenster („alert"/„confirm") verwendet wurden – beim Löschen von Seiten, Medien, Layouts, Schriften, Templates, Benutzern, News/Events, beim Zurücksetzen des Menüs, beim Aktivieren eines Designs usw. – erscheint jetzt der schöne, zum Backend passende Dialog. Bestätigungen für unwiderrufliche Aktionen haben einen roten Knopf.
- **Wiederherstellung mit Fortschrittsbalken:** Beim Einspielen einer Sicherung füllt sich jetzt ein Balken, der den Fortschritt in Prozent und die geschätzte Restzeit anzeigt (erst „Hochladen", dann „Einspielen"). Die Backup-Datei wird dabei in kleinen Häppchen hochgeladen – dadurch entfällt das frühere Problem mit dem **„Ungültigen/abgelaufenen Formular-Token"** bei größeren Sicherungen, und es gibt keine Server-Größenbeschränkung mehr.
- **Wiederherstellung lässt die Version unverändert:** Eine eingespielte Sicherung setzt alle Inhalte, Seiten, Medien und Einstellungen auf den Stand des Backups zurück – die **installierte Blockwerk-Version bleibt aber erhalten**. Nach dem Import wird das Datenbank-Schema automatisch an den aktuellen Programmstand angepasst, sodass auch ältere Sicherungen problemlos laufen.

## 1.32.0 – 2026-07-18

- **Seiten per Drag & Drop sortieren und verschachteln:** Die Seitenverwaltung zeigt die Seiten jetzt als Baum. Über den Ziehpunkt (⠿) lässt sich jede Seite mit der Maus verschieben:
  - **Reihenfolge ändern:** an den oberen oder unteren Rand einer anderen Seite ziehen – die Seite wird davor bzw. danach eingeordnet.
  - **Zur Unterseite machen:** auf die Mitte einer anderen Seite ziehen – die Seite wird deren Unterseite.
  - **Wieder zur Hauptseite machen:** an den Rand einer Seite auf der obersten Ebene ziehen.
  - Änderungen werden sofort gespeichert (kurze Bestätigung „✓ Reihenfolge gespeichert"); Verschieben in die eigene Unterseite wird verhindert. Die neue Reihenfolge gilt auch für das Menü.

## 1.31.0 – 2026-07-18

- **Backup wiederherstellen:** Unter **Updates** gibt es jetzt neben „Backup herunterladen" die neue Karte **„Wiederherstellen"**. Dort lädt man eine zuvor heruntergeladene Backup-ZIP hoch und spielt sie komplett zurück: **Datenbank** (alle Inhalte, Seiten, Einstellungen) und **Uploads** (Medien & Schriften) werden auf den Stand der Sicherung zurückgesetzt. Die Konfigurationsdatei (Datenbank-Zugang) bleibt bewusst unverändert, damit die Verbindung der Installation erhalten bleibt. Vor dem Einspielen erscheint eine Sicherheitsabfrage.
- **Logo in der Update-Oberfläche korrigiert:** Das Blockwerk-Zeichen oben in der Update-Ansicht zeigt jetzt – wie im übrigen Backend – drei Blöcke in der dunklen Logo-Schriftfarbe und nur den Block oben rechts in Orange (statt aller vier in Orange). Der Schriftzug „Blockwerk Orange" darunter erscheint jetzt im helleren Orange.

## 1.30.0 – 2026-07-18

- **Standard-Layout festlegen:** Bei mehreren Layouts lässt sich jetzt eines als Standard markieren. Das erste angelegte Layout ist automatisch Standard; unter **Layouts** kann per Klick auf „★ Als Standard" jederzeit ein anderes gewählt werden (die bisherige Markierung wechselt automatisch – es gibt immer genau ein Standard-Layout). Das Standard-Layout ist mit einem orangefarbenen „★ Standard"-Zeichen markiert.
  - **Neue Seiten** übernehmen das Standard-Layout jetzt automatisch als Vorauswahl – natürlich weiterhin frei änderbar. Zusätzlich gibt es im Layout-Auswahlfeld die Option „Standard-Layout (automatisch)", mit der eine Seite dauerhaft dem jeweils aktuellen Standard folgt.
  - Wird das Standard-Layout gelöscht, rückt automatisch das nächste nach, damit immer ein Standard existiert. Bestehende Installationen erhalten die Markierung beim Update automatisch (das erste Layout wird Standard).

## 1.29.2 – 2026-07-18

- **KI-Assistent: Fehlermeldung nach ausgeführter Anweisung behoben.** Rief die KI ein Werkzeug ohne Parameter auf (z. B. Vorlagen oder globale Blöcke auflisten), meldete Anthropic beim nächsten Schritt der Anweisung `messages…tool_use.input: Input should be an object` – die Anweisung wurde zwar korrekt ausgeführt, doch statt der Abschlussmeldung erschien der Fehler. Ursache: Der zentrale KI-Dienst wandelte beim Weiterreichen den leeren Werkzeug-Aufruf `{}` in ein leeres Array `[]` um, das die API ablehnt. Der Dienst stellt leere Objekte an allen relevanten Stellen (`tool_use.input` im Gesprächsverlauf, `input_schema.properties` in den Werkzeug-Definitionen) jetzt zuverlässig wieder her. **Hinweis:** Die Korrektur betrifft den zentralen KI-Dienst – nach einem einmaligen Update von `blockwerk.bairle.de` funktioniert der KI-Assistent auf allen angebundenen Installationen wieder fehlerfrei.

## 1.29.1 – 2026-07-18

- **Horizontaler Scrollbalken im Frontend behoben:** Zwei Ursachen sorgten für ungewolltes seitliches Scrollen auf der Website:
  - Das **Mega-Menü-Panel** ragte über den rechten Rand (durch Padding, das nicht zur Breite zählte, und eine ungenaue Positionierung des vollbreiten Panels). Es sitzt jetzt exakt bündig über die Inhaltsbreite.
  - **Vollbreite Elemente** (Hero, farbige Sektionen) sind bei sichtbarem vertikalem Scrollbalken minimal breiter als der Inhalt. Ein globaler Schutz auf Wurzel-Ebene verhindert das seitliche Scrollen jetzt in allen Templates – ohne den fixierten Kopfbereich (sticky) oder das vertikale Scrollen zu beeinträchtigen. Betrifft alle Designs und den visuellen Baukasten.

## 1.29.0 – 2026-07-18

- **Schönere Scrollbalken im Backend:** Überall dort, wo im Admin-Bereich gescrollt wird (Seitenleiste/Menü, Blöcke-Palette und Eigenschaften im Editor, lange Dialoge), erscheinen jetzt dezente, schlanke Scrollbalken im Orange-Design statt der Standard-Browser-Balken – auf dunklen Flächen wie der Seitenleiste hell abgestimmt. Betrifft nur das Backend; die Website selbst bleibt unverändert.

## 1.28.5 – 2026-07-18

- **Hero bündig auch im visuellen Baukasten:** Die Abstands-Korrektur für Hero/Vollbreit-Sektionen ganz oben gilt jetzt auch für visuell gebaute Layouts (Inhaltsbereich `.bwl-content`) – der Hero-Slider wird nicht mehr durch den oberen Innenabstand nach unten geschoben. Normale Seiten behalten ihren gewohnten oberen Abstand.

## 1.28.4 – 2026-07-18

- **Hero-Abstand-Korrektur wirkt jetzt sofort:** Der Fix aus 1.28.3 (kein Abstand über einem Hero/einer vollbreiten Sektion ganz oben) steckte in der pro-Design gespeicherten CSS – er griff dadurch erst nach erneutem Aktivieren des Designs. Die Regel liegt jetzt in der global eingebundenen Stylesheet-Datei und wirkt direkt nach dem Update, ohne dass das Design neu aktiviert werden muss.

## 1.28.3 – 2026-07-18

- **Kein Abstand mehr über dem Hero:** Bei den Gesamt-Designs entstand über einem Hero oder einer vollbreiten Sektion ganz oben auf der Seite eine schmale Lücke zwischen Kopfbereich und Inhalt (der obere Abstand des Inhaltsbereichs `.t-main`). Beginnt eine Seite mit einem Hero/einer vollbreiten Sektion, sitzt dieser jetzt bündig unter dem Kopfbereich. Normale Seiten behalten ihren gewohnten oberen Abstand.
- **Zeilen im Editor per Klick markieren:** Um eine Zeile zu bearbeiten, muss man nicht mehr genau auf den Zeilentitel klicken – ein Klick auf eine freie Stelle der Zeile (neben den Blöcken) markiert sie jetzt und öffnet ihre Einstellungen. Klicks auf Blöcke, Knöpfe oder Textfelder verhalten sich wie gewohnt.

## 1.28.2 – 2026-07-18

- **KI-Fehler endgültig behoben:** Der Fehler „tools.6.custom.input_schema.properties: Input should be an object" kam vom zentralen KI-Dienst selbst: Beim Weiterreichen an die Claude-API wurden leere Objekte durch die interne Verarbeitung wieder zu leeren Listen. Der Dienst stellt sie jetzt vor dem Senden korrekt als Objekte wieder her. (Betrifft nur den Anbieter-Server – dort einmal aktualisieren, damit der KI-Assistent aller Installationen wieder funktioniert.)

## 1.28.1 – 2026-07-18

- **KI-Fehler behoben:** Seit 1.28.0 brach jede KI-Anfrage mit „tools.6.custom.input_schema.properties: Input should be an object" ab. Ursache: Zwei der neuen Werkzeuge (globale Blöcke/Templates auflisten) haben keine Eingabefelder – das leere Feld wurde fälschlich als Liste statt als Objekt übermittelt. Jetzt korrigiert; die KI funktioniert wieder.
- **Blockwerk-Orange-Logo als Download:** Unter **Designs** gibt es jetzt den Bereich „Logo & Markenzeichen" – das Logo als SVG (verlustfrei skalierbar) zum Herunterladen, wahlweise mit Schriftzug „Blockwerk Orange" oder nur die Bildmarke.

## 1.28.0 – 2026-07-18

- **Der KI-Assistent kann jetzt fast alles bearbeiten:** Neben Seiten und Layouts beherrscht die KI nun auch
  - **News & Events** – Beiträge anlegen und ändern (Titel, Kurzbeschreibung, Inhalt, Beitragsbild; bei Events Beginn/Ende und Ort),
  - **Globale Blöcke** – wiederverwendbare Inhaltsbereiche erstellen und ändern (wirken überall, wo sie eingebettet sind),
  - **Templates** – wiederverwendbare HTML-Bausteine anlegen und bearbeiten (das Menü-Template bleibt dem Menü-Designer vorbehalten),
  - **Schriften** – eine Google-Schrift per Auftrag herunterladen und DSGVO-konform lokal speichern.
  Beispiele: „Schreib eine News zur Eröffnung", „Leg ein Event für den 20.12. um 18 Uhr an", „Lade die Schrift Poppins", „Baue einen globalen Aktionsbalken". Alle Änderungen laufen durch dieselbe Prüfung wie die manuelle Bearbeitung; bei globalen Blöcken wird der alte Stand als Version gesichert.

## 1.27.1 – 2026-07-18

- **Scroll-Animationen behoben:** Animierte Blöcke (z. B. „von links einfahren") blitzten beim Laden kurz auf und blendeten dann wieder aus – das sah aus, als würde die Animation nicht funktionieren. Ursache: Die nötige Kennzeichnung wurde erst vom Skript am Seitenende gesetzt. Jetzt passiert das sofort im Seitenkopf – die Blöcke starten unsichtbar und fahren erst beim Scrollen sauber ein. Zusätzlicher Notfallschutz: Sollte das Skript einmal nicht laden, werden alle Inhalte trotzdem angezeigt (nie unsichtbar hängen). Wichtig: Im Editor sind die Animationen weiterhin ausgeschaltet – sichtbar werden sie über „Vorschau ↗" bzw. auf der Website.

## 1.27.0 – 2026-07-18

- **Vier neue Gesamt-Designs:** Die Design-Auswahl hat jetzt zehn Themes. Neu dabei:
  - **Blockwerk Orange** – das Design in den Blockwerk-Orange-Farben: warmes Orange, dunkelbrauner Kopfbereich, goldener Akzent.
  - **Beere** – kräftiges Beerenrot mit violettem Akzent und weichem Verlauf.
  - **Sand & Stein** – ruhige Beige-/Steintöne mit Petrol-Akzent, zurückhaltend und hochwertig.
  - **Graphit** – Anthrazit mit Smaragd-Akzent, technisch und modern.
  Wie gewohnt ändert ein Klick die komplette Optik (Kopfbereich, Menü, Farben, Formen); die Inhalte bleiben unverändert und folgen automatisch den neuen Farben.

## 1.26.0 – 2026-07-18

- **Scroll-Animationen für Inhalte:** Jeder Block kann jetzt beim Scrollen erscheinen – im Gestaltungs-Panel unter „Beim Scrollen einblenden": Einblenden (Fade), von unten/links/rechts einfahren oder sanft vergrößern. Dezent umgesetzt (sanfte 0,7 s), barrierefrei (respektiert die System-Einstellung „Bewegung reduzieren") und ohne JavaScript bleibt alles sichtbar. Im Editor-Canvas laufen die Animationen bewusst nicht – auf der Website (Vorschau ↗) schon.
- **KI nutzt Animationen mit:** Der KI-Assistent kennt die neuen Scroll-Animationen und setzt sie bei neuen Seiten und Änderungen dezent ein (z. B. Karten-Reihen von unten einfahren lassen).
- **Formular zentrieren funktioniert jetzt:** Die Ausrichtung „Zentriert" im Gestaltungs-Panel zentriert nun auch das Kontaktformular selbst (inklusive Absende-Knopf) – nicht mehr nur die Texte. Gleiches gilt für rechtsbündig.
- **Editor: Seitenleisten laufen mit:** Blöcke-Palette (links) und Eigenschaften (rechts) bleiben beim Arbeiten immer sichtbar – gescrollt wird jetzt innerhalb des Seiteninhalts. Sind Palette oder Eigenschaften selbst länger als der Bildschirm, scrollen sie unabhängig in sich.

## 1.25.0 – 2026-07-18

- **Mediathek mit Ordnern:** Dateien lassen sich jetzt in Ordner sortieren – Ordner anlegen, umbenennen und löschen (Dateien bleiben dabei erhalten) direkt in der Mediathek. Beim Hochladen landet die Datei automatisch im gerade geöffneten Ordner, per Bearbeiten-Dialog kann sie jederzeit in einen anderen Ordner verschoben werden.
- **Umbenennen, Alt-Text & Titel:** Klick auf ein Bild (oder den ✎-Knopf) öffnet den Bearbeiten-Dialog: Anzeigename ändern, Alt-Text (Bildbeschreibung für Suchmaschinen/Screenreader) und Titel pflegen – auch für PDFs. Die Datei-URL bleibt beim Umbenennen stabil, nichts geht kaputt.
- **Suche in der Mediathek:** Neues Suchfeld durchsucht Name, Alt-Text und Titel – auch der Bild-Auswahldialog im Editor hat jetzt Suche und Ordner-Filter.
- **KI kennt die Mediathek:** Der KI-Assistent sieht Ordner und Alt-Texte, prüft vor dem Generieren, ob passende Bilder vorhanden sind (spart Token-Guthaben), und mit dem neuen Werkzeug `list_media` kann er gezielt suchen – Aufträge wie „Nimm die Bilder aus dem Ordner Teamfotos für die Team-Seite" funktionieren jetzt.

## 1.24.0 – 2026-07-18

- **KI kann jetzt Layouts ändern:** Der KI-Assistent hat zwei neue Werkzeuge – `get_layout` (Layout lesen) und `update_layout` (visuelles Layout ändern). Damit funktionieren Aufträge wie „Passe den Footer an" oder „Füge auf allen Seiten eine Kontaktbox über dem Footer ein": Die KI liest das Layout, fügt die Sektion an der richtigen Stelle (vor dem Footer, nach dem Inhaltsbereich) ein und speichert – die Änderung gilt sofort auf allen Seiten mit diesem Layout.
- **Mit Sicherheitsnetz:** Layout-Änderungen laufen durch dieselbe Validierung wie der Baukasten; das CMS erzwingt, dass der Inhaltsbereich (l-content) genau einmal erhalten bleibt – kaputte Layouts werden abgelehnt und die KI korrigiert sich. Klassische HTML-Layouts kann die KI nur lesen, nicht ändern.

## 1.23.1 – 2026-07-18

- **„API-Key verschwindet" behoben:** Nach dem Speichern in der KI-Verwaltung zeigt das Schlüssel-Feld jetzt deutlich den gespeicherten Zustand – grüner Badge „✓ hinterlegt" am Label und der maskierte Schlüssel als Platzhalter im Feld („gespeichert: ••••9999 – zum Ändern neuen Schlüssel eingeben"). Aus Sicherheitsgründen wird der Klartext bewusst nie wieder angezeigt; ein leeres Feld lässt den gespeicherten Wert unverändert.
- **OPcache-Fehler behoben:** Auf Servern mit aktivem OPcache konnte direkt nach dem Speichern noch die alte Konfiguration gelesen werden – der Schlüssel wirkte dadurch verloren. Die Datei wird jetzt nach dem Schreiben sofort im Cache invalidiert und zur Kontrolle frisch zurückgelesen; schlägt das fehl, erscheint eine klare Fehlermeldung statt einer falschen Erfolgsmeldung.

## 1.23.0 – 2026-07-18

- **🗝 KI-Verwaltung im Backend (nur Anbieter-Domain):** Neuer Menüpunkt unter System – kein FTP mehr nötig. Dort werden die API-Schlüssel (Anthropic für den Chat, OpenAI für Bilder), Modelle, der Token-Preis pro Bild und das Rate-Limit eingetragen; das CMS schreibt die Dienst-Konfiguration (`ai-server/config.php`) automatisch. Gespeicherte Schlüssel werden maskiert angezeigt, leere Felder behalten den vorhandenen Wert.
- **Kunden-Lizenzen direkt im Backend:** Lizenzen anlegen (Schlüssel wird generiert), Guthaben aufladen, sperren/aktivieren und Verbrauch einsehen – alles in der KI-Verwaltung. Per „Hier nutzen" lässt sich eine Lizenz mit einem Klick als Lizenz der eigenen Installation übernehmen.
- Der Menüpunkt und die Verwaltung erscheinen ausschließlich auf der Anbieter-Domain; auf Kunden-Installationen existiert der Bereich nicht.

## 1.22.3 – 2026-07-18

- **KI-Einrichtung für Kunden vereinfacht:** Die Dienst-URL ist jetzt mit dem Standard-Dienst des Anbieters vorbelegt – zum Aktivieren des KI-Assistenten genügt es, den Lizenzschlüssel in den Einstellungen einzutragen. Eine eigene Dienst-URL kann weiterhin hinterlegt werden.

## 1.22.2 – 2026-07-18

- **KI-Dienst domainabhängig ausliefern:** Der Updater installiert das Verzeichnis `ai-server/` jetzt ausschließlich auf den Anbieter-Domains (aktuell `blockwerk.bairle.de`) mit – dort wird eine vorhandene `config.php` und die Lizenz-Datenbank beim Update nie überschrieben. Alle anderen Installationen erhalten den Dienst weiterhin nicht. Zusätzlich lässt die Root-`.htaccess` Anfragen an `/ai-server/…` direkt durch, damit der Dienst auf dem Anbieter-Server unter der eigenen Domain erreichbar ist.

## 1.22.1 – 2026-07-18

- **KI-Dienst nicht mehr auf Installationen:** Installer und Updater überspringen das Verzeichnis `ai-server/` jetzt komplett – der zentrale KI-Dienst wird ausschließlich vom Anbieter auf dessen Server deployt und landet nicht mehr im Paket der Kunden-Installationen. (Wer 1.22.0 bereits installiert hat, kann einen evtl. vorhandenen Ordner `ai-server/` gefahrlos löschen – er ist ohne Konfiguration funktionslos.)

## 1.22.0 – 2026-07-18

- **✨ KI-Assistent:** Neuer Bereich im Backend (Inhalte → KI-Assistent, nur für Administratoren). Einfach beschreiben, was gebraucht wird – die KI (Claude) erstellt komplette Seiten direkt im CMS: Struktur mit Zeilen/Spalten, deutsche Texte, moderne Gestaltung mit den Layout-Farben und **per KI generierte Bilder**, die automatisch in der Mediathek landen. Bestehende Seiten lassen sich per Chat ändern („Mach die Überschrift knackiger"); vor jeder Änderung wird der alte Stand als Version gesichert. Die KI kennt die komplette Blockwerk-Architektur und die jeweilige Installation (Seiten, Layouts, Mediathek) – alles läuft durch dieselbe Validierung wie der normale Editor.
- **Token-Guthaben:** Jede Installation nutzt einen Lizenzschlüssel mit Token-Guthaben (Einstellungen → KI-Assistent: Dienst-URL + Schlüssel). Das Restguthaben wird im Chat angezeigt; Chat-Anfragen kosten die tatsächlichen Tokens, generierte Bilder einen festen Preis.
- **Zentraler KI-Dienst für Anbieter:** Neues Verzeichnis `ai-server/` – eigenständiger Dienst (PHP + SQLite) mit Lizenz-Verwaltung (`admin.php`: Lizenzen anlegen, Guthaben aufladen), Weiterleitung an die Claude- und Bild-APIs, Verbrauchs-Log, Rate-Limit und Mock-Modus für Tests. Nicht Teil der CMS-Installation – wird nur auf dem Anbieter-Server deployt (siehe `ai-server/README.md`).

## 1.21.0 – 2026-07-18

- **Moderner Drag-&-Drop-Upload in der Mediathek:** Statt des klassischen Formulars gibt es jetzt eine große Upload-Zone – Dateien einfach irgendwo auf die Seite ziehen (die Zone leuchtet orange auf) oder klicken zum Auswählen. Mehrere Dateien gleichzeitig, mit **Fortschrittsbalken** beim Hochladen.
- **Ohne Neuladen:** Neu hochgeladene Dateien erscheinen sofort oben im Raster (kurz orange hervorgehoben) – inklusive „URL kopieren" und Löschen. Fehlermeldungen (z. B. nicht erlaubter Dateityp) werden direkt unter der Upload-Zone angezeigt.

## 1.20.0 – 2026-07-18

- **Eigene Einbindungen pro Layout:** Im Layout-Formular gibt es die neue Karte „Einbindungen (externe Tools)" mit zwei Feldern – **Code im `<head>`** und **Code direkt vor `</body>`**. Ideal für Analyse-Tools, Chat-Widgets, Cookie-Banner oder eigene Meta-Tags; der Code wird unverändert (inklusive `<script>`-Tags) auf allen Seiten mit diesem Layout ausgegeben. Gilt für klassische und visuell gebaute Layouts; neue Datenbank-Spalten werden beim Update automatisch angelegt.

## 1.19.0 – 2026-07-18

- **Gestalten pro Displaygröße – direkt über die Geräte-Ansicht:** Die gewählte Ansicht im Editor bestimmt jetzt, für welche Bildschirmgröße die Gestaltung gilt. In der **Desktop-Ansicht** gelten Änderungen für alle Größen; wechselt man auf **Handy oder Tablet**, zeigt das Gestaltungs-Panel nur noch Ausrichtung und Abstände „– nur mobil" und Änderungen wirken ausschließlich unter 768 px. Ein farbiger Hinweis im Panel zeigt jederzeit, für welche Größe man gerade gestaltet.
- **Mobile Anpassungen wieder entfernen:** In der Handy-/Tablet-Ansicht gibt es einen Knopf „↺ Mobile Anpassungen entfernen", der den Block wieder komplett den Desktop-Werten folgen lässt.
- Der bisherige separate „📱 Mobil"-Abschnitt im Panel entfällt – die gespeicherten Werte bleiben vollständig kompatibel.

## 1.18.1 – 2026-07-18

- **Mobil-Gestaltung im Editor korrekt getrennt:** Die Mobil-Überschreibungen (z. B. „Ausrichtung mobil") richteten sich im Editor nach der echten Fensterbreite des Browsers – wer mit schmalem Fenster oder am Tablet arbeitete, sah die Mobil-Werte fälschlich auch in der Desktop-Ansicht. Jetzt folgen sie im Editor sauber dem Geräte-Umschalter: Desktop-Ansicht zeigt immer die Desktop-Gestaltung, Handy-/Tablet-Ansicht zeigt live die Mobil-Überschreibungen. Auf der Website selbst ändert sich nichts (dort gilt wie gehabt die echte Bildschirmbreite).

## 1.18.0 – 2026-07-18

- **Mobiles Spalten-Verhalten pro Zeile einstellbar:** In den Zeilen-Einstellungen gibt es jetzt „Spalten untereinander ab … px Bildschirmbreite". Leer = automatisch (768 px, wie bisher), eigener Wert = Spalten stapeln sich genau ab dieser Breite, 0 = Spalten bleiben auch auf dem Handy nebeneinander.
- **Eigene Mobil-Gestaltung pro Block:** Im Gestaltungs-Panel jedes Blocks gibt es den neuen Abschnitt „📱 Mobil (unter 768 px)" – dort lassen sich Ausrichtung (z. B. Desktop linksbündig, mobil zentriert) sowie Abstände oben/unten und Innenabstand nur für die mobile Ansicht überschreiben. Ohne Angabe gelten weiterhin die normalen Werte.

## 1.17.0 – 2026-07-18

- **Bereichs-Breite pro Zeile wählbar:** In den Zeilen-Einstellungen (Klick auf die Zeilen-Leiste) gibt es jetzt „Breite des Bereichs" – entweder so breit wie der Inhaltsbereich (Standard) oder **volle Seitenbreite**, dann laufen die Inhalte bis an den Browserrand. Im Editor wird das mit „↔ volle Breite" am Zeilen-Label und randloser Darstellung angezeigt.
- **Zeilen-Hintergrund aus der Layout-Palette:** Die Hintergrundfarbe eines Bereichs lässt sich jetzt direkt aus den Gestaltungs-Farben des Layouts wählen (Hauptfarbe, Akzentfarbe, Flächenfarbe, Seitenhintergrund) – ändert man später die Layout-Farben, färben sich diese Bereiche automatisch mit. Alternativ wie bisher eine frei wählbare eigene Farbe per Color-Picker.

## 1.16.1 – 2026-07-18

- **Geräte-Vorschau sichtbar gemacht:** Die drei Ansicht-Umschalter im Editor (Desktop/Tablet/Smartphone) zeigen jetzt einen deutlichen Unterschied – der Canvas wird schmal wie das Gerät, eine Beschriftung („Smartphone-Ansicht · 400 px …") erscheint darüber und die Spalten stapeln sich untereinander, genau wie im Frontend auf schmalen Bildschirmen. Vorher verhinderte eine CSS-Spezifitätsregel das Schmalstellen.
- **Icons repariert:** Die Umschalter nutzen jetzt klare eingebaute SVG-Symbole (Monitor, Tablet, Smartphone) statt Unicode-Zeichen – das Tablet-Symbol wurde je nach System gar nicht angezeigt. Der aktive Modus ist orange gefüllt.

## 1.16.0 – 2026-07-18

- **Einfügen per Klick:** Zwischen den Zeilen und unter jeder Spalte gibt es jetzt „+ Zeile"- und „+ Block"-Knöpfe – Ziehen ist nicht mehr nötig (bleibt aber möglich).
- **Block-Wähler mit Suche:** „+ Block" öffnet einen übersichtlichen Dialog mit Suchfeld und Kategorien (Text, Medien, Elemente, Inhalte & Daten; im Layout-Baukasten zusätzlich Layout).
- **Fertige Sektionen:** „+ Zeile" bietet neben den Spalten-Rastern fertig befüllte Sektionen an – Hero mit Button, Text + Bild, 3 Karten, Zitat, Preistabelle und Kontakt. Auch der leere Seiten-Start führt direkt dorthin.
- **Direkt auf der Seite schreiben:** Überschriften und Textblöcke lassen sich jetzt direkt im Canvas anklicken und beschreiben – ohne Umweg über das Eigenschaften-Panel.
- **Schwebende Block-Werkzeuge:** Im Block-Label erscheinen beim Überfahren Knöpfe für Hoch/Runter, **Duplizieren** (neu, auch für ganze Zeilen) und Löschen.
- **Rückgängig & Wiederholen:** Strg+Z / Strg+Y (bzw. Strg+Umschalt+Z) machen bis zu 60 Schritte rückgängig – Tipp-Phasen werden zu einem Schritt zusammengefasst.
- **Spaltenbreite per Ziehen:** Die rechte Spaltenkante lässt sich einfach mit der Maus ziehen – zusätzlich zu den +/−-Knöpfen.
- **Geräte-Vorschau:** Neue Umschalter im Editor-Kopf zeigen den Canvas in Desktop-, Tablet- (768 px) oder Smartphone-Breite (400 px, einspaltig).
- **Mehr Übersicht beim Ziehen:** Kräftigere orange Einfüge-Markierung und deutlich hervorgehobene Ablagezonen.
- **Tastenkürzel:** Strg+S speichern, Strg+D dupliziert Block/Zeile, Entf löscht den markierten Block, Esc hebt die Auswahl auf.

## 1.15.0 – 2026-07-18

- **Strukturierte Admin-Navigation:** Die Seitenleiste ist jetzt in Gruppen gegliedert statt einer langen Liste – **Inhalte** (Seiten, News, Events, Formulare, Mediathek, Globale Blöcke), **Gestaltung** (Designs, Menü, Layouts, Templates, Schriften) und **System** (Benutzer, Updates, Einstellungen), mit dezenten Zwischenüberschriften. Das Dashboard bleibt oben. Redakteure sehen weiterhin nur die Inhalts-Bereiche; auch das mobile Menü zeigt die Gruppen.

## 1.14.2 – 2026-07-18

- **Einheitliche Eingabefelder:** Datums-/Uhrzeitfelder (z. B. Beginn/Ende bei Events, Veröffentlichungszeitpunkt bei News) sowie E-Mail-, URL-, Such- und Telefonfelder sehen jetzt exakt aus wie alle anderen Eingabefelder im Backend – gleicher Rahmen, gleiche Rundung, gleiche Höhe.

## 1.14.1 – 2026-07-18

- **Menü-Designer wirkt jetzt überall:** Layouts und Design-Themes, die das Menü direkt über `{{menu}}` einbinden (statt über das Menü-Template), ignorierten die Einstellungen aus dem Menü-Designer komplett – deshalb änderte sich das Menü dort nie, egal was man wählte. Jetzt rendert `{{menu}}` immer das voll gestaltete Designer-Menü (Vorlage, Farben, Größen, mobiler Breakpoint). `{{menu:variante}}` bleibt für explizite Sonderfälle erhalten.
- **Kein veraltetes HTML mehr im Browser:** Alle Seiten werden mit `Cache-Control: no-cache` ausgeliefert – Browser (v. a. am Handy) prüfen jetzt bei jedem Aufruf, ob sich die Seite geändert hat, statt stillschweigend eine alte Kopie zu zeigen.

## 1.14.0 – 2026-07-18

- **Mobiles Website-Menü repariert:** Nach dem Update wird das Menü-Template einmalig automatisch auf den Designer-Stand gebracht – ältere, von Hand angelegte Menü-Templates hatten kein mobiles Burger-Menü. Zusätzlich ergänzt das CMS jetzt automatisch den Viewport-Meta-Tag, falls er in einem Layout fehlt (ohne ihn zeigen Smartphones die Desktop-Ansicht und das mobile Menü erscheint nie).
- **„Auf Standard zurücksetzen" im Menü-Designer:** Ein Klick stellt Standard-Vorlage und Standardfarben wieder her – mit Sicherheitsabfrage.
- **Neue Update-Oberfläche:** Aufgeräumt und einfach – keine URLs mehr sichtbar oder änderbar. Eine Karte mit Logo und installierter Version, ein Knopf „Nach Updates suchen", und wenn etwas gefunden wird, ein großer „Jetzt aktualisieren"-Knopf.
- **Schöne Bestätigungen statt Browser-Dialogen:** Vor dem Update öffnet sich ein gestaltetes Bestätigungsfenster, und nach erfolgreichem Update erscheint eine Erfolgsansicht mit grünem Haken und einer „Das ist neu"-Liste aller Änderungen aus dem Changelog.

## 1.13.0 – 2026-07-18

- **Neuer Bereich „Menü" im Backend:** Das Hauptmenü wird jetzt komplett visuell gestaltet – ohne HTML. Menü-Vorlage als Auswahlkarten (Dropdown, Mega-Menü, Vertikal, Nur oberste Ebene), Schriftgröße, Innenabstand, Abstand zwischen Punkten, Ausrichtung, GROSSBUCHSTABEN, eigene Farben (Text, Aufklapp-Hintergrund, Aufklapp-Text) per Color-Picker, Mega-Menü über volle Breite und der mobile Breakpoint.
- **Live-Vorschau:** Jede Änderung im Menü-Designer wird sofort in einer echten Vorschau mit den eigenen Seiten angezeigt – inklusive funktionierendem Hover-/Mega-Aufklappen.
- **Template wird im Hintergrund erzeugt:** Beim Speichern generiert das CMS das Menü-Template ({{template:main-menu}}) automatisch und übernimmt die Einstellungen auch in alle visuell gebauten Layouts (Menü-Baustein). Im Templates-Bereich ist das Menü-Template entsprechend gekennzeichnet; das HTML bleibt für Profis weiterhin zugänglich.

## 1.12.1 – 2026-07-18

- **Cache-Busting für CSS/JS:** Alle Stylesheets und Skripte (Admin, Login, Installer, Frontend-Blöcke) werden jetzt mit der CMS-Version in der URL eingebunden (`?v=…`). Nach einem Update laden Browser die Dateien automatisch frisch – vorher konnte ein alter Browser-Cache dafür sorgen, dass z. B. das Backend nach dem Orange-Update weiterhin blau aussah.

## 1.12.0 – 2026-07-18

- **Mobiles Backend-Menü:** Auf schmalen Bildschirmen gibt es jetzt eine feste Kopfleiste in voller Breite – links Logo und „Blockwerk Orange", rechts das Burger-Icon, immer sichtbar (auch beim Scrollen und im Editor).
- **Menü-Schublade von links:** Ein Tipp auf das Burger-Icon fährt die Admin-Navigation als Schublade von links über den Inhalt (mit Abdunkelung dahinter); Tipp auf den Hintergrund oder erneut auf das Icon schließt sie. Das Icon animiert zum ✕.

## 1.11.0 – 2026-07-18

- **Neuer Name: Blockwerk Orange!** Das CMS heißt jetzt „Blockwerk Orange" – umbenannt in Admin-Seitenleiste, Login, Installations-Assistent, Ein-Datei-Installer, README und Doku.
- **Backend komplett in Orangetönen:** Das gesamte Admin-Design (Primärfarbe, Seitenleiste, Login-Verlauf, Hover-Flächen) sowie Logo und Favicon wurden auf ein warmes Orange umgestellt.
- **Backend responsive:** Auf schmalen Bildschirmen wird die Seitenleiste zu einer horizontal scrollbaren Kopfleiste, Formularzeilen brechen automatisch um und breite Tabellen scrollen innerhalb ihrer Karte.
- **Grafische Editoren mit fester Arbeitsbreite:** Seiten-, Layout- und Menü-Editor behalten auf kleinen Screens ihre volle Breite und werden stattdessen horizontal gescrollt – nichts wird gequetscht.
- **Neue Paket-Adresse:** Installer und Updater laden das CMS jetzt vom umbenannten GitHub-Repository `jakober/Blockwerk` (die alte Adresse leitet weiterhin um).

## 1.10.0 – 2026-07-17

- **Menü komplett visuell gestaltbar:** Der Menü-Block im Layout-Baukasten hat jetzt volle Design-Optionen ohne CSS – Schriftgröße, Innenabstand der Menüpunkte, Abstand zwischen Punkten, Ausrichtung, GROSSBUCHSTABEN, Textfarbe sowie **Hintergrund- und Textfarbe des Aufklapp-Menüs** (gilt für Hover-Dropdown UND Mega-Menü). Die HTML-Templates bleiben optional weiter anpassbar.
- **Mega-Menü aufgehübscht:** sanfte Einblend-Animation, Akzentkante oben, sauber begrenzte und zentrierte Spalten, Hover-Effekte – wahlweise **über die volle Seitenbreite** oder nur in Inhaltsbreite.
- **Automatisches Mobil-Menü:** Pro Menü ist ein Breakpoint (px) einstellbar – darunter erscheint automatisch ein Burger-Menü mit aufklappender Vollbreiten-Liste (inkl. animiertem Burger-Icon, eingerückten Unterebenen, Panel-Farben).
- **Touch-Unterstützung:** Auf Geräten ohne Maus (Tablets/Handys) öffnet der erste Tipp auf einen Menüpunkt mit Unterpunkten das Dropdown/Mega-Panel, der zweite Tipp folgt dem Link – funktioniert auch in den HTML-Themes.

## 1.9.0 – 2026-07-17

- **Visueller Layout-Baukasten:** Layouts lassen sich jetzt komplett per Drag & Drop bauen – mit denselben Zeilen und flexiblen Spalten wie im Seiten-Editor. Neue Layout-Blöcke: **Logo & Name** (optional mit Logo-Bild), **Menü** (mit wählbarer Menü-Vorlage und Ausrichtung), **Inhaltsbereich** (dort erscheint der Seiteninhalt; nur einmal einsetzbar) und **Sprachumschalter**. Alle normalen Inhalts-Blöcke (Text, Bild, Social Media, globale Blöcke …) funktionieren im Layout ebenfalls – z. B. für die Fußzeile. Vollbreite farbige Kopf-/Fußzeilen über die bekannten Zeilen-Einstellungen.
- Unter Layouts: „+ Neues Layout (visuell)“ startet mit fertiger Grundstruktur (Kopfzeile, Inhaltsbereich, Fußzeile); bestehende Layouts können jederzeit visuell bearbeitet oder zurück in den HTML-Modus geschaltet werden. Designs (Themes) bleiben HTML-basiert.
- **Globale Blöcke überall:** Neuer Platzhalter `{{global:ID}}` bettet einen globalen Block direkt in Layouts oder Templates ein – damit erscheint er auf jeder Seite (die ID steht in der Liste unter „Globale Blöcke“).

## 1.8.2 – 2026-07-17

- **Update-Prüfung zuverlässiger:** Die Versionsabfrage nutzt bei GitHub-Quellen jetzt die GitHub-API (immer frisch) statt der bis zu 5 Minuten zwischengespeicherten Raw-URL – „Nach Updates suchen“ sieht neue Versionen damit sofort. Fällt bei Bedarf automatisch auf die normale URL zurück.

## 1.8.1 – 2026-07-17

- **Wichtige Fehlerbehebung (Error 500 nach Update auf 1.8.0):** Der Updater führte die Datenbank-Migration noch mit den alten, bereits geladenen Programmklassen aus – dadurch fehlten nach dem Update die neuen Tabellen/Spalten und die neuen Bereiche zeigten Fehler 500. Das CMS prüft jetzt bei jedem Aufruf selbst, ob das Datenbankschema zur Code-Version passt, und **migriert automatisch nach** (selbstheilend). Einmal erneut „Aktualisieren“ klicken genügt.
- **Optik-Feinschliff:** Preistabelle komplett überarbeitet (weiße Karten mit Schatten, „Empfohlen“-Badge, Häkchen vor Leistungen, bündige Buttons, Preis mit Zeitraum sauber gesetzt), Team-Karten und Countdown aufgehübscht, durchgängige Abstände zwischen gestapelten Blöcken.
- **Formular-Posteingang gestaltet:** Aufklappbare Detail-Ansicht als saubere Tabelle mit beschrifteten Feldern statt roher Browser-Darstellung.

## 1.8.0 – 2026-07-17

Das große Komfort- und Sicherheits-Update:

- **Papierkorb:** Gelöschte Seiten landen im Papierkorb (Seiten → Papierkorb) und lassen sich wiederherstellen oder endgültig löschen.
- **Versionsverlauf:** Jedes Speichern im Editor sichert den vorherigen Stand (letzte 20 Versionen, „Versionen“-Knopf im Editor); Wiederherstellen mit einem Klick – der aktuelle Stand wird dabei ebenfalls gesichert.
- **Backup mit einem Klick:** Komplette Sicherung (Datenbank-Dump, Uploads, Konfiguration, Wiederherstellungs-Anleitung) als ZIP – auf der Updates-Seite.
- **Formular-Baukasten & Posteingang:** Kontaktformulare können eigene Felder bekommen (Textzeile, Textbereich, Auswahlliste, Checkbox, Pflichtfeld-Option). Alle Einsendungen werden zusätzlich zur E-Mail im neuen Bereich „Formulare“ gespeichert.
- **Sitemap & Weiterleitungen:** Automatische `/sitemap.xml` (Seiten, News, Events); bei Slug-Änderungen entsteht automatisch eine 301-Weiterleitung von der alten Adresse.
- **Website-Suche:** Neuer Block „Suchfeld“ und Ergebnisseite unter `/suche` – durchsucht Seiten, News und Events.
- **Globale Blöcke:** Wiederverwendbare Inhaltsbereiche, gepflegt mit dem normalen Editor, einsetzbar über den Block „Globaler Block“ – eine Änderung wirkt überall.
- **Seiten duplizieren:** Kopier-Knopf in der Seitenliste (Kopie als Entwurf).
- **Rollen:** Neben Administratoren gibt es jetzt Redakteure – sie sehen nur Inhalte (Seiten, News, Events, Formulare, Medien, globale Blöcke) und kommen nicht an Layouts, Designs, Benutzer, Updates oder Einstellungen.
- **Seiten-Cache:** Zuschaltbar in den Einstellungen – fertige Seiten werden zwischengespeichert (deutlich schneller); jede Änderung im Admin leert den Cache automatisch, Seiten mit Formularen sind ausgenommen.
- **Mehrsprachigkeit:** Sprachen in den Einstellungen festlegen (z. B. `de,en`); jede Seite bekommt eine Sprache, weitere Sprachen sind unter `/en/…` erreichbar, das Menü zeigt nur die passende Sprache, Sprachumschalter per `{{languages}}`.
- **Neue Blöcke:** Karte (OpenStreetMap), Team-Mitglieder, Preistabelle (mit Hervorhebung), Countdown und Social-Media-Leiste.

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
