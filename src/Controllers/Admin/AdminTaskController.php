<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuditLogger;
use App\Support\ValidationException;

final class AdminTaskController
{
    public function index(Request $request): void
    {
        require_admin_permission('tasks.view');
        $pdo = Database::connection();
        $tasks = $pdo->query('SELECT t.*,
                (SELECT COUNT(*) FROM task_completions tc WHERE tc.task_id = t.id) AS completion_count
            FROM tasks t ORDER BY t.sort_order, t.id')->fetchAll();

        echo view('layouts.admin', [
            'pageTitle' => 'Tasks',
            'content' => view('admin.tasks.index', ['tasks' => $tasks]),
        ]);
    }

    public function create(Request $request): void
    {
        require_admin_permission('tasks.create');
        echo view('layouts.admin', [
            'pageTitle' => 'New task',
            'content' => view('admin.tasks.form', ['task' => null]),
        ]);
    }

    public function edit(Request $request): void
    {
        require_admin_permission('tasks.update');
        $id = (int) $request->param('id');
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        if (!$task) {
            abort(404, 'Task not found');
        }

        echo view('layouts.admin', [
            'pageTitle' => 'Edit task',
            'content' => view('admin.tasks.form', ['task' => $task]),
        ]);
    }

    public function store(Request $request): void
    {
        require_admin_permission('tasks.create');
        $this->save($request, null);
    }

    public function update(Request $request): void
    {
        require_admin_permission('tasks.update');
        $this->save($request, (int) $request->param('id'));
    }

    private function save(Request $request, ?int $id): void
    {
        require_admin_permission($id ? 'tasks.update' : 'tasks.create');
        $admin = Session::get('admin');
        $pdo = Database::connection();

        $data = $request->only(['title', 'description', 'type', 'reward_amount', 'sort_order', 'is_active']);

        try {
            if (trim((string) $data['title']) === '') {
                throw new ValidationException(['title' => 'Task title is required.']);
            }
            if (!in_array($data['type'], ['welcome', 'daily', 'one_time'], true)) {
                throw new ValidationException(['type' => 'Choose a valid task type.']);
            }
            if (!preg_match('/^\d+(\.\d{1,2})?$/', (string) $data['reward_amount'])) {
                throw new ValidationException(['reward_amount' => 'Enter a valid reward amount.']);
            }

            $isActive = isset($data['is_active']) ? 1 : 0;
            $sortOrder = (int) ($data['sort_order'] ?: 0);
            $description = trim((string) ($data['description'] ?? '')) ?: null;

            if ($id === null) {
                $stmt = $pdo->prepare('INSERT INTO tasks (title, description, type, reward_amount, is_active, sort_order)
                    VALUES (?,?,?,?,?,?)');
                $stmt->execute([$data['title'], $description, $data['type'], $data['reward_amount'], $isActive, $sortOrder]);
                $newId = (int) $pdo->lastInsertId();
                AuditLogger::log('admin', $admin['id'], 'task.created', 'task', $newId, null, $data, null, $request->ip());
                flash_success('Task created.');
                redirect('/admin/tasks');
                return;
            }

            $pdo->prepare('UPDATE tasks SET title=?, description=?, type=?, reward_amount=?, is_active=?, sort_order=? WHERE id=?')
                ->execute([$data['title'], $description, $data['type'], $data['reward_amount'], $isActive, $sortOrder, $id]);
            AuditLogger::log('admin', $admin['id'], 'task.updated', 'task', $id, null, $data, null, $request->ip());
            flash_success('Task updated.');
            redirect('/admin/tasks');
        } catch (ValidationException $e) {
            flash_errors($e->errors());
            flash_old($request->all());
            redirect($id ? '/admin/tasks/' . $id . '/edit' : '/admin/tasks/new');
        }
    }

    public function toggleActive(Request $request): void
    {
        require_admin_permission('tasks.update');
        $id = (int) $request->param('id');
        $pdo = Database::connection();
        $pdo->prepare('UPDATE tasks SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        flash_success('Task updated.');
        redirect('/admin/tasks');
    }

    public function destroy(Request $request): void
    {
        require_admin_permission('tasks.delete');
        $id = (int) $request->param('id');
        $admin = Session::get('admin');
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM tasks WHERE id = ?')->execute([$id]);
        AuditLogger::log('admin', $admin['id'], 'task.deleted', 'task', $id, null, null, null, $request->ip());
        flash_success('Task deleted.');
        redirect('/admin/tasks');
    }
}
