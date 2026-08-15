<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuditLogger;
use App\Services\SettingsService;

final class AdminSettingsController
{
    private const EDITABLE_KEYS = [
        'site_name' => 'string', 'support_email' => 'string', 'currency' => 'string', 'timezone' => 'string',
        'btc_deposit_address' => 'string', 'btc_deposit_network_note' => 'string',
        'min_deposit_usd' => 'decimal', 'min_withdrawal_usd' => 'decimal', 'max_withdrawal_usd' => 'decimal',
        'welcome_bonus_amount' => 'decimal', 'welcome_bonus_enabled' => 'bool',
        'registration_enabled' => 'bool', 'maintenance_mode' => 'bool', 'chatbot_enabled' => 'bool',
    ];

    public function index(Request $request): void
    {
        require_admin_permission('settings.view');
        $pdo = Database::connection();
        $referralLevels = $pdo->query('SELECT * FROM referral_settings ORDER BY level')->fetchAll();

        echo view('layouts.admin', [
            'pageTitle' => 'Settings',
            'content' => view('admin.settings.index', [
                'settings' => SettingsService::all(),
                'referralLevels' => $referralLevels,
            ]),
        ]);
    }

    public function update(Request $request): void
    {
        require_admin_permission('settings.update');
        $admin = Session::get('admin');

        foreach (self::EDITABLE_KEYS as $key => $type) {
            if ($type === 'bool') {
                SettingsService::set($key, $request->input($key) ? '1' : '0', 'bool');
                continue;
            }
            if ($request->input($key) !== null) {
                SettingsService::set($key, (string) $request->input($key), $type);
            }
        }

        AuditLogger::log('admin', $admin['id'], 'settings.updated', 'site_settings', null, null, $request->only(array_keys(self::EDITABLE_KEYS)), null, $request->ip());
        flash_success('Settings updated.');
        redirect('/admin/settings');
    }

    public function updateReferralLevel(Request $request): void
    {
        require_admin_permission('settings.update');
        $id = (int) $request->param('id');
        $admin = Session::get('admin');
        $pdo = Database::connection();

        $pdo->prepare('UPDATE referral_settings SET bonus_type = ?, bonus_amount = ?, min_qualifying_deposit = ?, is_active = ? WHERE id = ?')
            ->execute([
                (string) $request->input('bonus_type', 'fixed'),
                (string) $request->input('bonus_amount', '0'),
                (string) $request->input('min_qualifying_deposit', '0'),
                $request->input('is_active') ? 1 : 0,
                $id,
            ]);

        AuditLogger::log('admin', $admin['id'], 'referral_settings.updated', 'referral_settings', $id, null, $request->all(), null, $request->ip());
        flash_success('Referral level updated.');
        redirect('/admin/settings');
    }

    public function addReferralLevel(Request $request): void
    {
        require_admin_permission('settings.update');
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT COALESCE(MAX(level),0) + 1 FROM referral_settings');
        $nextLevel = (int) $stmt->fetchColumn();
        $pdo->prepare('INSERT INTO referral_settings (level, bonus_type, bonus_amount, min_qualifying_deposit, is_active) VALUES (?,"fixed",0,50,0)')
            ->execute([$nextLevel]);
        flash_success("Referral level {$nextLevel} added (inactive by default - configure and enable it).");
        redirect('/admin/settings');
    }
}
