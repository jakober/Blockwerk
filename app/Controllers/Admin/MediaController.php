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
            'isImage' => str_starts_with($m['mime'], 'image/'),
        ], Media::all());
        echo json_encode(['items' => $items], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function upload(): void
    {
        $files = $_FILES['files'] ?? null;
        if ($files === null || !is_array($files['name'] ?? null)) {
            flash('error', 'Keine Dateien ausgewählt.');
            redirect('/admin/media');
        }

        $dir = BASE_PATH . '/public/uploads';
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            flash('error', 'Das Upload-Verzeichnis konnte nicht angelegt werden.');
            redirect('/admin/media');
        }

        $uploaded = 0;
        $errors = [];
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

            $width = $height = null;
            if (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml') {
                $info = @getimagesize($dir . '/' . $filename);
                if ($info !== false) {
                    [$width, $height] = $info;
                }
            }

            Media::create((string) $name, 'uploads/' . $filename, $mime, (int) $files['size'][$i], $width, $height);
            $uploaded++;
        }
        finfo_close($finfo);

        if ($uploaded > 0) {
            flash('success', $uploaded . ' Datei(en) hochgeladen.' . ($errors ? ' Übersprungen: ' . implode(', ', $errors) : ''));
        } else {
            flash('error', $errors ? 'Nichts hochgeladen. ' . implode(', ', $errors) : 'Nichts hochgeladen.');
        }
        redirect('/admin/media');
    }

    public function delete(string $id): void
    {
        $item = Media::find((int) $id);
        if ($item !== null) {
            $file = BASE_PATH . '/public/' . $item['path'];
            if (is_file($file)) {
                unlink($file);
            }
            Media::delete((int) $item['id']);
            flash('success', 'Datei gelöscht.');
        }
        redirect('/admin/media');
    }
}
