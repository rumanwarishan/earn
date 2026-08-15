<a href="/admin/products" class="text-muted" style="font-size:13px;">&larr; Back to products</a>
<h2 class="mt-3 mb-3">Import products from CSV</h2>

<div class="glass-panel mb-3" style="padding:16px;font-size:13px;color:var(--text-mid);">
  <div style="font-weight:600;color:var(--text-hi);margin-bottom:6px;">Expected columns</div>
  <code class="mono" style="font-size:12px;display:block;word-break:break-all;">name, marketplace, category, external_url, affiliate_url, original_price, display_price, cashback_type, cashback_value, short_description, is_featured, is_published</code>
  <div class="mt-2">marketplace/category must match an existing slug (e.g. "amazon", "electronics"). cashback_type is "percentage" or "fixed".</div>
</div>

<form method="POST" action="/admin/products/import/preview" enctype="multipart/form-data" class="glass-card" style="padding:20px;max-width:480px;">
  <?= csrf_field() ?>
  <div class="field"><label>CSV file</label><input class="input" type="file" name="csv_file" accept=".csv" required></div>
  <button class="btn btn-primary" type="submit">Preview import</button>
</form>
