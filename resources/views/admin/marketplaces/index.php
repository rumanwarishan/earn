<h2 class="mb-3">Marketplaces &amp; categories</h2>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
  <div>
    <div class="glass-card table-wrap mb-3">
      <table class="data-table">
        <thead><tr><th>Name</th><th>Active</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($marketplaces as $m): ?>
            <tr>
              <td><?= e($m['name']) ?></td>
              <td><span class="badge <?= $m['is_active'] ? 'badge-emerald' : 'badge-muted' ?>"><?= $m['is_active'] ? 'Active' : 'Inactive' ?></span></td>
              <td><form method="POST" action="/admin/marketplaces/<?= (int) $m['id'] ?>/toggle"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit"><?= $m['is_active'] ? 'Disable' : 'Enable' ?></button></form></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <form method="POST" action="/admin/marketplaces" class="glass-card" style="padding:18px;">
      <?= csrf_field() ?>
      <h3 class="mb-3" style="font-size:14px;">Add marketplace</h3>
      <div class="field"><label>Name</label><input class="input" type="text" name="name" required></div>
      <div class="field"><label>Website URL</label><input class="input" type="url" name="website_url"></div>
      <button class="btn btn-primary btn-sm" type="submit">Add</button>
    </form>
  </div>

  <div>
    <div class="glass-card table-wrap mb-3">
      <table class="data-table">
        <thead><tr><th>Category</th></tr></thead>
        <tbody>
          <?php foreach ($categories as $c): ?><tr><td><?= e($c['name']) ?></td></tr><?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <form method="POST" action="/admin/marketplaces/categories" class="glass-card" style="padding:18px;">
      <?= csrf_field() ?>
      <h3 class="mb-3" style="font-size:14px;">Add category</h3>
      <div class="field"><label>Name</label><input class="input" type="text" name="name" required></div>
      <button class="btn btn-primary btn-sm" type="submit">Add</button>
    </form>
  </div>
</div>
