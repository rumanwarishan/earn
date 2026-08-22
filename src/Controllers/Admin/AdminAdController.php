<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuditLogger;
use App\Services\FileUploadService;
use App\Support\ValidationException;

final class AdminAdController
{
    public function index(Request $request): void
    {
        require_admin_permission('ads.view');
        $pdo = Database::connection();

        $ads = $pdo->query('SELECT a.*,
                (SELECT COUNT(*) FROM ad_completions ac WHERE ac.advertisement_id = a.id) AS completion_count,
                (SELECT COALESCE(SUM(reward_amount),0) FROM ad_completions ac WHERE ac.advertisement_id = a.id) AS total_paid
            FROM advertisements a ORDER BY a.sort_order ASC, a.id DESC')->fetchAll();

        $stats = [
            'total_ads' => count($ads),
            'active_ads' => count(array_filter($ads, fn ($a) => (bool) $a['is_active'])),
            'total_completions' => (int) $pdo->query('SELECT COUNT(*) FROM ad_completions')->fetchColumn(),
            'total_paid' => (string) $pdo->query('SELECT COALESCE(SUM(reward_amount),0) FROM ad_completions')->fetchColumn(),
        ];

        echo view('layouts.admin', [
            'pageTitle' => 'Watch & Earn',
            'content' => view('admin.ads.index', ['ads' => $ads, 'stats' => $stats]),
        ]);
    }

    public function create(Request $request): void
    {
        require_admin_permission('ads.create');
        echo view('layouts.admin', [
            'pageTitle' => 'New advertisement',
            'content' => view('admin.ads.form', ['ad' => null]),
        ]);
    }

    public function edit(Request $request): void
    {
        require_admin_permission('ads.update');
        $id = (int) $request->param('id');
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM advertisements WHERE id = ?');
        $stmt->execute([$id]);
        $ad = $stmt->fetch();
        if (!$ad) {
            abort(404, 'Advertisement not found');
        }

        echo view('layouts.admin', [
            'pageTitle' => 'Edit advertisement',
            'content' => view('admin.ads.form', ['ad' => $ad]),
        ]);
    }

    public function store(Request $request): void
    {
        require_admin_permission('ads.create');
        $this->save($request, null);
    }

    public function update(Request $request): void
    {
        require_admin_permission('ads.update');
        $this->save($request, (int) $request->param('id'));
    }

    private function save(Request $request, ?int $id): void
    {
        $admin = Session::get('admin');
        $pdo = Database::connection();

        $data = $request->only([
            'title', 'description', 'type', 'destination_url', 'reward_amount',
            'watch_seconds', 'max_completions', 'starts_at', 'ends_at', 'is_active', 'sort_order',
        ]);

        try {
            if (trim((string) $data['title']) === '') {
                throw new ValidationException(['title' => 'Advertisement title is required.']);
            }
            $type = in_array($data['type'], ['image', 'external', 'video'], true) ? $data['type'] : 'image';
            $destinationUrl = trim((string) ($data['destination_url'] ?? '')) ?: null;

            if ($type === 'external') {
                if (!filter_var($destinationUrl, FILTER_VALIDATE_URL)) {
                    throw new ValidationException(['destination_url' => 'A valid destination URL is required for external ads.']);
                }
            } elseif ($type === 'video') {
                $videoId = youtube_video_id((string) $destinationUrl);
                if ($videoId === null) {
                    throw new ValidationException(['destination_url' => 'Enter a valid YouTube video URL (youtube.com/watch?v=..., youtu.be/..., etc.) or the 11-character video ID.']);
                }
                $destinationUrl = $videoId;
            }
            if (!preg_match('/^\d+(\.\d{1,2})?$/', (string) $data['reward_amount'])) {
                throw new ValidationException(['reward_amount' => 'Enter a valid reward amount.']);
            }
            if (!preg_match('/^\d+$/', (string) $data['watch_seconds']) || (int) $data['watch_seconds'] < 1) {
                throw new ValidationException(['watch_seconds' => 'Enter a required watch time of at least 1 second.']);
            }

            $maxCompletions = null;
            if ($data['max_completions'] !== null && trim((string) $data['max_completions']) !== '') {
                if (!preg_match('/^\d+$/', (string) $data['max_completions'])) {
                    throw new ValidationException(['max_completions' => 'Enter a valid whole number.']);
                }
                $maxCompletions = (int) $data['max_completions'];
            }

            $startsAt = trim((string) ($data['starts_at'] ?? '')) !== '' ? date('Y-m-d H:i:s', strtotime((string) $data['starts_at'])) : null;
            $endsAt = trim((string) ($data['ends_at'] ?? '')) !== '' ? date('Y-m-d H:i:s', strtotime((string) $data['ends_at'])) : null;

            $imagePath = null;
            $file = $request->file('image');
            if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $imagePath = FileUploadService::storeAdImage($file);
            }

            $isActive = isset($data['is_active']) ? 1 : 0;
            $sortOrder = (int) ($data['sort_order'] ?: 0);
            $description = trim((string) ($data['description'] ?? '')) ?: null;

            if ($id === null) {
                $stmt = $pdo->prepare('INSERT INTO advertisements
                    (title, description, type, image_path, destination_url, reward_amount, watch_seconds,
                     max_completions, starts_at, ends_at, is_active, sort_order)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([
                    $data['title'], $description, $type, $imagePath, $destinationUrl, $data['reward_amount'],
                    $data['watch_seconds'], $maxCompletions, $startsAt, $endsAt, $isActive, $sortOrder,
                ]);
                $newId = (int) $pdo->lastInsertId();
                AuditLogger::log('admin', $admin['id'], 'ad.created', 'advertisement', $newId, null, $data, null, $request->ip());
                flash_success('Advertisement created.');
                redirect('/admin/ads');
                return;
            }

            $sql = 'UPDATE advertisements SET title=?, description=?, type=?, destination_url=?, reward_amount=?,
                watch_seconds=?, max_completions=?, starts_at=?, ends_at=?, is_active=?, sort_order=?';
            $params = [
                $data['title'], $description, $type, $destinationUrl, $data['reward_amount'],
                $data['watch_seconds'], $maxCompletions, $startsAt, $endsAt, $isActive, $sortOrder,
            ];
            if ($imagePath) {
                $sql .= ', image_path = ?';
                $params[] = $imagePath;
            }
            $sql .= ' WHERE id = ?';
            $params[] = $id;

            $pdo->prepare($sql)->execute($params);
            AuditLogger::log('admin', $admin['id'], 'ad.updated', 'advertisement', $id, null, $data, null, $request->ip());
            flash_success('Advertisement updated.');
            redirect('/admin/ads');
        } catch (ValidationException $e) {
            flash_errors($e->errors());
            flash_old($request->all());
            redirect($id ? '/admin/ads/' . $id . '/edit' : '/admin/ads/new');
        }
    }

    public function toggleActive(Request $request): void
    {
        require_admin_permission('ads.update');
        $id = (int) $request->param('id');
        $pdo = Database::connection();
        $pdo->prepare('UPDATE advertisements SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        flash_success('Advertisement updated.');
        redirect('/admin/ads');
    }

    public function destroy(Request $request): void
    {
        require_admin_permission('ads.delete');
        $id = (int) $request->param('id');
        $admin = Session::get('admin');
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM ad_completions WHERE advertisement_id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            flash_errors(['ad' => 'This advertisement already has reward history and cannot be deleted. Deactivate it instead to stop new views.']);
            redirect('/admin/ads');
            return;
        }

        $pdo->prepare('DELETE FROM advertisements WHERE id = ?')->execute([$id]);
        AuditLogger::log('admin', $admin['id'], 'ad.deleted', 'advertisement', $id, null, null, null, $request->ip());
        flash_success('Advertisement deleted.');
        redirect('/admin/ads');
    }
}
