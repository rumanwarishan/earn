<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;

final class AdminAuditController
{
    public function index(Request $request): void
    {
        require_admin_permission('audit.view');
        $pdo = Database::connection();

        $action = trim((string) $request->query('action', ''));
        $where = '';
        $params = [];
        if ($action !== '') {
            $where = 'WHERE al.action LIKE ?';
            $params[] = "%{$action}%";
        }

        $stmt = $pdo->prepare("SELECT * FROM audit_logs al {$where} ORDER BY al.created_at DESC LIMIT 200");
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        echo view('layouts.admin', [
            'pageTitle' => 'Audit Logs',
            'content' => view('admin.audit.index', ['logs' => $logs, 'action' => $action]),
        ]);
    }
}
