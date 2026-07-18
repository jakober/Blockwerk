<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Models\Media;

class MediaController extends AdminController
{
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        'application/pdf' => 'pdf',
    ];

    public function index(): void
    {
        $this->view('admin/media/index', [
            'title' => 'Mediathek',
            'active' => 'media',
            'media' => Media::all(),
            'maxUpload' => ini_get('upload_max_filesize'),
        ]);
    }

    /** JSON-Liste für den Medien-Auswahldialog im Editor. */
    public function list(): void
    {
        header('Content-Type: application/json');
        $items = array_map(static fn (array $m): array => [
            'id' => (int) $m['id'],
            'name' => $m['filename'],
            'url' => url('/' . $m['path']),
            'thumb' => self::thumbUrl($m['path']),
            'isImage' => str_starts_with($m['mime'], 'image/'),
        ], Media::all());
        echo json_encode(['items' => $items], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function upload(): void
    {
        // Moderner Drag-&-Drop-Upload schickt die Dateien per fetch/XHR und
        // erwartet JSON; das klassische Formular bleibt als Fallback.
        $wantsJson = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

        $files = $_FILES['files'] ?? null;
        if ($files === null || !is_array($files['name'] ?? null)) {
            if ($wantsJson) {
                $this->json(['ok' => false, 'uploaded' => 0, 'errors' => ['Keine Dateien ausgewählt.'], 'items' => []]);
            }
            flash('error', 'Keine Dateien ausgewählt.');
            redirect('/admin/media');
        }

        $dir = BASE_PATH . '/public/uploads';
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            if ($wantsJson) {
                $this->json(['ok' => false, 'uploaded' => 0, 'errors' => ['Das Upload-Verzeichnis konnte nicht angelegt werden.'], 'items' => []]);
            }
            flash('error', 'Das Upload-Verzeichnis konnte nicht angelegt werden.');
            redirect('/admin/media');
        }

        $uploaded = 0;
        $errors = [];
        $items = [];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        foreach ($files['name'] as $i => $name) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                if ($files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                    $errors[] = $name . ' (Upload-Fehler, evtl. zu groß)';
                }
                continue;
            }
            $tmp = $files['tmp_name'][$i];
            $mime = finfo_file($finfo, $tmp) ?: '';
            if (!isset(self::ALLOWED[$mime])) {
                $errors[] = $name . ' (Dateityp nicht erlaubt)';
                continue;
            }

            $base = slugify(pathinfo((string) $name, PATHINFO_FILENAME)) ?: 'datei';
            $filename = $base . '-' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . self::ALLOWED[$mime];

            if (!move_uploaded_file($tmp, $dir . '/' . $filename)) {
                $errors[] = $name . ' (konnte nicht gespeichert werden)';
                continue;
            }

            // Große Bilder automatisch verkleinern und Thumbnail erzeugen.
            self::optimizeImage($dir . '/' . $filename, $mime);

            $width = $height = null;
            if (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml') {
                $info = @getimagesize($dir . '/' . $filename);
                if ($info !== false) {
                    [$width, $height] = $info;
                }
            }

            $id = Media::create((string) $name, 'uploads/' . $filename, $mime, (int) filesize($dir . '/' . $filename), $width, $height);
            $items[] = [
                'id' => $id,
                'name' => (string) $name,
                'url' => url('/uploads/' . $filename),
                'thumb' => self::thumbUrl('uploads/' . $filename),
                'isImage' => str_starts_with($mime, 'image/'),
                'width' => $width,
                'height' => $height,
                'size' => (int) filesize($dir . '/' . $filename),
                'deleteUrl' => url('/admin/media/' . $id . '/delete'),
            ];
            $uploaded++;
        }
        finfo_close($finfo);

        if ($wantsJson) {
            $this->json(['ok' => $uploaded > 0, 'uploaded' => $uploaded, 'errors' => $errors, 'items' => $items]);
        }

        if ($uploaded > 0) {
            flash('success', $uploaded . ' Datei(en) hochgeladen.' . ($errors ? ' Übersprungen: ' . implode(', ', $errors) : ''));
        } else {
            flash('error', $errors ? 'Nichts hochgeladen. ' . implode(', ', $errors) : 'Nichts hochgeladen.');
        }
        redirect('/admin/media');
    }

    private function json(array $data): never
    {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function delete(string $id): void
    {
        $item = Media::find((int) $id);
        if ($item !== null) {
            $file = BASE_PATH . '/public/' . $item['path'];
            if (is_file($file)) {
                unlink($file);
            }
            $thumb = BASE_PATH . '/public/' . self::thumbPath($item['path']);
            if (is_file($thumb)) {
                unlink($thumb);
            }
            Media::delete((int) $item['id']);
            flash('success', 'Datei gelöscht.');
        }
        redirect('/admin/media');
    }

    /** Pfad des Thumbnails zu einem Medienpfad ("bild.jpg" → "bild-thumb.jpg"). */
    public static function thumbPath(string $path): string
    {
        $dot = strrpos($path, '.');
        return $dot === false ? $path . '-thumb' : substr($path, 0, $dot) . '-thumb' . substr($path, $dot);
    }

    /** Thumbnail-URL, falls vorhanden – sonst das Original. */
    public static function thumbUrl(string $path): string
    {
        $thumb = self::thumbPath($path);
        return url('/' . (is_file(BASE_PATH . '/public/' . $thumb) ? $thumb : $path));
    }

    private const MAX_WIDTH = 1920;
    private const THUMB_WIDTH = 480;

    /**
     * Verkleinert zu große Bilder auf max. 1920px Breite (schnellere Website)
     * und legt ein Thumbnail für Mediathek/Auswahldialog an. Ohne GD-Erweiterung
     * oder bei GIF/SVG/PDF passiert nichts.
     */
    private static function optimizeImage(string $file, string $mime): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            return;
        }
        $loaders = [
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png' => 'imagecreatefrompng',
            'image/webp' => 'imagecreatefromwebp',
        ];
        if (!isset($loaders[$mime]) || !function_exists($loaders[$mime])) {
            return;
        }
        $image = @$loaders[$mime]($file);
        if ($image === false) {
            return;
        }

        $save = static function ($img, string $target) use ($mime): void {
            match ($mime) {
                'image/jpeg' => imagejpeg($img, $target, 85),
                'image/png' => imagepng($img, $target, 6),
                'image/webp' => imagewebp($img, $target, 82),
            };
        };

        if (imagesx($image) > self::MAX_WIDTH) {
            $resized = imagescale($image, self::MAX_WIDTH, -1, IMG_BICUBIC);
            if ($resized !== false) {
                imagedestroy($image);
                $image = $resized;
                if ($mime === 'image/png') {
                    imagesavealpha($image, true);
                }
                $save($image, $file);
            }
        }

        if (imagesx($image) > self::THUMB_WIDTH) {
            $thumb = imagescale($image, self::THUMB_WIDTH, -1, IMG_BICUBIC);
            if ($thumb !== false) {
                if ($mime === 'image/png') {
                    imagesavealpha($thumb, true);
                }
                $save($thumb, dirname($file) . '/' . basename(self::thumbPath($file)));
                imagedestroy($thumb);
            }
        }
        imagedestroy($image);
    }
}
