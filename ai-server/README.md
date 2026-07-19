# Blockwerk Orange – zentraler KI-Dienst

**Dieses Verzeichnis ist NICHT Teil der CMS-Installation.** Es ist der
zentrale Dienst des Anbieters, über den alle KI-Anfragen der
Blockwerk-Orange-Installationen laufen – inklusive Token-Abrechnung pro
Lizenzschlüssel. Auf Kunden-Installationen liegt der Ordner zwar mit im
Paket, ist dort aber ohne `config.php` funktionslos.

## Deployment (nur auf dem Anbieter-Server)

1. Verzeichnis auf einen PHP-8-Webspace legen (mit `pdo_sqlite` und `curl`),
   idealerweise als eigene (Sub-)Domain, z. B. `https://ki.deine-domain.de/`.
   - **Apache:** Die mitgelieferte `.htaccess` leitet `/v1/*` auf `index.php` und
     schützt `config.php`/`data.sqlite` – nichts weiter nötig.
   - **nginx als Unterpfad** (kein Subdomain, z. B. `https://deine-domain.de/ai-server`):
     eigenen `location`-Block ergänzen, der auf das Verzeichnis zeigt und PHP an PHP-FPM
     gibt (`.htaccess` wirkt bei nginx nicht):

     ```nginx
     location ^~ /ai-server/ {
         alias /pfad/zu/ai-server/;
         location ~ ^/ai-server/(data\.sqlite|.*config.*\.php)$ { deny all; }
         try_files $uri /ai-server/index.php$is_args$args;
         location ~ ^/ai-server/.+\.php$ {
             include fastcgi_params;
             fastcgi_pass unix:/run/php/php8.3-fpm.sock;
             fastcgi_param SCRIPT_FILENAME $request_filename;
         }
     }
     ```

     Der Dienst erkennt seine Endpunkte über das Ende des Pfads
     (`str_ends_with($path, '/v1/chat')`), daher ist jeder Unterpfad möglich.
2. `config.example.php` nach `config.php` kopieren und ausfüllen:
   Anthropic-Key (Chat), OpenAI-Key (Bilder), Admin-Passwort.
3. `admin.php` im Browser öffnen → Lizenz anlegen, Guthaben aufladen.
4. Den Lizenzschlüssel dem Kunden geben – er trägt ihn im CMS unter
   **Einstellungen → KI-Assistent** ein (plus die Dienst-URL).

## Endpunkte

| Methode | Pfad | Zweck |
|---|---|---|
| POST | `/v1/chat` | Claude-Anfrage (Messages-API-Passthrough) |
| POST | `/v1/image` | Bildgenerierung (OpenAI Images) |
| GET | `/v1/balance` | Token-Guthaben einer Lizenz |

Jede Antwort enthält das aktuelle Restguthaben (`balance`). Chat-Anfragen
kosten die echten Ein-/Ausgabe-Tokens, Bilder einen festen Preis
(`image_token_price`).

## Lokale Tests

`'mock' => true` in der `config.php` aktiviert den Mock-Modus: `/v1/chat`
liefert vorgefertigte Tool-Aufrufe (Bild generieren → Seite anlegen →
fertig), `/v1/image` ein Mini-PNG. Damit lässt sich der komplette
CMS-Kreislauf ohne echte API-Keys testen:

```bash
php -S 127.0.0.1:8100 ai-server-router.php   # oder Ordner als Docroot
```
