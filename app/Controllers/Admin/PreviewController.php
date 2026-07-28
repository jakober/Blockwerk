<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\BlockRegistry;

/**
 * Rendert Blöcke serverseitig für die Live-Vorschau im Editor –
 * dieselbe Render-Logik wie im Frontend (WYSIWYG).
 */
class PreviewController extends AdminController
{
    public function blocks(): void
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input') ?: '', true);
        $renderer = new \Core\Renderer();
        $out = [];
        foreach ((array) ($data['blocks'] ?? []) as $block) {
            if (is_array($block) && in_array($block['type'] ?? '', BlockRegistry::types(), true)) {
                $html = BlockRegistry::render([
                    'type' => $block['type'],
                    'data' => BlockRegistry::sanitizeData((array) ($block['data'] ?? [])),
                ]);
                // Platzhalter (Menü, Marke, Inhaltsbereich …) live auflösen.
                $out[] = $renderer->fillForPreview($html);
            } else {
                $out[] = '';
            }
        }
        echo json_encode(['html' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
