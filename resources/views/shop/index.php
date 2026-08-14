<h2 class="mb-3">Shop &amp; earn cashback</h2>

<form method="GET" action="/shop" class="mb-3">
  <input class="input mb-3" type="text" name="q" value="<?= e($q) ?>" placeholder="Search products...">
  <input type="hidden" name="marketplace" value="<?= e($marketplace) ?>">
  <input type="hidden" name="category" value="<?= e($category) ?>">
</form>

<div class="tabs mb-3">
  <a class="tab <?= $marketplace === '' ? 'active' : '' ?>" href="/shop?q=<?= urlencode($q) ?>">All</a>
  <?php foreach ($marketplaces as $mk): ?>
    <a class="tab <?= $marketplace === $mk['slug'] ? 'active' : '' ?>" href="/shop?marketplace=<?= e($mk['slug']) ?>&q=<?= urlencode($q) ?>"><?= e($mk['name']) ?></a>
  <?php endforeach; ?>
</div>

<div class="tabs mb-3">
  <?php foreach (['newest'=>'Newest','price_low'=>'Price: Low to High','price_high'=>'Price: High to Low','cashback'=>'Highest Cashback'] as $key => $label): ?>
    <a class="tab <?= $sort === $key ? 'active' : '' ?>" href="/shop?sort=<?= $key ?>&q=<?= urlencode($q) ?>&marketplace=<?= e($marketplace) ?>&category=<?= e($category) ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$products): ?>
  <div class="empty-state"><div class="icon">🛍️</div>No products found. Try a different search or filter.</div>
<?php else: ?>
<div class="product-grid mb-3">
  <?php foreach ($products as $p): ?>
    <?= view('partials.product-card', ['product' => $p]) ?>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($totalPages > 1): ?>
<div class="flex justify-between mt-3">
  <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="/shop?page=<?= $page - 1 ?>&q=<?= urlencode($q) ?>&marketplace=<?= e($marketplace) ?>&sort=<?= $sort ?>">Previous</a><?php else: ?><span></span><?php endif; ?>
  <span class="text-muted" style="font-size:12.5px;align-self:center;">Page <?= $page ?> of <?= $totalPages ?></span>
  <?php if ($page < $totalPages): ?><a class="btn btn-ghost btn-sm" href="/shop?page=<?= $page + 1 ?>&q=<?= urlencode($q) ?>&marketplace=<?= e($marketplace) ?>&sort=<?= $sort ?>">Next</a><?php else: ?><span></span><?php endif; ?>
</div>
<?php endif; ?>
