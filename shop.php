<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

const PER_PAGE = 24;
$f = [
    'category' => isset(CATEGORIES[$_GET['category'] ?? '']) ? (string) $_GET['category'] : '',
    'make'     => mb_substr(trim((string) ($_GET['make'] ?? '')), 0, 60),
    'fuel'     => in_array($_GET['fuel'] ?? '', ['petrol', 'diesel'], true) ? (string) $_GET['fuel'] : '',
    'q'        => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80),
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$total = countProducts($f);
$pages = max(1, (int) ceil($total / PER_PAGE));
$page = min($page, $pages);
$items = array_map('productPublic', findProducts($f + ['offset' => ($page - 1) * PER_PAGE], PER_PAGE));
$makes = makesInStock();

$url = function (array $over = []) use ($f): string {
    $q = array_filter(array_merge($f, ['page' => ''], $over), fn($v) => $v !== '' && $v !== null);
    return APP_URL . '/shop.php' . ($q ? '?' . http_build_query($q) : '');
};
$heading = $f['category'] ? CATEGORIES[$f['category']] . 's' : ($f['make'] ?: 'All engines, gearboxes & parts');
$title = ($f['category'] || $f['make'] ? $heading . ' for sale' : 'Shop engines, gearboxes & car parts') . ' — ' . APP_NAME;

pageHead($title, 'Browse tested engines, gearboxes and car parts in stock. Request a quote and we confirm fitment before you pay.',
    ['canonical' => $url(), 'noindex' => $f['q'] !== '' || $page > 1]);
siteHeader('shop.php');
?>
<main id="main" class="page">
  <div class="page-head"><h1><?= e($heading) ?></h1><p><?= $total ?> in stock. Prices marked "Get a quote" are confirmed after we check fitment.</p></div>
  <div class="shop">
    <form class="filters glass" method="get" action="<?= e(APP_URL) ?>/shop.php" role="search">
      <h2>Filter</h2>
      <div class="field"><label for="s-q">Search</label><input id="s-q" name="q" value="<?= e($f['q']) ?>" placeholder="Hilux, 2.7, BFB…"></div>
      <div class="field"><label for="s-cat">Type</label><select id="s-cat" name="category"><option value="">Everything</option><?php foreach (CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= $k === $f['category'] ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="s-make">Make</label><select id="s-make" name="make"><option value="">All makes</option><?php foreach ($makes as $m => $n): $m = (string) $m; ?><option value="<?= e($m) ?>"<?= $m === $f['make'] ? ' selected' : '' ?>><?= e($m) ?> (<?= (int) $n ?>)</option><?php endforeach; ?></select></div>
      <div class="field"><label for="s-fuel">Fuel</label><select id="s-fuel" name="fuel"><option value="">Petrol &amp; diesel</option><option value="petrol"<?= $f['fuel'] === 'petrol' ? ' selected' : '' ?>>Petrol</option><option value="diesel"<?= $f['fuel'] === 'diesel' ? ' selected' : '' ?>>Diesel</option></select></div>
      <button class="btn btn-primary btn-block" type="submit">Apply</button>
      <a class="btn btn-ghost btn-block" href="<?= e(APP_URL) ?>/shop.php">Clear</a>
    </form>
    <div>
      <?php if (!$items): ?>
      <div class="empty glass"><h2 style="font-size:1.4rem;margin-bottom:8px">Nothing matches that yet</h2><p>We get new stock every week and can source units that are not listed. Tell us what you need and we will quote.</p><p style="margin-top:16px"><a class="btn btn-primary" href="<?= e(APP_URL) ?>/contact.php">Request this unit</a></p></div>
      <?php else: ?>
      <div class="grid"><?php foreach ($items as $i => $p) echo productCard($p, $i > 3); ?></div>
      <?php if ($pages > 1): ?><nav class="pager" aria-label="Pages"><?php for ($i = 1; $i <= $pages; $i++): if ($i !== 1 && $i !== $pages && abs($i - $page) > 2) { if (abs($i - $page) === 3) echo '<span class="chip" aria-hidden="true">…</span>'; continue; } ?><a class="chip<?= $i === $page ? ' on' : '' ?>" href="<?= e($url(['page' => $i > 1 ? $i : ''])) ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>><?= $i ?></a><?php endfor; ?></nav><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php
siteFooter();
pageFoot();
