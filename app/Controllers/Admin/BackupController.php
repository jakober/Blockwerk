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
