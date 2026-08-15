<a href="/admin/products/import" class="text-muted" style="font-size:13px;">&larr; Back</a>
<h2 class="mt-3 mb-3">Import preview</h2>

<div class="stat-row">
  <div class="glass-panel stat-tile"><div class="label">Valid rows</div><div class="value" style="color:var(--emerald);"><?= (int) $validCount ?></div></div>
  <div class="glass-panel stat-tile"><div class="label">Failed rows</div><div class="value" style="color:var(--danger);"><?= (int) $invalidCount ?></div></div>
</div>

<?php if ($validCount > 0): ?>
<form method="POST" action="/admin/products/import/confirm" class="mb-3">
  <?= csrf_field() ?>
  <input type="hidden" name="token" value="<?= e($token) ?>">
  <button class="btn btn-primary" type="submit">Confirm import of <?= (int) $validCount ?> product(s)</button>
</form>
<?php else: ?>
<div class="alert alert-error">No valid rows to import. Fix the errors below and re-upload.</div>
<?php endif; ?>

<div class="glass-card table-wrap">
  <table class="data-table">
    <thead><tr><th>#</th><th>Name</th><th>Status</th><th>Errors</th></tr></thead>
    <tbody>
      <?php foreach ($results as $i => $r): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= e($r['data']['name'] ?? '') ?></td>
          <td><?= $r['valid'] ? '<span class="badge badge-emerald">OK</span>' : '<span class="badge badge-danger">Failed</span>' ?></td>
          <td class="text-muted"><?= e(implode('; ', $r['errors'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
