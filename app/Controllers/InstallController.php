<?php
declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Core\View;
use Models\User;
use PDO;
use PDOException;

class InstallController
{
    /** Schritt 1: Zugangsdaten zur Datenbank. */
    public function index(): void
    {
        redirect('/install/database');
    }

    public function databaseForm(): void
    {
        View::render('install/database', ['error' => flash()['message'] ?? null]);
    }

    public function database(): void
    {
        $host = trim($_POST['host'] ?? 'localhost');
        $port = (int) ($_POST['port'] ?? 3306);
        $name = trim($_POST['name'] ?? '');
        $user = trim($_POST['user'] ?? '');
        $pass = (string) ($_POST['pass'] ?? '');

        if ($host === '' || $name === '' || $user === '') {
            flash('error', 'Bitte Host, Datenbankname und Benutzer angeben.');
            redirect('/install/database');
        }

        try {
            try {
                $pdo = Database::connect($host, $port, $name, $user, $pass);
            } catch (PDOException $e) {
                // Datenbank existiert evtl. noch nicht – versuchen, sie anzulegen.
                $server = new PDO(
                    sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port),
                    $user,
                    $pass,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $server->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $name) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $pdo = Database::connect($host, $port, $name, $user, $pass);
            }
            Database::createSchema($pdo);
        } catch (PDOException $e) {
            flash('error', self::friendlyDbError($e, $host, $user));
            redirect('/install/database');
        }

        $_SESSION['install_db'] = compact('host', 'port', 'name', 'user', 'pass');
        redirect('/install/site');
    }

    /** Übersetzt häufige MySQL-Verbindungsfehler in verständliche Hinweise. */
    private static function friendlyDbError(PDOException $e, string $host, string $user): string
    {
        $message = $e->getMessage();
        $code = (int) ($e->errorInfo[1] ?? 0);
        if ($code === 0 && preg_match('/\[(\d{4})\]/', $message, $m)) {
            $code = (int) $m[1];
        }

        return match (true) {
            $code === 1130 => 'Der MySQL-Server lehnt Verbindungen von deinem Webserver ab (Fehler 1130 – "Host is not allowed to connect"). '
                . 'Das ist KEIN falsches Passwort, sondern eine Rechte-Einstellung am MySQL-Server: Der Benutzer "' . $user . '" ist dort nur für bestimmte Hosts freigeschaltet (z. B. nur localhost). '
                . 'Lösungen: 1) Wenn Webserver und Datenbank auf derselben Maschine laufen, als Host "localhost" eintragen. '
                . '2) Beim Hoster den exakten Datenbank-Host aus dem Kundenmenü verwenden (oft nicht "localhost"). '
                . '3) Bei eigenem Server/Docker dem Benutzer den Zugriff vom Webserver erlauben, z. B.: CREATE USER \'' . $user . '\'@\'%\' IDENTIFIED BY \'…\'; GRANT ALL PRIVILEGES ON `datenbankname`.* TO \'' . $user . '\'@\'%\'; FLUSH PRIVILEGES;',
            $code === 1045 => 'Anmeldung am MySQL-Server abgelehnt (Fehler 1045 – "Access denied"). Benutzername oder Passwort stimmen nicht – oder der Benutzer existiert für diesen Host nicht. Bitte die Zugangsdaten genau so verwenden, wie sie beim Anlegen des Datenbank-Benutzers vergeben wurden.',
            $code === 1044 => 'Der Benutzer "' . $user . '" hat keine Rechte auf diese Datenbank (Fehler 1044). Bitte dem Benutzer im Hosting-Menü bzw. per GRANT die Rechte auf die Datenbank geben – oder eine Datenbank wählen, für die er berechtigt ist.',
            $code === 2002 || str_contains($message, 'Connection refused') || str_contains($message, 'getaddrinfo')
                => 'Der MySQL-Server ist unter "' . $host . '" nicht erreichbar (Verbindung abgelehnt oder Host unbekannt). Bitte Host und Port prüfen – bei vielen Hostern lautet der Datenbank-Host nicht "localhost", sondern steht im Kundenmenü. Läuft der MySQL-Server?',
            default => 'Datenbankverbindung fehlgeschlagen: ' . $message,
        };
    }

    public function site(): void
    {
        if (empty($_SESSION['install_db'])) {
            redirect('/install');
        }
        View::render('install/site', ['error' => flash()['message'] ?? null]);
    }

    public function finish(): void
    {
        $db = $_SESSION['install_db'] ?? null;
        if (!is_array($db)) {
            redirect('/install');
        }

        $siteName = trim($_POST['site_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $passwordRepeat = (string) ($_POST['password_repeat'] ?? '');

        if ($siteName === '' || $username === '' || strlen($password) < 8) {
            flash('error', 'Bitte alle Felder ausfüllen. Das Passwort braucht mindestens 8 Zeichen.');
            redirect('/install/site');
        }
        if ($password !== $passwordRepeat) {
            flash('error', 'Die Passwörter stimmen nicht überein.');
            redirect('/install/site');
        }

        try {
            $pdo = Database::connect($db['host'], (int) $db['port'], $db['name'], $db['user'], $db['pass']);
            Database::createSchema($pdo);
            User::create($pdo, $username, $password);
            self::seed($pdo, $siteName);
        } catch (PDOException $e) {
            flash('error', 'Installation fehlgeschlagen: ' . $e->getMessage());
            redirect('/install/site');
        }

        if (!$this->writeConfig($db)) {
            flash('error', 'Die Konfigurationsdatei konnte nicht geschrieben werden. Bitte Schreibrechte für das config/-Verzeichnis prüfen.');
            redirect('/install/site');
        }

        unset($_SESSION['install_db']);
        flash('success', 'Installation abgeschlossen! Du kannst dich jetzt anmelden.');
        redirect('/login');
    }

    private function writeConfig(array $db): bool
    {
        return \Core\Config::save([
            'db' => [
                'host' => $db['host'],
                'port' => (int) $db['port'],
                'name' => $db['name'],
                'user' => $db['user'],
                'pass' => $db['pass'],
            ],
        ]);
    }

    /**
     * Grundausstattung einer frischen Installation: Menü-Template, Standard-Layout
     * und eine kleine Beispiel-Website (Start, Leistungen, Über uns, Kontakt,
     * Impressum, Datenschutz), damit nach dem ersten Login nicht eine leere
     * Installation dasteht. Mit $examples = false bleibt es bei der Startseite.
     */
    public static function seed(PDO $pdo, string $siteName, bool $examples = true): void
    {
        // Nur beim allerersten Durchlauf Beispieldaten anlegen.
        if ((int) $pdo->query('SELECT COUNT(*) FROM pages')->fetchColumn() > 0) {
            $pdo->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)')
                ->execute(['site_name', $siteName]);
            return;
        }

        $menuTemplate = "<nav class=\"main-nav\">{{menu}}</nav>";
        $pdo->prepare('INSERT INTO templates (name, tkey, html) VALUES (?, ?, ?)')
            ->execute(['Hauptmenü', 'main-menu', $menuTemplate]);

        $layoutHtml = <<<'HTML'
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{title}} – {{site_name}}</title>
<link rel="stylesheet" href="{{base_url}}/assets/css/site.css">
</head>
<body>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="{{base_url}}/">{{logo}}{{site_name}}</a>
    {{template:main-menu}}
  </div>
</header>
<main class="container">
{{content}}
</main>
<footer class="site-footer">
  <div class="container">&copy; {{year}} {{site_name}}</div>
</footer>
</body>
</html>
HTML;
        $pdo->prepare('INSERT INTO layouts (name, html) VALUES (?, ?)')->execute(['Standard', $layoutHtml]);
        $layoutId = (int) $pdo->lastInsertId();

        $content = json_encode([
            'rows' => [
                [
                    'id' => 'row-1',
                    'columns' => [
                        ['id' => 'col-1', 'span' => 12, 'blocks' => [
                            ['id' => 'b-1', 'type' => 'heading', 'data' => ['text' => 'Willkommen auf ' . $siteName, 'level' => 'h1']],
                            ['id' => 'b-2', 'type' => 'text', 'data' => ['html' => '<p>Diese Seite wurde vom Install-Assistenten angelegt. Öffne den Admin-Bereich und bearbeite den Inhalt per Drag &amp; Drop.</p>']],
                        ]],
                    ],
                ],
                [
                    'id' => 'row-2',
                    'columns' => [
                        ['id' => 'col-2', 'span' => 6, 'blocks' => [
                            ['id' => 'b-3', 'type' => 'heading', 'data' => ['text' => 'Flexible Spalten', 'level' => 'h3']],
                            ['id' => 'b-4', 'type' => 'text', 'data' => ['html' => '<p>Jede Zeile kann beliebig viele Spalten mit frei wählbarer Breite haben – im 12er-Raster.</p>']],
                        ]],
                        ['id' => 'col-3', 'span' => 6, 'blocks' => [
                            ['id' => 'b-5', 'type' => 'heading', 'data' => ['text' => 'Drag & Drop', 'level' => 'h3']],
                            ['id' => 'b-6', 'type' => 'text', 'data' => ['html' => '<p>Ziehe Inhalts-Blöcke einfach aus der Palette in eine Spalte und sieh das Ergebnis sofort.</p>']],
                        ]],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        if ($examples) {
            $content = self::examplePage($siteName);
        }

        $pdo->prepare('INSERT INTO pages (title, slug, layout_id, in_menu, menu_order, published, content) VALUES (?, ?, ?, 1, 0, 1, ?)')
            ->execute(['Start', 'start', $layoutId, $content]);
        $pageId = (int) $pdo->lastInsertId();

        if ($examples) {
            self::seedExamplePages($pdo, $layoutId, $siteName);
        }

        $settings = [['site_name', $siteName], ['home_page', (string) $pageId]];
        $stmt = $pdo->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
        foreach ($settings as $row) {
            $stmt->execute($row);
        }
    }

    /** Eine Zeile mit einer Spalte über die volle Breite. */
    private static function row(string $id, array $blocks, array $style = []): array
    {
        $row = ['id' => $id, 'columns' => [['id' => $id . '-c', 'span' => 12, 'blocks' => $blocks]]];
        if ($style !== []) {
            $row['style'] = $style;
        }
        return $row;
    }

    /** Überschrift + Fließtext als Blockpaar (Kurzschreibweise für den Seed). */
    private static function textBlocks(string $id, string $heading, string $level, string $html): array
    {
        return [
            ['id' => $id . '-h', 'type' => 'heading', 'data' => ['text' => $heading, 'level' => $level]],
            ['id' => $id . '-t', 'type' => 'text', 'data' => ['html' => $html, 'variant' => 'standard']],
        ];
    }

    /** Startseite der Beispiel-Website: Begrüßung, drei Leistungen, Kontaktaufruf. */
    private static function examplePage(string $siteName): string
    {
        $cards = [];
        $texts = [
            ['Persönliche Beratung', 'Wir nehmen uns Zeit für dein Anliegen und finden gemeinsam die passende Lösung.'],
            ['Erfahrung, die zählt', 'Seit vielen Jahren begleiten wir unsere Kundinnen und Kunden zuverlässig.'],
            ['Fair und transparent', 'Klare Absprachen, verständliche Angebote – ohne Überraschungen.'],
        ];
        foreach ($texts as $i => [$title, $text]) {
            $cards[] = ['id' => 'ex-card-' . $i, 'span' => 4, 'blocks' => [
                ['id' => 'ex-card-' . $i . '-h', 'type' => 'heading', 'data' => ['text' => $title, 'level' => 'h3']],
                ['id' => 'ex-card-' . $i . '-t', 'type' => 'text', 'data' => ['html' => '<p>' . e($text) . '</p>', 'variant' => 'infobox']],
            ]];
        }

        return (string) json_encode(['rows' => [
            self::row('ex-intro', self::textBlocks(
                'ex-intro',
                'Willkommen bei ' . $siteName,
                'h1',
                '<p>Schön, dass du da bist. Diese Startseite ist ein Vorschlag zum Loslegen – '
                    . 'ersetze die Texte durch deine eigenen oder lass den KI-Assistenten eine komplette '
                    . 'Website daraus machen.</p>'
            )),
            ['id' => 'ex-cards', 'style' => ['bg' => 'surface', 'pt' => 50, 'pb' => 50], 'columns' => $cards],
            self::row('ex-cta', array_merge(
                self::textBlocks('ex-cta', 'Sprich uns an', 'h2', '<p>Wir freuen uns auf deine Nachricht.</p>'),
                [['id' => 'ex-cta-f', 'type' => 'form', 'data' => [
                    'recipient' => '',
                    'subject' => 'Neue Nachricht über die Website',
                    'button_text' => 'Nachricht senden',
                    'success' => 'Vielen Dank! Wir melden uns bei dir.',
                    'show_name' => 1,
                    'show_phone' => 1,
                ]]]
            )),
        ]], JSON_UNESCAPED_UNICODE);
    }

    /** Fertige Unterseiten der Beispiel-Website (inkl. der Pflichtseiten). */
    private static function seedExamplePages(PDO $pdo, int $layoutId, string $siteName): void
    {
        $ph = '<p><strong>[Bitte ergänzen]</strong> ';
        $pages = [
            ['Leistungen', 'leistungen', 1, 1, ['rows' => [
                self::row('lst', self::textBlocks('lst', 'Unsere Leistungen', 'h1',
                    '<p>Hier beschreibst du, was du anbietest. Ein Absatz je Leistung genügt – '
                        . 'kurz, konkret und in der Sprache deiner Kundschaft.</p>')),
            ]]],
            ['Über uns', 'ueber-uns', 1, 2, ['rows' => [
                self::row('abt', self::textBlocks('abt', 'Über uns', 'h1',
                    '<p>Erzähle, wer hinter ' . e($siteName) . ' steckt: seit wann es euch gibt, '
                        . 'was euch antreibt und warum man euch vertrauen kann.</p>')),
            ]]],
            ['Kontakt', 'kontakt', 1, 3, ['rows' => [
                self::row('kon', array_merge(
                    self::textBlocks('kon', 'Kontakt', 'h1', $ph . 'Adresse, Telefonnummer und E-Mail eintragen.</p>'),
                    [['id' => 'kon-f', 'type' => 'form', 'data' => [
                        'recipient' => '',
                        'subject' => 'Neue Nachricht über das Kontaktformular',
                        'button_text' => 'Nachricht senden',
                        'success' => 'Vielen Dank! Wir melden uns bei dir.',
                        'show_name' => 1,
                        'show_phone' => 1,
                    ]]]
                )),
            ]]],
            ['Impressum', 'impressum', 0, 4, ['rows' => [
                self::row('imp', self::textBlocks('imp', 'Impressum', 'h1',
                    '<p>Angaben gemäß § 5 DDG:</p>' . $ph . 'Name, Anschrift, Telefon, E-Mail, '
                        . 'ggf. Umsatzsteuer-Identifikationsnummer und Verantwortliche/r für den Inhalt.</p>')),
            ]]],
            ['Datenschutzerklärung', 'datenschutz', 0, 5, ['rows' => [
                self::row('dsg', self::textBlocks('dsg', 'Datenschutzerklärung', 'h1',
                    '<p>Informationen zur Verarbeitung personenbezogener Daten nach Art. 13 DSGVO.</p>'
                        . $ph . 'Verantwortliche Stelle, Zweck der Verarbeitung, Speicherdauer, '
                        . 'Rechte der Betroffenen sowie eingesetzte Dienste (z. B. Kontaktformular) ergänzen.</p>')),
            ]]],
        ];

        $stmt = $pdo->prepare('INSERT INTO pages (title, slug, layout_id, in_menu, menu_order, published, content) VALUES (?, ?, ?, ?, ?, 1, ?)');
        foreach ($pages as [$title, $slug, $inMenu, $order, $content]) {
            $stmt->execute([$title, $slug, $layoutId, $inMenu, $order, json_encode($content, JSON_UNESCAPED_UNICODE)]);
        }
    }
}
