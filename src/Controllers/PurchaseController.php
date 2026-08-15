<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Services\PurchaseService;
use App\Support\ValidationException;

final class PurchaseController
{
    public function buy(Request $request): void
    {
        $user = Session::get('user');
        $productId = (int) $request->param('id');

        $shipping = [
            'name' => (string) $request->input('shipping_name', ''),
            'phone' => (string) $request->input('shipping_phone', ''),
            'address_line1' => (string) $request->input('shipping_address_line1', ''),
            'address_line2' => (string) $request->input('shipping_address_line2', ''),
            'city' => (string) $request->input('shipping_city', ''),
            'state' => (string) $request->input('shipping_state', ''),
            'postal_code' => (string) $request->input('shipping_postal_code', ''),
            'country' => (string) $request->input('shipping_country', ''),
        ];

        try {
            $orderId = PurchaseService::buyWithWallet((int) $user['id'], $productId, $shipping, $request->ip());
            flash_success('Purchase confirmed! Your order will ship soon - track it on the Tasks page.');
            redirect('/tasks');
        } catch (ValidationException $e) {
            flash_errors($e->errors());
            flash_old($request->all());
            redirect($request->input('return_to', '/shop'));
        } catch (\Throwable $e) {
            flash_errors(['purchase' => $e->getMessage()]);
            redirect($request->input('return_to', '/shop'));
        }
    }
}
