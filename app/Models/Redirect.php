<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

/** Automatische 301-Weiterleitungen nach Slug-Änderungen. */
class Redirect
{
    public static function find(string $fromSlug): ?string
    {
        $stmt = Database::pdo()->prepare('SELECT to_slug FROM redirects WHERE from_slug = ?');
        $stmt->execute([$fromSlug]);
        $to = $stmt->fetchColumn();
        return $to !== false ? (string) $to : null;
    }

    public static function set(string $fromSlug, string $toSlug): void
    {
        if ($fromSlug === $toSlug || $fromSlug === '' || $toSlug === '') {
            return;
        }
        $pdo = Database::pdo();
        // Keine Weiterleitung VON einem Slug, der (wieder) existiert.
        $pdo->prepare('DELETE FROM redirects WHERE from_slug = ?')->execute([$toSlug]);
        // Bestehende Ketten direkt auf das neue Ziel zeigen lassen.
        $pdo->prepare('UPDATE redirects SET to_slug = ? WHERE to_slug = ?')->execute([$toSlug, $fromSlug]);
        $pdo->prepare('INSERT INTO redirects (from_slug, to_slug) VALUES (?, ?) ON DUPLICATE KEY UPDATE to_slug = VALUES(to_slug)')
            ->execute([$fromSlug, $toSlug]);
    }
}
