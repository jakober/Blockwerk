<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

class Layout
{
    public static function all(): array
    {
        return Database::pdo()->query('SELECT * FROM layouts ORDER BY name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM layouts WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Das Standard-Layout: das explizit markierte, sonst als Rückfall das
     * erste (kleinste id). Wird für neue Seiten vorgewählt und überall dort
     * genutzt, wo eine Seite kein eigenes Layout hat.
     */
    public static function default(): ?array
    {
        return Database::pdo()
            ->query('SELECT * FROM layouts ORDER BY is_default DESC, id ASC LIMIT 1')
            ->fetch() ?: null;
    }

    /** @deprecated Alias für default() – historische Aufrufe. */
    public static function first(): ?array
    {
        return self::default();
    }

    public static function defaultId(): ?int
    {
        $layout = self::default();
        return $layout ? (int) $layout['id'] : null;
    }

    /** Ein Layout zum Standard machen (alle anderen verlieren die Markierung). */
    public static function setDefault(int $id): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        $pdo->exec('UPDATE layouts SET is_default = 0');
        $pdo->prepare('UPDATE layouts SET is_default = 1 WHERE id = ?')->execute([$id]);
        $pdo->commit();
    }

    public static function create(string $name, string $html, ?string $design = null, string $headCode = '', string $bodyCode = ''): int
    {
        $pdo = Database::pdo();
        // Das allererste angelegte Layout wird automatisch Standard.
        $isDefault = (int) $pdo->query('SELECT COUNT(*) FROM layouts')->fetchColumn() === 0 ? 1 : 0;
        $pdo->prepare('INSERT INTO layouts (name, html, design, head_code, body_code, is_default) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$name, $html, $design, $headCode, $bodyCode, $isDefault]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, string $name, string $html, ?string $design = null, string $headCode = '', string $bodyCode = ''): void
    {
        Database::pdo()->prepare('UPDATE layouts SET name = ?, html = ?, design = ?, head_code = ?, body_code = ? WHERE id = ?')
            ->execute([$name, $html, $design, $headCode, $bodyCode, $id]);
    }

    public static function saveBuilder(int $id, ?string $json): void
    {
        Database::pdo()->prepare('UPDATE layouts SET builder = ? WHERE id = ?')->execute([$json, $id]);
    }

    public static function delete(int $id): void
    {
        $pdo = Database::pdo();
        $wasDefault = (int) ($pdo->query('SELECT is_default FROM layouts WHERE id = ' . (int) $id)->fetchColumn() ?: 0) === 1;
        $pdo->prepare('UPDATE pages SET layout_id = NULL WHERE layout_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM layouts WHERE id = ?')->execute([$id]);
        // War es das Standard-Layout, rückt das nächste (kleinste id) nach.
        if ($wasDefault) {
            $pdo->exec('UPDATE layouts SET is_default = 1 WHERE id = (SELECT id FROM (SELECT MIN(id) AS id FROM layouts) AS t)');
        }
    }
}
