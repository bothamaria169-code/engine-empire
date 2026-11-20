<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$counts = categoryCounts();
$makes = makesInStock();
$featured = array_map('productPublic', findProducts([], 8));
$stockTotal = array_sum($counts);
$phone = get_setting('business_phone', get_setting('whatsapp_number', ''));
$wa = whatsappLink('Hi, I need a quote for an engine / gearbox.');

$faq = [
    ['Do your engines and gearboxes come with a warranty?', 'Yes. Every unit is tested before it is sold and the warranty period is printed on your quote and invoice. Warranty covers the unit itself; fitting must be done by a qualified workshop.'],
    ['How do I know the engine fits my vehicle?', 'Send us your vehicle make, model, year and engine code (or a photo of the engine plate). We confirm fitment before you pay.'],
    ['Do you deliver outside Gauteng?', 'Yes. We courier to every province and quote the delivery cost together with the unit price.'],
    ['How long is a quote valid?', 'Normally 7 days. The date is printed on the quote. Stock moves quickly, so accept and pay the deposit to reserve the unit.'],
    ['How can I pay?', 'EFT to our business account. The banking details are on every invoice. We start your order as soon as the payment reflects.'],
];

$faqSchema = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faq)];
$orgSchema = ['@context' => 'https://schema.org', '@type' => 'AutoPartsStore', 'name' => APP_NAME, 'url' => APP_URL . '/', 'image' => APP_URL . '/assets/img/og.png',
    'areaServed' => 'South Africa', 'priceRange' => 'RR', 'telephone' => $phone ?: null, 'email' => get_setting('contact_email', CONTACT_EMAIL)];

pageHead(APP_NAME . ' — Engines, Gearboxes & Car Parts | South Africa',
    'Buy tested petrol and diesel engines, gearboxes and car parts with warranty. Fast quotes and delivery across South Africa.',
    ['schema' => [$orgSchema, $faqSchema]]);
siteHeader('index.php');
?>
<main id="main">
<section class="hero">
  <div class="wrap hero-in">
    <div>
      <span class="eyebrow">Engines · Gearboxes · Car parts</span>
      <h1>The engine your vehicle needs, <em>quoted fast.</em></h1>
      <p class="hero-lead">Tested petrol and diesel engines, gearboxes and spares for every major brand, with warranty and delivery across South Africa. Tell us what you drive and get a written quote today.</p>
      <div class="hero-cta">
        <a class="btn btn-primary btn-lg" href="<?= e(APP_URL) ?>/shop.php"><i class="fa-solid fa-gears"></i> Shop engines</a>
        <a class="btn btn-ghost btn-lg" href="<?= e(APP_URL) ?>/contact.php"><i class="fa-solid fa-file-invoice"></i> Get a quote</a>
      </div>
      <p class="hero-points"><span><i class="fa-solid fa-circle-check"></i>Tested &amp; guaranteed</span><span><i class="fa-solid fa-circle-check"></i>Written quotes</span><span><i class="fa-solid fa-circle-check"></i>Nationwide delivery</span></p>
    </div>
    <form class="finder glass" action="<?= e(APP_URL) ?>/shop.php" method="get" role="search">
      <h2>Find your engine</h2>
      <p>Search by vehicle, engine size or engine code.</p>
      <div class="field"><label for="f-q">Vehicle or engine code</label><input id="f-q" name="q" placeholder="e.g. Hilux 2.7, BFB, 1KD"></div>
      <div class="row2">
        <div class="field"><label for="f-make">Make</label><select id="f-make" name="make"><option value="">All makes</option><?php foreach ($makes as $m => $n): $m = (string) $m; ?><option value="<?= e($m) ?>"><?= e($m) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="f-cat">Type</label><select id="f-cat" name="category"><option value="">Everything</option><?php foreach (CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
      </div>
      <button class="btn btn-primary btn-block" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search stock</button>
    </form>
  </div>
</section>

<section class="strip"><div class="wrap strip-in">
  <div><b><?= $stockTotal ?>+</b><span>units in stock</span></div>
  <div><b><?= count($makes) ?></b><span>makes covered</span></div>
  <div><b>Tested</b><span>and guaranteed</span></div>
  <div><b>9 provinces</b><span>delivered to</span></div>
</div></section>

<section class="sec"><div class="wrap">
  <div class="sec-head"><span class="eyebrow">Shop by type</span><h2>Everything your vehicle needs to keep moving</h2></div>
  <div class="cats">
    <?php foreach (CATEGORIES as $k => $l): ?>
    <a class="cat glass" href="<?= e(APP_URL) ?>/shop.php?category=<?= e($k) ?>"><i class="fa-solid <?= e(CATEGORY_ICONS[$k] ?? 'fa-gear') ?>"></i><b><?= e($l) ?></b><span><?= (int) ($counts[$k] ?? 0) ?> in stock</span></a>
    <?php endforeach; ?>
  </div>
</div></section>

<?php if ($featured): ?>
<section class="sec sec-alt"><div class="wrap">
  <div class="sec-head"><span class="eyebrow">Featured stock</span><h2>Recently added</h2><p>Photos and prices are on each page. Not sure it fits? Ask for a quote and we confirm fitment first.</p></div>
  <div class="grid"><?php foreach ($featured as $i => $p) echo productCard($p, $i > 3); ?></div>
  <p style="margin-top:32px;text-align:center"><a class="btn btn-ghost" href="<?= e(APP_URL) ?>/shop.php">See all stock <i class="fa-solid fa-arrow-right"></i></a></p>
</div></section>
<?php endif; ?>

<section class="sec"><div class="wrap">
  <div class="sec-head center"><span class="eyebrow">How it works</span><h2>From enquiry to delivery in four steps</h2></div>
  <div class="steps">
    <div class="step glass"><h3>Tell us what you need</h3><p>Send your vehicle, year and engine code on the form, WhatsApp or phone.</p></div>
    <div class="step glass"><h3>Get a written quote</h3><p>We confirm fitment and send a branded quote with the price, warranty and delivery cost.</p></div>
    <div class="step glass"><h3>Accept and pay</h3><p>Accept the quote online, pay by EFT, and receive your tax invoice.</p></div>
    <div class="step glass"><h3>We deliver</h3><p>Your unit is packed and couriered, or you collect it from us.</p></div>
  </div>
</div></section>

<section class="sec sec-alt"><div class="wrap">
  <div class="sec-head"><span class="eyebrow">Why Engine Empire</span><h2>Parts you can trust, service you can check</h2></div>
  <div class="split">
    <div class="feat glass"><i class="fa-solid fa-shield-halved"></i><h3>Tested with warranty</h3><p>Every engine and gearbox is checked before it leaves, with the warranty written on your invoice.</p></div>
    <div class="feat glass"><i class="fa-solid fa-file-invoice-dollar"></i><h3>Clear quotes and tax invoices</h3><p>Itemised, VAT-ready documents with our banking details. No surprises at the end.</p></div>
    <div class="feat glass"><i class="fa-solid fa-truck-fast"></i><h3>Nationwide delivery</h3><p>Fast, insured courier to every province, or collect from us.</p></div>
    <div class="feat glass"><i class="fa-solid fa-headset"></i><h3>Expert advice</h3><p>Tell us the vehicle and symptoms. We help you choose the right unit, not the dearest.</p></div>
  </div>
</div></section>

<?php if ($makes): ?>
<section class="sec"><div class="wrap">
  <div class="sec-head"><span class="eyebrow">Brands</span><h2>Shop by make</h2></div>
  <?php arsort($makes); $topMakes = array_slice($makes, 0, 30, true); ksort($topMakes); ?><div class="brands"><?php foreach ($topMakes as $m => $n): $m = (string) $m; ?><a href="<?= e(APP_URL) ?>/shop.php?make=<?= e(rawurlencode($m)) ?>"><?= e($m) ?> <span style="color:var(--text-3)">(<?= (int) $n ?>)</span></a><?php endforeach; ?></div>
</div></section>
<?php endif; ?>

<section class="sec sec-alt"><div class="wrap">
  <div class="sec-head center"><span class="eyebrow">FAQ</span><h2>Questions we hear every day</h2></div>
  <div class="faq"><?php foreach ($faq as [$q, $a]): ?><details class="glass"><summary><?= e($q) ?></summary><p><?= e($a) ?></p></details><?php endforeach; ?></div>
</div></section>

<section class="sec cta" id="quote"><div class="wrap cta-in">
  <div>
    <span class="eyebrow" style="color:#ffa072">Free quote</span>
    <h2>Tell us what you drive. We do the rest.</h2>
    <p class="lead">One short form. We confirm fitment, check stock and send you a branded quote you can accept online.</p>
    <ul><li><i class="fa-solid fa-check"></i>Reply within the hour on working days</li><li><i class="fa-solid fa-check"></i>Fitment confirmed before you pay</li><li><i class="fa-solid fa-check"></i>Tax invoice with warranty details</li></ul>
    <?php if ($wa): ?><p style="margin-top:24px"><a class="btn btn-wa btn-lg" href="<?= e($wa) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Prefer WhatsApp?</a></p><?php endif; ?>
  </div>
  <?php enquiryForm(); ?>
</div></section>
</main>
<?php
siteFooter();
pageFoot();
