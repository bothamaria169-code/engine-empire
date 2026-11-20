<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$raw = productBySlug(mb_substr((string) ($_GET['s'] ?? ''), 0, 160));
if (!$raw) {
    http_response_code(404);
    pageHead('Not found — ' . APP_NAME, 'This item is no longer listed.', ['noindex' => true]);
    siteHeader('shop.php');
    echo '<main id="main" class="page"><div class="prose glass"><h1>That item is no longer listed</h1><p>It may have been sold. Tell us what you need and we will find another one.</p><p><a class="btn btn-primary" href="' . e(APP_URL) . '/shop.php">Browse stock</a></p></div></main>';
    siteFooter();
    pageFoot();
    exit;
}
getDB()->prepare('UPDATE products SET views = views + 1 WHERE id = ?')->execute([$raw['id']]);
$p = productPublic($raw);
$sold = $raw['stock_status'] === 'sold';
$title = $p['title'] . ' for sale — ' . APP_NAME;
$desc = mb_substr(trim("{$p['categoryLabel']} for {$p['make']} {$p['model']}. " . ($p['description'] ?: 'Tested, with warranty. Delivered across South Africa.')), 0, 158);

$schema = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $p['title'], 'description' => $desc,
    'image' => array_column($p['images'], 'src') ?: [$p['thumb']], 'category' => $p['categoryLabel'], 'brand' => ['@type' => 'Brand', 'name' => $p['make']], 'url' => $p['url']];
if ($p['partNumber']) $schema['mpn'] = $p['partNumber'];
if ($p['hasPrice']) {
    $schema['offers'] = ['@type' => 'Offer', 'priceCurrency' => 'ZAR', 'price' => number_format((float) $raw['price'], 2, '.', ''), 'url' => $p['url'],
        'availability' => 'https://schema.org/' . ($sold ? 'SoldOut' : 'InStock')];
}
$others = array_values(array_filter(array_map('productPublic', findProducts(['make' => $raw['make']], 5)), fn($o) => $o['id'] !== $p['id']));
$others = array_slice($others, 0, 4);

$items = array_merge(
    array_map(fn($i) => ['t' => 'img', 'src' => $i['src'], 'th' => $i['thumb']], $p['images']),
    array_map(fn($v) => ['t' => 'vid', 'src' => $v['src'], 'th' => $v['poster']], $p['videos'])
);
$wa = whatsappLink("Hi, I need a quote for: {$p['title']} {$p['url']}");

pageHead($title, $desc, ['canonical' => $p['url'], 'image' => $p['images'][0]['src'] ?? $p['thumb'], 'schema' => [$schema], 'ogType' => 'product']);
siteHeader('shop.php');
?>
<main id="main" class="page">
  <p class="crumbs"><a href="<?= e(APP_URL) ?>/shop.php">Shop</a> › <a href="<?= e(APP_URL) ?>/shop.php?category=<?= e($p['category']) ?>"><?= e($p['categoryLabel']) ?></a> › <?= e($p['make']) ?></p>
  <article class="pd glass">
    <div>
      <?php if ($items): $f = $items[0]; ?>
      <div class="pd-gal" data-gal="<?= e(json_encode($items, JSON_UNESCAPED_SLASHES)) ?>" data-title="<?= e($p['title']) ?>">
        <div class="pd-main">
          <?php if ($f['t'] === 'img'): ?><img src="<?= e($f['src']) ?>" alt="<?= e($p['title']) ?>" width="1400" height="1050" fetchpriority="high">
          <?php else: ?><video src="<?= e($f['src']) ?>" poster="<?= e($f['th']) ?>" controls playsinline preload="metadata"></video><?php endif; ?>
        </div>
        <?php if (count($items) > 1): ?><div class="pd-thumbs">
          <?php foreach ($items as $i => $it): ?>
            <button class="<?= $i ? '' : 'on' ?>" data-gi="<?= $i ?>" aria-label="<?= $it['t'] === 'vid' ? 'Play video' : 'Photo' ?> <?= $i + 1 ?>"><img src="<?= e($it['th']) ?>" alt="" loading="lazy" width="72" height="56"><?= $it['t'] === 'vid' ? '<span class="play"><i class="fa-solid fa-play"></i></span>' : '' ?></button>
          <?php endforeach; ?>
        </div><?php endif; ?>
      </div>
      <?php else: ?><div class="pd-main"><img src="<?= e($p['thumb']) ?>" alt="<?= e($p['title']) ?>" width="560" height="420"></div><?php endif; ?>
    </div>
    <div class="pd-info">
      <h1><?= e($p['title']) ?></h1>
      <p class="veh"><i class="fa-solid fa-car-side"></i> <?= e($p['make'] . ' ' . $p['model']) ?><?= $p['spec'] ? ' <span class="yr">' . e($p['spec']) . '</span>' : '' ?></p>
      <strong class="price<?= $p['hasPrice'] ? '' : ' ask' ?>"><?= e($p['price']) ?></strong>
      <dl class="facts">
        <dt>Type</dt><dd><?= e($p['categoryLabel']) ?></dd>
        <?php if ($p['capacity']): ?><dt>Capacity</dt><dd><?= e($p['capacity']) ?> L</dd><?php endif; ?>
        <?php if ($p['fuel']): ?><dt>Fuel</dt><dd><?= e($p['fuel']) ?></dd><?php endif; ?>
        <?php if ($p['code']): ?><dt>Engine code</dt><dd><?= e($p['code']) ?></dd><?php endif; ?>
        <dt>Condition</dt><dd><?= e($p['grade']) ?></dd>
        <?php if ($p['warranty']): ?><dt>Warranty</dt><dd><?= e($p['warranty']) ?></dd><?php endif; ?>
        <?php if ($p['partNumber']): ?><dt>Part number</dt><dd><?= e($p['partNumber']) ?></dd><?php endif; ?>
        <dt>Stock</dt><dd><?= e($p['stock']) ?></dd>
      </dl>
      <?php if ($p['description']): ?><p><?= nl2br(e($p['description'])) ?></p><?php endif; ?>
      <p class="note"><i class="fa-solid fa-circle-info"></i> Engines differ between model years. Send us your vehicle and engine code and we confirm it fits before you pay.</p>
      <?php if ($sold): ?><p class="alert alert-err">This unit has been sold. Ask us, we may have another.</p><?php endif; ?>
      <div class="pd-cta">
        <a class="btn btn-primary" href="#enquire"><i class="fa-solid fa-file-invoice"></i> Get a quote</a>
        <?php if ($wa): ?><a class="btn btn-wa" href="<?= e($wa) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a><?php endif; ?>
      </div>
    </div>
  </article>

  <section class="glass" id="enquire" style="border-radius:var(--r-xl);margin:24px auto 0;max-width:760px"><?php enquiryForm($raw, 'Get a quote for this unit'); ?></section>

  <?php if ($others): ?>
  <div class="sec-head" style="margin-top:48px"><h2 style="font-size:1.6rem">More <?= e($p['make']) ?></h2></div>
  <div class="grid"><?php foreach ($others as $o) echo productCard($o); ?></div>
  <?php endif; ?>
</main>
<?php
siteFooter();
pageFoot();
