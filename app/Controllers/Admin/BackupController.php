<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Database;
use ZipArchive;

/**
 * Komplett-Backup als ZIP-Download: Datenbank-Dump (SQL), alle Uploads
 * (Medien & Schriften) und die Konfigurationsdatei.
 */
class BackupController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
    }

    public function download(): void
    {
        if (!class_exists('ZipArchive')) {
            flash('error', 'Die PHP-Erweiterung "zip" fehlt auf diesem Server.');
            redirect('/admin/update');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'bw-backup-') ?: BASE_PATH . '/cache/backup.zip';
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            flash('error', 'Backup-Datei konnte nicht erstellt werden.');
            redirect('/admin/update');
        }

        $zip->addFromString('datenbank.sql', $this->dumpDatabase());
        if (is_file(CONFIG_FILE)) {
            $zip->addFile(CONFIG_FILE, 'config/config.php');
        }
        $this->addFolder($zip, BASE_PATH . '/public/uploads', 'public/uploads');
        $zip->addFromString('LIESMICH.txt',
            "Blockwerk-Backup vom " . date('d.m.Y H:i') . "\n\n"
            . "Wiederherstellen:\n"
            . "1. Blockwerk installieren (oder bestehende Installation nutzen).\n"
            . "2. datenbank.sql in die Datenbank einspielen (z. B. phpMyAdmin → Importieren).\n"
            . "3. Den Ordner public/uploads in die Installation kopieren.\n"
            . "4. Bei einem Serverumzug config/config.php anpassen (Zugangsdaten).\n");
        $zip->close();

        $filename = 'blockwerk-backup-' . date('Y-m-d-Hi') . '.zip';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) filesize($tmp));
        readfile($tmp);
        unlink($tmp);
        exit;
    }

    /**
     * Chunk-Upload der Backup-ZIP: Der Browser lädt die Datei in kleinen
     * Häppchen hoch (Fortschrittsanzeige, keine Server-Größenlimits). Jeder
     * Chunk wird an eine temporäre Datei angehängt. Das CSRF-Token kommt im
     * Header – so scheitert der Upload nicht mehr am Formular-Token, wenn die
     * Datei groß ist. Rohdaten stehen in php://input.
     */
    public function restoreChunk(): void
    {
        header('Content-Type: application/json');
        $token = $this->restoreToken($_GET['token'] ?? '');
        $index = (int) ($_GET['index'] ?? -1);
        if ($token === '' || $index < 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Ungültige Upload-Daten.']);
            return;
        }

        $path = $this->restorePath($token);
        $data = file_get_contents('php://input') ?: '';
        // Beim ersten Chunk neu anlegen, danach anhängen.
        $ok = file_put_contents($path, $data, $index === 0 ? 0 : FILE_APPEND) !== false;
        if (!$ok) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Chunk konnte nicht gespeichert werden (Speicherplatz?).']);
            return;
        }
        echo json_encode(['ok' => true, 'size' => filesize($path) ?: 0]);
    }

    /**
     * Wiederherstellung ausführen und den Fortschritt als Stream (eine
     * JSON-Zeile pro Schritt) an den Browser senden. Spielt den Datenbank-
     * Dump zurück und stellt die Uploads wieder her; die Konfigurationsdatei
     * bleibt unangetastet. Die installierte Blockwerk-Version bleibt erhalten:
     * das Schema wird nach dem Import auf den aktuellen Code-Stand gebracht.
     */
    public function restoreRun(): void
    {
        header('Content-Type: application/x-ndjson; charset=utf-8');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no'); // nginx: nicht puffern
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        $emit = static function (array $data): void {
            echo json_encode($data) . "\n";
            @ob_flush();
            @flush();
        };

        $token = $this->restoreToken($_GET['token'] ?? '');
        $path = $token !== '' ? $this->restorePath($token) : '';

        if (!class_exists('ZipArchive')) {
            $emit(['error' => 'Die PHP-Erweiterung "zip" fehlt auf diesem Server.']);
            return;
        }
        if ($path === '' || !is_file($path)) {
            $emit(['error' => 'Die hochgeladene Datei wurde nicht gefunden. Bitte erneut versuchen.']);
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            @unlink($path);
            $emit(['error' => 'Die Datei ist keine gültige ZIP-Sicherung.']);
            return;
        }
        $sql = $zip->getFromName('datenbank.sql');
        if ($sql === false) {
            $zip->close();
            @unlink($path);
            $emit(['error' => 'In der ZIP fehlt „datenbank.sql". Bitte eine mit „Backup herunterladen" erstellte Sicherung verwenden.']);
            return;
        }

        $statements = $this->splitSql($sql);
        $uploadEntries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false && str_starts_with($name, 'public/uploads/') && !str_ends_with($name, '/') && !str_contains($name, '..')) {
                $uploadEntries[] = $name;
            }
        }
        $total = count($statements) + count($uploadEntries);
        $done = 0;
        $emit(['phase' => 'start', 'total' => $total]);

        try {
            $pdo = Database::pdo();
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($statements as $statement) {
                $pdo->exec($statement);
                $done++;
                if ($done % 15 === 0) {
                    $emit(['phase' => 'db', 'done' => $done, 'total' => $total]);
                }
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            $emit(['phase' => 'db', 'done' => $done, 'total' => $total]);
        } catch (\Throwable $e) {
            $zip->close();
            @unlink($path);
            $emit(['error' => 'Datenbank-Wiederherstellung fehlgeschlagen: ' . $e->getMessage()]);
            return;
        }

        // Uploads (Medien & Schriften) wiederherstellen.
        $target = BASE_PATH . '/public';
        foreach ($uploadEntries as $name) {
            $dest = $target . '/' . substr($name, strlen('public/'));
            $dir = dirname($dest);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $stream = $zip->getStream($name);
            if ($stream !== false) {
                $out = @fopen($dest, 'wb');
                if ($out !== false) {
                    stream_copy_to_stream($stream, $out);
                    fclose($out);
                }
                fclose($stream);
            }
            $done++;
            if ($done % 8 === 0) {
                $emit(['phase' => 'files', 'done' => $done, 'total' => $total]);
            }
        }
        $zip->close();
        @unlink($path);

        // Version der Installation bewahren: Schema auf aktuellen Code-Stand
        // bringen (fügt neue Spalten hinzu) und schema_version zurücksetzen.
        Database::createSchema($pdo);
        \Models\Setting::set('schema_version', \Core\Updater::currentVersion());

        \Core\Cache::clear();
        $emit(['phase' => 'done', 'done' => $total, 'total' => $total]);
    }

    /** Nur Hex-Zeichen als Token zulassen (dient als Dateiname). */
    private function restoreToken(mixed $raw): string
    {
        $token = preg_replace('/[^a-f0-9]/', '', strtolower((string) $raw)) ?? '';
        return strlen($token) >= 8 && strlen($token) <= 64 ? $token : '';
    }

    private function restorePath(string $token): string
    {
        $dir = BASE_PATH . '/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/restore-' . $token . '.part';
    }

    /**
     * Zerlegt einen SQL-Dump in einzelne Anweisungen und beachtet dabei
     * Zeichenketten in einfachen Anführungszeichen samt Backslash-Escapes –
     * so werden Semikolons innerhalb von Werten nicht als Trenner erkannt.
     *
     * @return string[]
     */
    private function splitSql(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inString = false;
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];
            if ($inString) {
                $buffer .= $char;
                if ($char === '\\' && $i + 1 < $len) {
                    $buffer .= $sql[++$i]; // Escape-Zeichen mitnehmen
                } elseif ($char === "'") {
                    $inString = false;
                }
                continue;
            }
            if ($char === "'") {
                $inString = true;
                $buffer .= $char;
                continue;
            }
            if ($char === ';') {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }
        // Kommentar-Zeilen (-- …) als eigenständige Anweisungen entfernen.
        return array_values(array_filter(
            $statements,
            static fn (string $s): bool => !str_starts_with($s, '--')
        ));
    }

    private function addFolder(ZipArchive $zip, string $dir, string $prefix): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relative = substr($file->getPathname(), strlen($dir) + 1);
                $zip->addFile($file->getPathname(), $prefix . '/' . str_replace('\\', '/', $relative));
            }
        }
    }

    /** Reiner PHP-Dump: CREATE TABLE + INSERTs für alle Tabellen. */
    private function dumpDatabase(): string
    {
        $pdo = Database::pdo();
        $sql = "-- Blockwerk-Datenbank-Backup vom " . date('Y-m-d H:i:s') . "\n"
            . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

        $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(\PDO::FETCH_NUM);
            $sql .= "DROP TABLE IF EXISTS `$table`;\n" . $create[1] . ";\n\n";

            $rows = $pdo->query('SELECT * FROM `' . $table . '`');
            foreach ($rows as $row) {
                $values = array_map(
                    static fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value),
                    array_values($row)
                );
                $columns = '`' . implode('`, `', array_keys($row)) . '`';
                $sql .= "INSERT INTO `$table` ($columns) VALUES (" . implode(', ', $values) . ");\n";
            }
            $sql .= "\n";
        }
        return $sql . "SET FOREIGN_KEY_CHECKS = 1;\n";
    }
}
