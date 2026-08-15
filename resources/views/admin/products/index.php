<div class="admin-toolbar">
  <form method="GET" action="/admin/products" class="flex gap-2">
    <input class="input" type="text" name="q" value="<?= e($q) ?>" placeholder="Search products...">
    <button class="btn btn-secondary btn-sm" type="submit">Search</button>
  </form>
  <a class="btn btn-primary btn-sm" href="/admin/products/new">+ New product</a>
  <a class="btn btn-secondary btn-sm" href="/admin/products/import">Import CSV</a>
  <a class="btn btn-secondary btn-sm" href="/admin/products/export">Export CSV</a>
</div>

<div class="glass-card table-wrap">
  <table class="data-table">
    <thead><tr><th>Product</th><th>Marketplace</th><th>Price</th><th>Cashback</th><th>Featured</th><th>Published</th><th></th></tr></thead>
    <tbody>
      <?php if (!$products): ?><tr><td colspan="7" class="text-muted">No products yet.</td></tr><?php endif; ?>
      <?php foreach ($products as $p): ?>
        <tr>
          <td class="cell-truncate" title="<?= e($p['name']) ?>"><?= e($p['name']) ?></td>
          <td><?= e($p['marketplace_name']) ?></td>
          <td><?= money($p['display_price']) ?></td>
          <td><?= $p['cashback_type'] === 'percentage' ? rtrim(rtrim((string) $p['cashback_value'], '0'), '.') . '%' : money($p['cashback_value']) ?></td>
          <td><?= $p['is_featured'] ? '⭐' : '—' ?></td>
          <td><span class="badge <?= $p['is_published'] ? 'badge-emerald' : 'badge-muted' ?>"><?= $p['is_published'] ? 'Published' : 'Draft' ?></span></td>
          <td class="flex gap-2">
            <a class="btn btn-sm btn-secondary" href="/admin/products/<?= (int) $p['id'] ?>/edit">Edit</a>
            <form method="POST" action="/admin/products/<?= (int) $p['id'] ?>/duplicate"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit">Duplicate</button></form>
            <form method="POST" action="/admin/products/<?= (int) $p['id'] ?>/archive"><?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('Archive this product?')">Archive</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
