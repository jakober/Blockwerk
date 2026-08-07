<?php
declare(strict_types=1);

namespace Controllers;

use Core\Cart;
use Core\CustomerAuth;
use Core\Mailer;
use Core\PayPal;
use Core\Renderer;
use Core\Shop;
use Core\View;
use Models\Customer;
use Models\Setting;
use Models\ShopCategory;
use Models\ShopOrder;
use Models\ShopProduct;
use Models\ShopShipping;

class ShopController
{
    private function render(string $view, string $title, array $data = [], string $metaDescription = ''): void
    {
        $data['shop_title'] = $title;
        $html = View::fetch('shop/' . $view, $data);
        echo (new Renderer())->renderRaw($title, $html, Renderer::metaHead($title, $metaDescription));
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
        $pageSize = max(1, (int) Setting::get('shop_page_size', '24'));
        $page = max(1, (int) ($_GET['seite'] ?? 1));
        $total = ShopProduct::queryCount($ids, $opts);
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);

        $title = trim((string) ($cat['meta_title'] ?? '')) !== '' ? (string) $cat['meta_title'] : $cat['name'];
        $description = trim((string) ($cat['meta_description'] ?? '')) !== '' ? (string) $cat['meta_description'] : (string) ($cat['description'] ?? '');
        $this->render('category', $title, [
            'category' => $cat,
            'subcategories' => array_filter(ShopCategory::all(), fn ($c) => (int) ($c['parent_id'] ?? 0) === (int) $cat['id']),
            'products' => ShopProduct::query($ids, $opts, $pageSize, ($page - 1) * $pageSize),
            'opts' => $opts,
            'range' => ShopProduct::priceRange(),
            'page' => $page,
            'pages' => $pages,
        ], $description);
    }

    public function product(string $slug): void
    {
        $product = ShopProduct::findBySlug($slug);
        if ($product === null || (int) $product['active'] !== 1) {
            (new SiteController())->notFound();
            return;
        }
        $cat = $product['category_id'] ? ShopCategory::find((int) $product['category_id']) : null;
        $title = trim((string) ($product['meta_title'] ?? '')) !== '' ? (string) $product['meta_title'] : $product['name'];
        $description = trim((string) ($product['meta_description'] ?? '')) !== '' ? (string) $product['meta_description'] : (string) ($product['short_desc'] ?? '');

        $customerId = CustomerAuth::id();
        $canReview = $customerId !== null
            && ShopOrder::customerPurchasedProduct($customerId, (int) $product['id'])
            && !\Models\ShopReview::hasReviewed($customerId, (int) $product['id']);

        $this->render('product', $title, [
            'product' => $product,
            'category' => $cat,
            'gallery' => array_filter(array_map('trim', explode("\n", (string) ($product['gallery'] ?? '')))),
            'tiers' => ShopProduct::tiers($product),
            'optionGroups' => ShopProduct::options($product),
            'crossSell' => array_filter(ShopProduct::relatedProducts($product, 'cross_sell'), fn ($r) => (int) $r['id'] !== (int) $product['id']),
            'accessories' => array_filter(ShopProduct::relatedProducts($product, 'accessories'), fn ($r) => (int) $r['id'] !== (int) $product['id']),
            'reviews' => \Models\ShopReview::approvedForProduct((int) $product['id']),
            'reviewSummary' => \Models\ShopReview::summaryForProduct((int) $product['id']),
            'canReview' => $canReview,
        ], $description);
    }

    public function reviewSubmit(string $slug): void
    {
        CustomerAuth::requireLogin();
        $product = ShopProduct::findBySlug($slug);
        if ($product === null) {
            (new SiteController())->notFound();
            return;
        }
        $customerId = (int) CustomerAuth::id();
        if (!ShopOrder::customerPurchasedProduct($customerId, (int) $product['id'])) {
            flash('error', 'Eine Bewertung ist nur nach dem Kauf dieses Produkts möglich.');
            redirect($this->path('produkt/' . $slug));
        }
        if (\Models\ShopReview::hasReviewed($customerId, (int) $product['id'])) {
            flash('error', 'Du hast dieses Produkt bereits bewertet.');
            redirect($this->path('produkt/' . $slug));
        }
        $rating = (int) ($_POST['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            flash('error', 'Bitte eine Bewertung von 1 bis 5 Sternen abgeben.');
            redirect($this->path('produkt/' . $slug));
        }
        $customer = CustomerAuth::current();
        $name = trim((string) ($customer['first_name'] ?? '')) !== ''
            ? trim((string) $customer['first_name'])
            : 'Verifizierter Käufer';
        \Models\ShopReview::create((int) $product['id'], $customerId, $name, $rating, trim((string) ($_POST['text'] ?? '')));
        flash('success', 'Danke für deine Bewertung! Sie erscheint nach kurzer Prüfung.');
        redirect($this->path('produkt/' . $slug));
    }

    /* ---------- Warenkorb ---------- */

    public function cart(): void
    {
        $this->render('cart', 'Warenkorb', [
            'items' => Cart::items(),
            'subtotal' => Cart::subtotal(),
            'coupon' => Cart::coupon(),
            'discount' => Cart::discount(),
            'total' => Cart::total(),
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

    /* ---------- Gutschein ---------- */

    private function couponBack(): string
    {
        $back = (string) ($_POST['back'] ?? 'warenkorb');
        return $this->path($back === 'kasse' ? 'kasse' : 'warenkorb');
    }

    public function couponApply(): void
    {
        $code = trim((string) ($_POST['coupon_code'] ?? ''));
        if ($code === '') {
            flash('error', 'Bitte einen Gutscheincode eingeben.');
            redirect($this->couponBack());
        }
        $result = Cart::applyCoupon($code);
        if ($result['ok']) {
            flash('success', 'Gutschein „' . $code . '" wurde angewendet.');
        } else {
            flash('error', $result['error']);
        }
        redirect($this->couponBack());
    }

    public function couponRemove(): void
    {
        Cart::removeCoupon();
        flash('success', 'Gutschein wurde entfernt.');
        redirect($this->couponBack());
    }

    /* ---------- Merkliste ---------- */

    private function wishBack(): string
    {
        $back = trim((string) ($_POST['back'] ?? ''));
        // Nur einen internen, relativen Pfad zulassen – kein Open-Redirect.
        if ($back !== '' && str_starts_with($back, '/') && !str_starts_with($back, '//')) {
            return $back;
        }
        return $this->path('merkliste');
    }

    public function wishlistAdd(): void
    {
        CustomerAuth::requireLogin();
        \Models\ShopWishlist::add((int) CustomerAuth::id(), (int) ($_POST['product_id'] ?? 0));
        redirect($this->wishBack());
    }

    public function wishlistRemove(): void
    {
        CustomerAuth::requireLogin();
        \Models\ShopWishlist::remove((int) CustomerAuth::id(), (int) ($_POST['product_id'] ?? 0));
        redirect($this->wishBack());
    }

    public function wishlistPage(): void
    {
        CustomerAuth::requireLogin();
        $this->render('wishlist', 'Merkliste', [
            'products' => \Models\ShopWishlist::products((int) CustomerAuth::id()),
        ]);
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
        // Eingeloggte Kunden: Formular mit Profildaten vorbefüllen (bzw. der
        // Standardadresse, falls vorhanden).
        $customer = \Core\CustomerAuth::current();
        $addresses = [];
        $form = $_SESSION['shop_checkout'] ?? [];
        if ($customer !== null) {
            $addresses = \Models\ShopCustomerAddress::forCustomer((int) $customer['id']);
            if ($form === []) {
                $default = \Models\ShopCustomerAddress::defaultShipping((int) $customer['id']);
                $form = [
                    'email' => $customer['email'],
                    'first_name' => (string) ($default['first_name'] ?? $customer['first_name'] ?? ''),
                    'last_name' => (string) ($default['last_name'] ?? $customer['last_name'] ?? ''),
                    'company' => (string) ($default['company'] ?? ''),
                    'street' => (string) ($default['street'] ?? ''),
                    'zip' => (string) ($default['zip'] ?? ''),
                    'city' => (string) ($default['city'] ?? ''),
                    'country' => (string) ($default['country'] ?? ''),
                    'phone' => (string) ($default['phone'] ?? ''),
                ];
            }
        }
        $agbPage = \Models\Page::findBySlug('agb');
        $widerrufPage = \Models\Page::findBySlug('widerrufsbelehrung');
        $this->render('checkout', 'Kasse', [
            'items' => Cart::items(),
            'subtotal' => Cart::subtotal(),
            'weight' => Cart::weight(),
            'coupon' => Cart::coupon(),
            'discount' => Cart::discount(),
            'shipping' => $shipping,
            'shipCountries' => $shipCountries,
            'payments' => Shop::paymentMethods(),
            'form' => $form,
            'customer' => $customer,
            'addresses' => $addresses,
            'agbUrl' => $agbPage !== null ? url('/' . $agbPage['slug']) : url('/agb'),
            'widerrufUrl' => $widerrufPage !== null ? url('/' . $widerrufPage['slug']) : url('/widerrufsbelehrung'),
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

    /** Rechnung als PDF für den Kunden (über den Bestell-Token) – nur wenn erstellt. */
    public function invoice(string $token): void
    {
        $order = ShopOrder::findByToken($token);
        if ($order === null) {
            (new SiteController())->notFound();
            return;
        }
        $invoice = \Models\Invoice::findByOrder((int) $order['id']);
        if ($invoice === null) {
            (new SiteController())->notFound();
            return;
        }
        $pdf = \Core\InvoicePdf::render($order, ShopOrder::items((int) $order['id']), $invoice);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . \Core\InvoicePdf::filename($invoice) . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: no-store');
        echo $pdf;
    }

    /* ---------- Kundenkonto ---------- */

    public function login(): void
    {
        if (CustomerAuth::check()) {
            redirect($this->path('konto'));
        }
        $email = (string) ($_SESSION['shop_login_email'] ?? '');
        unset($_SESSION['shop_login_email']);
        $this->render('login', 'Anmelden', ['email' => $email]);
    }

    public function doLogin(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if (CustomerAuth::attempt($email, $password)) {
            flash('success', 'Willkommen zurück!');
            redirect($this->path('konto'));
        }
        flash('error', 'E-Mail oder Passwort ist falsch.');
        $_SESSION['shop_login_email'] = $email;
        redirect($this->path('login'));
    }

    public function logout(): void
    {
        CustomerAuth::logout();
        flash('success', 'Du wurdest abgemeldet.');
        redirect('/' . trim(Shop::rootSlug(), '/'));
    }

    public function register(): void
    {
        if (CustomerAuth::check()) {
            redirect($this->path('konto'));
        }
        $form = $_SESSION['shop_register'] ?? [];
        unset($_SESSION['shop_register']);
        $this->render('register', 'Konto erstellen', ['form' => $form]);
    }

    public function doRegister(): void
    {
        $email = trim($_POST['email'] ?? '');
        $first = trim($_POST['first_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $_SESSION['shop_register'] = ['email' => $email, 'first_name' => $first, 'last_name' => $last];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Bitte eine gültige E-Mail-Adresse angeben.');
            redirect($this->path('registrieren'));
        }
        if (strlen($password) < 6) {
            flash('error', 'Das Passwort muss mindestens 6 Zeichen haben.');
            redirect($this->path('registrieren'));
        }
        if (Customer::emailExists($email)) {
            flash('error', 'Für diese E-Mail gibt es bereits ein Konto. Bitte melde dich an.');
            redirect($this->path('login'));
        }
        $id = Customer::create($email, $password, $first, $last);
        CustomerAuth::login(Customer::find($id));
        unset($_SESSION['shop_register']);
        flash('success', 'Konto erstellt – willkommen!');
        redirect($this->path('konto'));
    }

    public function account(): void
    {
        CustomerAuth::requireLogin();
        $customer = CustomerAuth::current();
        if ($customer === null) {
            CustomerAuth::logout();
            redirect($this->path('login'));
        }
        $this->render('account', 'Mein Konto', [
            'customer' => $customer,
            'orders' => ShopOrder::forCustomer((int) $customer['id'], (string) $customer['email']),
        ]);
    }

    /* ---------- Adressbuch ---------- */

    public function addresses(): void
    {
        CustomerAuth::requireLogin();
        $this->render('addresses', 'Adressbuch', [
            'addresses' => \Models\ShopCustomerAddress::forCustomer((int) CustomerAuth::id()),
        ]);
    }

    public function addressCreate(): void
    {
        CustomerAuth::requireLogin();
        $this->render('address-form', 'Neue Adresse', ['address' => null]);
    }

    public function addressEdit(string $id): void
    {
        CustomerAuth::requireLogin();
        $address = \Models\ShopCustomerAddress::find((int) $id, (int) CustomerAuth::id());
        if ($address === null) {
            flash('error', 'Adresse nicht gefunden.');
            redirect($this->path('konto/adressen'));
        }
        $this->render('address-form', 'Adresse bearbeiten', ['address' => $address]);
    }

    private function addressData(): array
    {
        return [
            'label' => trim($_POST['label'] ?? ''),
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'company' => trim($_POST['company'] ?? ''),
            'street' => trim($_POST['street'] ?? ''),
            'zip' => trim($_POST['zip'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'country' => trim($_POST['country'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
        ];
    }

    public function addressStore(): void
    {
        CustomerAuth::requireLogin();
        \Models\ShopCustomerAddress::create((int) CustomerAuth::id(), $this->addressData());
        flash('success', 'Adresse gespeichert.');
        redirect($this->path('konto/adressen'));
    }

    public function addressUpdate(string $id): void
    {
        CustomerAuth::requireLogin();
        \Models\ShopCustomerAddress::update((int) $id, (int) CustomerAuth::id(), $this->addressData());
        flash('success', 'Adresse aktualisiert.');
        redirect($this->path('konto/adressen'));
    }

    public function addressDelete(string $id): void
    {
        CustomerAuth::requireLogin();
        \Models\ShopCustomerAddress::delete((int) $id, (int) CustomerAuth::id());
        flash('success', 'Adresse gelöscht.');
        redirect($this->path('konto/adressen'));
    }

    public function addressSetDefault(string $id): void
    {
        CustomerAuth::requireLogin();
        $customerId = (int) CustomerAuth::id();
        \Models\ShopCustomerAddress::setDefault((int) $id, $customerId, 'shipping');
        \Models\ShopCustomerAddress::setDefault((int) $id, $customerId, 'billing');
        flash('success', 'Als Standardadresse festgelegt.');
        redirect($this->path('konto/adressen'));
    }

    public function forgotPassword(): void
    {
        $this->render('forgot', 'Passwort vergessen', []);
    }

    public function sendReset(): void
    {
        $email = trim($_POST['email'] ?? '');
        $customer = Customer::findByEmail($email);
        if ($customer !== null) {
            $token = Customer::setResetToken((int) $customer['id']);
            $body = "Du hast angefordert, das Passwort deines Kundenkontos neu zu setzen.\n\n"
                . "Setze dein Passwort hier neu (Link ist 1 Stunde gültig):\n"
                . $this->absoluteUrl('passwort-neu/' . $token) . "\n\n"
                . "Falls du das nicht warst, kannst du diese E-Mail ignorieren.";
            Mailer::send((string) $customer['email'], 'Passwort zurücksetzen', $body);
        }
        // Immer dieselbe Rückmeldung – verrät nicht, ob die E-Mail existiert.
        flash('success', 'Falls ein Konto mit dieser E-Mail existiert, haben wir dir einen Link zum Zurücksetzen geschickt.');
        redirect($this->path('login'));
    }

    public function resetPassword(string $token): void
    {
        if (Customer::findByValidResetToken($token) === null) {
            flash('error', 'Der Link ist ungültig oder abgelaufen. Bitte fordere einen neuen an.');
            redirect($this->path('passwort-vergessen'));
        }
        $this->render('reset', 'Neues Passwort', ['token' => $token]);
    }

    public function doResetPassword(string $token): void
    {
        $customer = Customer::findByValidResetToken($token);
        if ($customer === null) {
            flash('error', 'Der Link ist ungültig oder abgelaufen. Bitte fordere einen neuen an.');
            redirect($this->path('passwort-vergessen'));
        }
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 6) {
            flash('error', 'Das Passwort muss mindestens 6 Zeichen haben.');
            redirect($this->path('passwort-neu/' . $token));
        }
        Customer::updatePassword((int) $customer['id'], $password);
        CustomerAuth::login(Customer::find((int) $customer['id']));
        flash('success', 'Dein Passwort wurde geändert – du bist jetzt angemeldet.');
        redirect($this->path('konto'));
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
        foreach ($items as $it) {
            $stock = $it['product']['stock'] ?? null;
            if ($stock !== null && (int) $it['qty'] > (int) $stock) {
                return [[], [], 'Nur noch ' . (int) $stock . ' Stück von „' . $it['product']['name'] . '“ verfügbar.'];
            }
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
        if (($_POST['accept_terms'] ?? '') === '') {
            return [[], [], 'Bitte die AGB und die Widerrufsbelehrung akzeptieren, um die Bestellung abzuschließen.'];
        }

        $payments = Shop::paymentMethods();
        $payment = $_POST['payment_method'] ?? '';
        if (!isset($payments[$payment])) {
            return [[], [], 'Bitte eine Zahlungsart wählen.'];
        }

        $sepaFields = [];
        if ($payment === 'sepa') {
            $holder = trim($_POST['sepa_account_holder'] ?? '');
            $iban = \Core\Iban::normalize($_POST['sepa_iban'] ?? '');
            if ($holder === '' || $iban === '' || !\Core\Iban::isValid($iban)) {
                return [[], [], 'Bitte Kontoinhaber und eine gültige IBAN für die SEPA-Lastschrift angeben.'];
            }
            if (($_POST['sepa_mandate'] ?? '') === '') {
                return [[], [], 'Bitte dem SEPA-Lastschriftmandat zustimmen, um per Lastschrift zu bezahlen.'];
            }
            $sepaFields = [
                'sepa_account_holder' => $holder,
                'sepa_iban' => $iban,
                'sepa_bic' => trim($_POST['sepa_bic'] ?? '') ?: null,
            ];
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
                // Steuersatz zum Bestellzeitpunkt einfrieren – spätere Änderungen
                // am Produkt oder am Standardsatz dürfen bestehende Rechnungen nicht verändern.
                'tax_rate' => Shop::productTaxRate($it['product']),
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

        $coupon = Cart::coupon();
        $discount = $coupon !== null ? \Models\ShopCoupon::discountFor($coupon, $subtotal) : 0;

        $head = $form + $sepaFields + [
            'token' => bin2hex(random_bytes(16)),
            'status' => 'new',
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'discount_cents' => $discount,
            'coupon_code' => $coupon['code'] ?? null,
            'total' => max(0, $subtotal + $shippingCost - $discount),
            'currency' => Shop::currency(),
            'shipping_method' => $shippingName,
            'payment_method' => $payment,
            'payment_status' => 'pending',
            'customer_id' => $this->resolveCustomer($form),
        ];
        return [$head, $orderItems, null];
    }

    /**
     * Ordnet die Bestellung einem Kundenkonto zu: eingeloggt → dessen ID;
     * sonst bei „Konto anlegen" (Checkbox + Passwort) ein neues Konto erstellen
     * und einloggen. Existiert die E-Mail bereits, bleibt es eine Gastbestellung
     * (taucht nach Login per E-Mail-Zuordnung trotzdem im Konto auf).
     */
    private function resolveCustomer(array $form): ?int
    {
        if (CustomerAuth::check()) {
            return CustomerAuth::id();
        }
        if (($_POST['create_account'] ?? '') === '' ) {
            return null;
        }
        $password = (string) ($_POST['account_password'] ?? '');
        $email = $form['email'];
        if (strlen($password) < 6) {
            flash('error', 'Für das Kundenkonto bitte ein Passwort mit mindestens 6 Zeichen wählen. Die Bestellung wurde als Gast gespeichert.');
            return null;
        }
        try {
            if (Customer::emailExists($email)) {
                flash('error', 'Es besteht bereits ein Konto mit dieser E-Mail – bitte melde dich an, um die Bestellung deinem Konto zuzuordnen.');
                return null;
            }
            $id = Customer::create($email, $password, $form['first_name'], $form['last_name']);
            CustomerAuth::login(Customer::find($id));
            flash('success', 'Kundenkonto angelegt – du bist jetzt angemeldet.');
            return $id;
        } catch (\Throwable) {
            // Konto-Anlage darf die Bestellung nie verhindern.
            flash('error', 'Das Kundenkonto konnte nicht angelegt werden – deine Bestellung wurde als Gast gespeichert.');
            return null;
        }
    }

    private function afterOrder(int $orderId, array $items): void
    {
        foreach ($items as $it) {
            if (!empty($it['product_id'])) {
                ShopProduct::decreaseStock((int) $it['product_id'], (int) $it['qty']);
            }
        }
        $order = ShopOrder::find($orderId);
        if ($order === null) {
            return;
        }
        $orderItems = ShopOrder::items($orderId);
        // Rechnung sofort erzeugen (fortlaufende Nummer) und als PDF an die
        // Bestätigung anhängen – bei Vorkasse/Rechnung ist das zugleich die
        // Zahlungsaufforderung, bei sofort bezahlter PayPal-Order die fällige Rechnung.
        $pdf = null;
        $invoice = null;
        try {
            $invoice = \Models\Invoice::createForOrder($orderId);
            $pdf = \Core\InvoicePdf::render($order, $orderItems, $invoice);
        } catch (\Throwable) {
            // Rechnungserzeugung darf die Bestellung nie verhindern – im Backend
            // kann die Rechnung jederzeit nachträglich erstellt werden.
        }
        // Bestätigung an den Besteller + Benachrichtigung an die Shop-Kontakt-E-Mail.
        \Core\ShopMail::confirmation($order, $orderItems, $pdf, $invoice);
        \Core\ShopMail::shopNotification($order, $orderItems);
    }

    private function path(string $sub): string
    {
        return '/' . trim(Shop::rootSlug(), '/') . '/' . ltrim($sub, '/');
    }

    /** Voll qualifizierte URL (Schema + Host) für E-Mail-Links. */
    private function absoluteUrl(string $sub): string
    {
        $scheme = \Core\App::scheme();
        $host = $_SERVER['HTTP_HOST'] ?? '';
        return $scheme . '://' . $host . url($this->path($sub));
    }
}
