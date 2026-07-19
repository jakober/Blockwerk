<?php
declare(strict_types=1);

namespace Controllers;

use Core\Cart;
use Core\PayPal;
use Core\Renderer;
use Core\Shop;
use Core\View;
use Models\Setting;
use Models\ShopCategory;
use Models\ShopOrder;
use Models\ShopProduct;
use Models\ShopShipping;

class ShopController
{
    private function render(string $view, string $title, array $data = []): void
    {
        $data['shop_title'] = $title;
        $html = View::fetch('shop/' . $view, $data);
        echo (new Renderer())->renderRaw($title, $html);
    }

    /* ---------- Katalog ---------- */

    public function index(): void
    {
        $this->render('index', 'Shop', [
            'categories' => array_filter(ShopCategory::tree(), fn ($c) => (int) $c['depth'] === 0),
            'featured' => ShopProduct::featured(8),
        ]);
    }

    public function category(string $slug): void
    {
        $cat = ShopCategory::findBySlug($slug);
        if ($cat === null) {
            (new SiteController())->notFound();
            return;
        }
        $ids = ShopCategory::withDescendants((int) $cat['id']);
        $opts = [
            'search' => trim($_GET['q'] ?? ''),
            'sort' => $_GET['sort'] ?? '',
            'min' => ($_GET['min'] ?? '') !== '' ? Shop::parsePrice((string) $_GET['min']) : '',
            'max' => ($_GET['max'] ?? '') !== '' ? Shop::parsePrice((string) $_GET['max']) : '',
        ];
        $this->render('category', $cat['name'], [
            'category' => $cat,
            'subcategories' => array_filter(ShopCategory::all(), fn ($c) => (int) ($c['parent_id'] ?? 0) === (int) $cat['id']),
            'products' => ShopProduct::query($ids, $opts),
            'opts' => $opts,
            'range' => ShopProduct::priceRange(),
        ]);
    }

    public function product(string $slug): void
    {
        $product = ShopProduct::findBySlug($slug);
        if ($product === null || (int) $product['active'] !== 1) {
            (new SiteController())->notFound();
            return;
        }
        $cat = $product['category_id'] ? ShopCategory::find((int) $product['category_id']) : null;
        $this->render('product', $product['name'], [
            'product' => $product,
            'category' => $cat,
            'gallery' => array_filter(array_map('trim', explode("\n", (string) ($product['gallery'] ?? '')))),
            'tiers' => ShopProduct::tiers($product),
            'optionGroups' => ShopProduct::options($product),
            'crossSell' => array_filter(ShopProduct::relatedProducts($product, 'cross_sell'), fn ($r) => (int) $r['id'] !== (int) $product['id']),
            'accessories' => array_filter(ShopProduct::relatedProducts($product, 'accessories'), fn ($r) => (int) $r['id'] !== (int) $product['id']),
        ]);
    }

    /* ---------- Warenkorb ---------- */

    public function cart(): void
    {
        $this->render('cart', 'Warenkorb', [
            'items' => Cart::items(),
            'subtotal' => Cart::subtotal(),
        ]);
    }

    public function cartAdd(): void
    {
        $id = (int) ($_POST['product_id'] ?? 0);
        $qty = max(1, (int) ($_POST['qty'] ?? 1));
        $opts = is_array($_POST['opt'] ?? null) ? array_map('strval', $_POST['opt']) : [];
        $product = ShopProduct::find($id);
        if ($product !== null && (int) $product['active'] === 1) {
            Cart::add($id, $qty, $opts);
            flash('success', '„' . $product['name'] . '" wurde in den Warenkorb gelegt.');
        }
        redirect($this->path('warenkorb'));
    }

    public function cartUpdate(): void
    {
        // Parallele Arrays ckey[]/qty[] – der Warenkorb-Schlüssel kann
        // Sonderzeichen enthalten und eignet sich nicht als Feldname.
        $keys = (array) ($_POST['ckey'] ?? []);
        $qtys = (array) ($_POST['qty'] ?? []);
        foreach ($keys as $i => $key) {
            Cart::set((string) $key, (int) ($qtys[$i] ?? 0));
        }
        flash('success', 'Warenkorb aktualisiert.');
        redirect($this->path('warenkorb'));
    }

    public function cartRemove(): void
    {
        Cart::remove((string) ($_POST['product_key'] ?? ''));
        redirect($this->path('warenkorb'));
    }

    /* ---------- Kasse ---------- */

    public function checkout(): void
    {
        if (Cart::isEmpty()) {
            flash('error', 'Dein Warenkorb ist leer.');
            redirect($this->path('warenkorb'));
        }
        $shipping = ShopShipping::active();
        // Liefergebiet: gibt es eine weltweite Versandart (ohne Länderliste),
        // stehen alle Länder zur Wahl; sonst nur die bei den Versandarten
        // hinterlegten. Deutschland/Österreich/Schweiz stehen oben.
        $hasWorldwide = false;
        foreach ($shipping as $m) {
            if (ShopShipping::countries($m) === []) {
                $hasWorldwide = true;
                break;
            }
        }
        $shipCountries = $shipping === []
            ? []
            : ($hasWorldwide ? \Core\Countries::all() : \Core\Countries::sort(ShopShipping::allCountries()));
        $this->render('checkout', 'Kasse', [
            'items' => Cart::items(),
            'subtotal' => Cart::subtotal(),
            'weight' => Cart::weight(),
            'shipping' => $shipping,
            'shipCountries' => $shipCountries,
            'payments' => Shop::paymentMethods(),
            'form' => $_SESSION['shop_checkout'] ?? [],
        ]);
    }

    public function placeOrder(): void
    {
        [$head, $items, $error] = $this->buildOrder();
        if ($error !== null) {
            flash('error', $error);
            redirect($this->path('kasse'));
        }
        if (($head['payment_method'] ?? '') === 'paypal') {
            // PayPal läuft über die Buttons (paypalCreate/paypalCapture).
            flash('error', 'Bitte den PayPal-Button zum Bezahlen verwenden.');
            redirect($this->path('kasse'));
        }

        $orderId = ShopOrder::create($head, $items);
        $this->afterOrder($orderId, $items);
        $order = ShopOrder::find($orderId);
        Cart::clear();
        unset($_SESSION['shop_checkout']);
        redirect($this->path('bestellung/' . $order['token']));
    }

    public function orderConfirm(string $token): void
    {
        $order = ShopOrder::findByToken($token);
        if ($order === null) {
            (new SiteController())->notFound();
            return;
        }
        $this->render('confirm', 'Bestellung ' . $order['number'], [
            'order' => $order,
            'items' => ShopOrder::items((int) $order['id']),
            'bankInfo' => Setting::get('shop_bank_info', ''),
        ]);
    }

    /* ---------- PayPal (AJAX) ---------- */

    public function paypalCreate(): void
    {
        header('Content-Type: application/json');
        [$head, $items, $error] = $this->buildOrder();
        if ($error !== null) {
            http_response_code(422);
            echo json_encode(['error' => $error]);
            return;
        }
        $_SESSION['shop_pending'] = ['head' => $head, 'items' => $items];
        [$ppId, $err] = PayPal::createOrder((int) $head['total'], $head['currency'], 'BW-' . substr($head['token'], 0, 8));
        if ($ppId === null) {
            http_response_code(502);
            echo json_encode(['error' => $err ?? 'PayPal-Fehler']);
            return;
        }
        echo json_encode(['id' => $ppId]);
    }

    public function paypalCapture(): void
    {
        header('Content-Type: application/json');
        $ppId = trim($_POST['orderID'] ?? '');
        $pending = $_SESSION['shop_pending'] ?? null;
        if ($ppId === '' || !is_array($pending)) {
            http_response_code(422);
            echo json_encode(['error' => 'Sitzung abgelaufen. Bitte die Kasse erneut aufrufen.']);
            return;
        }
        [$ok, $err] = PayPal::captureOrder($ppId);
        if (!$ok) {
            http_response_code(502);
            echo json_encode(['error' => $err ?? 'Zahlung fehlgeschlagen']);
            return;
        }
        $head = $pending['head'];
        $head['payment_status'] = 'paid';
        $head['status'] = 'paid';
        $head['paypal_order_id'] = $ppId;
        $orderId = ShopOrder::create($head, $pending['items']);
        $this->afterOrder($orderId, $pending['items']);
        $order = ShopOrder::find($orderId);
        Cart::clear();
        unset($_SESSION['shop_pending'], $_SESSION['shop_checkout']);
        echo json_encode(['redirect' => $this->path('bestellung/' . $order['token'])]);
    }

    /* ---------- Helfer ---------- */

    /** @return array{0:array,1:array,2:?string} [orderHead, items, error] */
    private function buildOrder(): array
    {
        $items = Cart::items();
        if ($items === []) {
            return [[], [], 'Dein Warenkorb ist leer.'];
        }

        $required = ['email', 'first_name', 'last_name', 'street', 'zip', 'city'];
        $form = [];
        foreach (['email', 'first_name', 'last_name', 'company', 'street', 'zip', 'city', 'country', 'phone', 'note'] as $k) {
            $form[$k] = trim($_POST[$k] ?? '');
        }
        $_SESSION['shop_checkout'] = $form; // Eingaben für Fehlerfall merken
        foreach ($required as $k) {
            if ($form[$k] === '') {
                return [[], [], 'Bitte alle Pflichtfelder ausfüllen (E-Mail, Name, Adresse).'];
            }
        }
        if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            return [[], [], 'Bitte eine gültige E-Mail-Adresse angeben.'];
        }

        $payments = Shop::paymentMethods();
        $payment = $_POST['payment_method'] ?? '';
        if (!isset($payments[$payment])) {
            return [[], [], 'Bitte eine Zahlungsart wählen.'];
        }

        $subtotal = 0;
        $orderItems = [];
        foreach ($items as $it) {
            $subtotal += $it['line'];
            $orderItems[] = [
                'product_id' => (int) $it['product']['id'],
                'name' => $it['product']['name'] . ($it['optionLabel'] !== '' ? ' (' . $it['optionLabel'] . ')' : ''),
                'sku' => $it['product']['sku'] ?? null,
                'price' => (int) $it['unit'],
                'qty' => $it['qty'],
            ];
        }

        // Versandart – abhängig von Land (Verfügbarkeit) und Warenkorbgewicht (Preis).
        $shippingCost = 0;
        $shippingName = null;
        $weight = Cart::weight();
        $methods = ShopShipping::availableFor($form['country']);
        if ($methods !== []) {
            $chosen = null;
            $sid = (int) ($_POST['shipping_id'] ?? 0);
            foreach ($methods as $m) {
                if ((int) $m['id'] === $sid) {
                    $chosen = $m;
                    break;
                }
            }
            if ($chosen === null) {
                return [[], [], 'Bitte eine für das gewählte Land verfügbare Versandart wählen.'];
            }
            $shippingCost = ShopShipping::costFor($chosen, $subtotal, $weight);
            $shippingName = $chosen['name'];
        } elseif (ShopShipping::active() !== []) {
            return [[], [], 'In das gewählte Land ist derzeit kein Versand möglich.'];
        }

        $head = $form + [
            'token' => bin2hex(random_bytes(16)),
            'status' => 'new',
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'total' => $subtotal + $shippingCost,
            'currency' => Shop::currency(),
            'shipping_method' => $shippingName,
            'payment_method' => $payment,
            'payment_status' => 'pending',
        ];
        return [$head, $orderItems, null];
    }

    private function afterOrder(int $orderId, array $items): void
    {
        foreach ($items as $it) {
            if (!empty($it['product_id'])) {
                ShopProduct::decreaseStock((int) $it['product_id'], (int) $it['qty']);
            }
        }
        $to = Setting::get('shop_email', '');
        if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $order = ShopOrder::find($orderId);
            @mail($to, 'Neue Bestellung ' . $order['number'], 'Es ist eine neue Bestellung eingegangen: ' . $order['number']);
        }
    }

    private function path(string $sub): string
    {
        return '/' . trim(Shop::rootSlug(), '/') . '/' . ltrim($sub, '/');
    }
}
