<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Shop;

/**
 * Basis für alle Shop-Verwaltungsseiten: erfordert Admin-Rechte UND einen
 * aktivierten Shop. Ist der Shop nicht aktiv, gibt es keine Shop-Menüpunkte –
 * ein direkter Aufruf landet mit Hinweis in den Einstellungen.
 */
abstract class ShopAdminController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
        if (!Shop::enabled()) {
            flash('error', 'Der Shop ist nicht aktiviert. Aktiviere ihn zuerst in den Einstellungen.');
            redirect('/admin/settings');
        }
    }
}
