<div class="admin-toolbar">
  <h2>Tasks</h2>
  <a class="btn btn-primary btn-sm" href="/admin/tasks/new">+ New task</a>
</div>

<div class="glass-card table-wrap">
  <table class="data-table">
    <thead><tr><th>Title</th><th>Type</th><th>Reward</th><th>Completions</th><th>Active</th><th></th></tr></thead>
    <tbody>
      <?php if (!$tasks): ?><tr><td colspan="6" class="text-muted">No tasks yet. Create a welcome bonus or daily check-in task.</td></tr><?php endif; ?>
      <?php foreach ($tasks as $t): ?>
        <tr>
          <td>
            <?= e($t['title']) ?>
            <?php if ($t['description']): ?><div class="text-muted" style="font-size:11.5px;"><?= e($t['description']) ?></div><?php endif; ?>
          </td>
          <td><span class="badge badge-muted"><?= e(ucfirst(str_replace('_', ' ', $t['type']))) ?></span></td>
          <td><?= money($t['reward_amount']) ?></td>
          <td><?= (int) $t['completion_count'] ?></td>
          <td><span class="badge <?= $t['is_active'] ? 'badge-emerald' : 'badge-muted' ?>"><?= $t['is_active'] ? 'Active' : 'Disabled' ?></span></td>
          <td class="flex gap-2">
            <a class="btn btn-sm btn-secondary" href="/admin/tasks/<?= (int) $t['id'] ?>/edit">Edit</a>
            <form method="POST" action="/admin/tasks/<?= (int) $t['id'] ?>/toggle"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit"><?= $t['is_active'] ? 'Disable' : 'Enable' ?></button></form>
            <form method="POST" action="/admin/tasks/<?= (int) $t['id'] ?>/delete"><?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('Delete this task? Users will no longer be able to claim it.')">Delete</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
