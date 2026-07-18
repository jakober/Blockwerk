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
     * Komplette Wiederherstellung aus einer heruntergeladenen Backup-ZIP:
     * spielt den Datenbank-Dump zurück und stellt die Uploads wieder her.
     * Die Konfigurationsdatei (config/config.php) wird bewusst NICHT
     * überschrieben, damit die Datenbank-Verbindung der aktuellen
     * Installation erhalten bleibt.
     */
    public function restore(): void
    {
        if (!class_exists('ZipArchive')) {
            flash('error', 'Die PHP-Erweiterung "zip" fehlt auf diesem Server.');
            redirect('/admin/update');
        }

        $file = $_FILES['backup'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            flash('error', $this->uploadError($file['error'] ?? UPLOAD_ERR_NO_FILE));
            redirect('/admin/update');
        }

        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) {
            flash('error', 'Die Datei konnte nicht gelesen werden – ist es eine gültige ZIP-Datei?');
            redirect('/admin/update');
        }

        $sql = $zip->getFromName('datenbank.sql');
        if ($sql === false) {
            $zip->close();
            flash('error', 'In der ZIP fehlt die Datei „datenbank.sql". Bitte eine mit „Backup herunterladen" erstellte Sicherung verwenden.');
            redirect('/admin/update');
        }

        try {
            $this->importDatabase($sql);
        } catch (\Throwable $e) {
            $zip->close();
            flash('error', 'Datenbank-Wiederherstellung fehlgeschlagen: ' . $e->getMessage());
            redirect('/admin/update');
        }

        $restoredFiles = $this->restoreUploads($zip);
        $zip->close();

        \Core\Cache::clear();
        flash('success', 'Sicherung wiederhergestellt: Datenbank eingespielt und ' . $restoredFiles . ' Upload-Datei(en) zurückgesetzt. Die Konfiguration blieb unverändert.');
        redirect('/admin/update');
    }

    /** Führt den SQL-Dump anweisungsweise aus (quote-sicher zerlegt). */
    private function importDatabase(string $sql): void
    {
        $pdo = Database::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->splitSql($sql) as $statement) {
            $pdo->exec($statement);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
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

    /** Stellt die Dateien unter public/uploads/ aus der ZIP wieder her. */
    private function restoreUploads(ZipArchive $zip): int
    {
        $target = BASE_PATH . '/public';
        $count = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || !str_starts_with($name, 'public/uploads/') || str_ends_with($name, '/')) {
                continue;
            }
            // Pfad-Traversal-Schutz.
            if (str_contains($name, '..')) {
                continue;
            }
            $dest = $target . '/' . substr($name, strlen('public/'));
            $dir = dirname($dest);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $stream = $zip->getStream($name);
            if ($stream === false) {
                continue;
            }
            $out = @fopen($dest, 'wb');
            if ($out !== false) {
                stream_copy_to_stream($stream, $out);
                fclose($out);
                $count++;
            }
            fclose($stream);
        }
        return $count;
    }

    private function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_NO_FILE => 'Bitte zuerst eine Backup-ZIP auswählen.',
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Die Datei ist größer als vom Server erlaubt (siehe upload_max_filesize / post_max_size).',
            default => 'Die Datei konnte nicht hochgeladen werden (Fehlercode ' . $code . ').',
        };
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
