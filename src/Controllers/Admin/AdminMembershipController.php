<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuditLogger;

final class AdminMembershipController
{
    public function index(Request $request): void
    {
        require_admin_permission('settings.view');
        $pdo = Database::connection();
        $levels = $pdo->query('SELECT * FROM membership_levels ORDER BY sort_order')->fetchAll();

        echo view('layouts.admin', ['pageTitle' => 'Membership levels', 'content' => view('admin.membership.index', ['levels' => $levels])]);
    }

    public function update(Request $request): void
    {
        require_admin_permission('settings.update');
        $id = (int) $request->param('id');
        $admin = Session::get('admin');
        $pdo = Database::connection();

        $pdo->prepare('UPDATE membership_levels SET name = ?, min_total_deposited = ?, min_total_purchased = ?,
            cashback_multiplier = ?, referral_bonus_multiplier = ?, is_active = ? WHERE id = ?')
            ->execute([
                (string) $request->input('name'),
                (string) $request->input('min_total_deposited', '0'),
                (string) $request->input('min_total_purchased', '0'),
                (string) $request->input('cashback_multiplier', '1'),
                (string) $request->input('referral_bonus_multiplier', '1'),
                $request->input('is_active') ? 1 : 0,
                $id,
            ]);

        AuditLogger::log('admin', $admin['id'], 'membership_level.updated', 'membership_level', $id, null, $request->only(['name', 'min_total_deposited']), null, $request->ip());
        flash_success('Membership level updated.');
        redirect('/admin/membership-levels');
    }
}
