<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\SepaXml;
use Models\ShopOrder;

class ShopSepaController extends ShopAdminController
{
    public function index(): void
    {
        $this->view('admin/shop/sepa', [
            'title' => 'SEPA-Lastschriften',
            'active' => 'shop-sepa',
            'orders' => ShopOrder::pendingSepa(),
        ]);
    }

    /** Sammeldatei (pain.008) für die ausgewählten Bestellungen erzeugen und zum Download anbieten. */
    public function export(): void
    {
        $ids = array_map('intval', (array) ($_POST['order_ids'] ?? []));
        $collectionDate = trim((string) ($_POST['collection_date'] ?? ''));
        if ($ids === [] || $collectionDate === '') {
            flash('error', 'Bitte mindestens eine Bestellung und ein Fälligkeitsdatum wählen.');
            redirect('/admin/shop/sepa');
        }
        $orders = array_values(array_filter(
            ShopOrder::pendingSepa(),
            static fn ($o) => in_array((int) $o['id'], $ids, true)
        ));
        if ($orders === []) {
            flash('error', 'Keine passenden Bestellungen gefunden.');
            redirect('/admin/shop/sepa');
        }
        $xml = SepaXml::build($orders, $collectionDate);
        ShopOrder::markSepaSubmitted(array_column($orders, 'id'));
        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="sepa-lastschrift-' . date('Y-m-d') . '.xml"');
        header('Content-Length: ' . strlen($xml));
        echo $xml;
    }
}
