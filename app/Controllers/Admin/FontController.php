<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Models\Font;

/**
 * Lädt Google Fonts herunter und speichert sie lokal
 * (public/uploads/fonts/<slug>/), damit sie DSGVO-freundlich ohne
 * Verbindung zu Google-Servern eingebunden werden können.
 */
class FontController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
    }

    public function index(): void
    {
        $this->view('admin/fonts/index', [
            'title' => 'Schriften',
            'active' => 'fonts',
            'fonts' => Font::all(),
        ]);
    }

    public function store(): void
    {
        $family = trim($_POST['family'] ?? '');
        $error = self::download($family);
        if ($error !== null) {
            flash('error', $error);
        } else {
            flash('success', 'Schrift "' . $family . '" heruntergeladen und lokal gespeichert.');
        }
        redirect('/admin/fonts');
    }

    /**
     * Lädt eine Google-Schrift herunter, speichert sie lokal (GDPR) und legt
     * den Font-Eintrag an. Gibt null (Erfolg) oder eine Fehlermeldung zurück.
     * Auch vom KI-Assistenten genutzt.
     */
    public static function download(string $family): ?string
    {
        $family = trim($family);
        if (!preg_match('/^[A-Za-z0-9 ]{2,60}$/', $family)) {
            return 'Bitte einen gültigen Google-Fonts-Namen angeben (z. B. „Inter" oder „Playfair Display").';
        }
        if (Font::findByFolder(slugify($family)) !== null) {
            return 'Die Schrift „' . $family . '" ist bereits installiert.';
        }

        $folder = slugify($family);
        $dir = BASE_PATH . '/public/uploads/fonts/' . $folder;

        try {
            $cssUrl = 'https://fonts.googleapis.com/css2?family='
                . str_replace(' ', '+', $family) . ':wght@400;600;700&display=swap';
            $css = self::fetchStatic($cssUrl);
            if ($css === null || !str_contains($css, '@font-face')) {
                throw new \RuntimeException('Die Schrift wurde bei Google Fonts nicht gefunden.');
            }
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                throw new \RuntimeException('Das Schriften-Verzeichnis konnte nicht angelegt werden.');
            }
            $css = preg_replace_callback('/url\((https:[^)]+)\)/', static function (array $m) use ($dir): string {
                $fontUrl = $m[1];
                $filename = basename(parse_url($fontUrl, PHP_URL_PATH) ?: 'font.woff2');
                $data = self::fetchStatic($fontUrl);
                if ($data === null) {
                    throw new \RuntimeException('Eine Font-Datei konnte nicht geladen werden.');
                }
                file_put_contents($dir . '/' . $filename, $data);
                return 'url(' . $filename . ')';
            }, $css) ?? '';
            file_put_contents($dir . '/font.css', $css);
        } catch (\RuntimeException $e) {
            return 'Download fehlgeschlagen: ' . $e->getMessage();
        }

        Font::create($family, $family, $folder);
        return null;
    }

    public function delete(string $id): void
    {
        $font = Font::find((int) $id);
        if ($font !== null) {
            $dir = BASE_PATH . '/public/uploads/fonts/' . basename($font['folder']);
            if (is_dir($dir)) {
                foreach (glob($dir . '/*') ?: [] as $file) {
                    unlink($file);
                }
                rmdir($dir);
            }
            Font::delete((int) $font['id']);
            flash('success', 'Schrift gelöscht.');
        }
        redirect('/admin/fonts');
    }

    /** Lädt eine URL mit Browser-User-Agent (nötig, damit Google WOFF2 liefert). */
    private static function fetchStatic(string $url): ?string
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_USERAGENT => $ua,
            ]);
            $data = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            return is_string($data) && $status < 400 ? $data : null;
        }

        $context = stream_context_create(['http' => ['header' => 'User-Agent: ' . $ua, 'timeout' => 20]]);
        $data = @file_get_contents($url, false, $context);
        return $data !== false ? $data : null;
    }
}
