<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Models\Customer;
use Models\ShopOrder;

class ShopCustomerController extends ShopAdminController
{
    public function index(): void
    {
        $this->view('admin/shop/customers', [
            'title' => 'Kunden',
            'active' => 'shop-customers',
            'customers' => Customer::all(),
        ]);
    }

    public function show(string $id): void
    {
        $customer = Customer::find((int) $id) ?? $this->abort();
        $this->view('admin/shop/customer-detail', [
            'title' => 'Kunde: ' . ($customer['email'] ?? ''),
            'active' => 'shop-customers',
            'customer' => $customer,
            'orders' => ShopOrder::forCustomer((int) $customer['id'], (string) $customer['email']),
        ]);
    }

    /** Stamm­daten (Name/E-Mail) speichern. */
    public function update(string $id): void
    {
        $customer = Customer::find((int) $id) ?? $this->abort();
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Bitte eine gültige E-Mail-Adresse angeben.');
            redirect('/admin/shop/customers/' . $customer['id']);
        }
        $other = Customer::findByEmail($email);
        if ($other !== null && (int) $other['id'] !== (int) $customer['id']) {
            flash('error', 'Diese E-Mail-Adresse wird bereits von einem anderen Konto verwendet.');
            redirect('/admin/shop/customers/' . $customer['id']);
        }
        Customer::update((int) $customer['id'], $email, trim($_POST['first_name'] ?? ''), trim($_POST['last_name'] ?? ''));
        flash('success', 'Kundendaten gespeichert.');
        redirect('/admin/shop/customers/' . $customer['id']);
    }

    /** Neues Passwort für den Kunden setzen (durch den Admin). */
    public function setPassword(string $id): void
    {
        $customer = Customer::find((int) $id) ?? $this->abort();
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 6) {
            flash('error', 'Das Passwort muss mindestens 6 Zeichen haben.');
        } else {
            Customer::updatePassword((int) $customer['id'], $password);
            flash('success', 'Neues Passwort gesetzt.');
        }
        redirect('/admin/shop/customers/' . $customer['id']);
    }

    public function delete(string $id): void
    {
        $customer = Customer::find((int) $id) ?? $this->abort();
        Customer::delete((int) $customer['id']);
        flash('success', 'Kunde gelöscht. Seine Bestellungen bleiben erhalten.');
        redirect('/admin/shop/customers');
    }

    private function abort(): never
    {
        flash('error', 'Kunde nicht gefunden.');
        redirect('/admin/shop/customers');
    }
}
