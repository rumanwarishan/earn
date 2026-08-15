<a href="/admin/tasks" class="text-muted" style="font-size:13px;">&larr; Back to tasks</a>
<h2 class="mt-3 mb-3"><?= $task ? 'Edit task' : 'New task' ?></h2>

<form method="POST" action="<?= $task ? '/admin/tasks/' . (int) $task['id'] : '/admin/tasks' ?>" class="glass-card" style="padding:24px;max-width:560px;">
  <?= csrf_field() ?>
  <div class="field"><label>Title</label><input class="input" type="text" name="title" required maxlength="150" value="<?= e($task['title'] ?? old('title')) ?>"></div>
  <div class="field"><label>Description (optional)</label><textarea class="input" name="description" rows="2" maxlength="500"><?= e($task['description'] ?? old('description')) ?></textarea></div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
    <div class="field">
      <label>Type</label>
      <select class="input" name="type">
        <option value="welcome" <?= ($task['type'] ?? '') === 'welcome' ? 'selected' : '' ?>>Welcome (once, new users)</option>
        <option value="daily" <?= ($task['type'] ?? 'daily') === 'daily' ? 'selected' : '' ?>>Daily (once per day)</option>
        <option value="one_time" <?= ($task['type'] ?? '') === 'one_time' ? 'selected' : '' ?>>One-time (once per user)</option>
      </select>
    </div>
    <div class="field"><label>Reward amount (USD)</label><input class="input" type="text" name="reward_amount" required value="<?= e((string) ($task['reward_amount'] ?? (old('reward_amount') ?: '0.00'))) ?>"></div>
  </div>

  <div class="field"><label>Sort order</label><input class="input" type="text" inputmode="numeric" pattern="\d*" name="sort_order" value="<?= e((string) ($task['sort_order'] ?? '0')) ?>"></div>

  <label class="flex items-center gap-2 mb-3"><input type="checkbox" name="is_active" <?= ($task === null || !empty($task['is_active'])) ? 'checked' : '' ?>> Active</label>

  <button class="btn btn-primary" type="submit"><?= $task ? 'Save changes' : 'Create task' ?></button>
</form>
