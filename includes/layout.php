<?php
declare(strict_types=1);

require_once __DIR__ . '/catalog.php';

/** <head> shared by every page. $o: canonical, image, schema (array), bodyClass, css, ogType, noindex */
function pageHead(string $title, string $desc, array $o = []): void
{
    startAppSession();
    $canonical = $o['canonical'] ?? APP_URL . '/';
    $image = $o['image'] ?? APP_URL . '/assets/img/og.png';
    $ga = get_setting('ga4_id');
    $pixel = get_setting('meta_pixel_id');
    ?>
<!doctype html>
<html lang="en-ZA">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta name="csrf" content="<?= e(csrfToken()) ?>">
<meta name="theme-color" content="#c2410c">
<?php if (!empty($o['noindex'])): ?><meta name="robots" content="noindex"><?php endif; ?>
<meta property="og:type" content="<?= e($o['ogType'] ?? 'website') ?>">
<meta property="og:site_name" content="<?= e(APP_NAME) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($image) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php if ($v = get_setting('google_site_verification')): ?><meta name="google-site-verification" content="<?= e($v) ?>"><?php endif; ?>
<link rel="icon" href="<?= e(APP_URL) ?>/assets/img/mark.svg" type="image/svg+xml">
<script>(function(){try{var t=localStorage.getItem('ee-theme');if(t)document.documentElement.dataset.theme=t}catch(e){}})()</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
<?php foreach ($o['css'] ?? [] as $c): ?><link rel="stylesheet" href="<?= e(asset($c)) ?>"><?php endforeach; ?>
<?php foreach ($o['schema'] ?? [] as $s): ?><script type="application/ld+json"><?= json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script><?php endforeach; ?>
<?php if ($ga && !IS_LOCAL): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config',<?= json_encode($ga) ?>);</script>
<?php endif; ?>
<?php if ($pixel && !IS_LOCAL): ?>
<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init',<?= json_encode($pixel) ?>);fbq('track','PageView');</script>
<?php endif; ?>
</head>
<body class="<?= e($o['bodyClass'] ?? '') ?>">
<?php
}

function pageFoot(bool $appJs = true): void
{
    $cfg = ['base' => APP_URL, 'whatsapp' => whatsappLink()];
    ?>
<script>window.EE = Object.assign(window.EE || {}, <?= json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>);</script>
<?php if ($appJs): ?><script src="<?= e(asset('assets/js/app.js')) ?>" defer></script><?php endif; ?>
</body>
</html>
<?php
}

function brandMark(): string
{
    return '<span class="mark" aria-hidden="true"><svg viewBox="0 0 32 32" width="22" height="22"><path d="M5 16c0-5 4-9 9-9h3v18h-3c-5 0-9-4-9-9z" fill="currentColor"/><path d="M21 9l6-2M21 16h7M21 23l6 2" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg></span>';
}

/** Product card. $p = productPublic(). */
function productCard(array $p, bool $lazy = true): string
{
    ob_start(); ?>
<article class="pcard glass" data-id="<?= (int) $p['id'] ?>">
  <a class="pcard-img" href="<?= e($p['url']) ?>" aria-label="View <?= e($p['title']) ?>">
    <img src="<?= e($p['thumb']) ?>" alt="<?= e($p['title']) ?>" width="560" height="420" <?= $lazy ? 'loading="lazy"' : 'fetchpriority="high"' ?> decoding="async">
    <span class="badge"><?= e($p['categoryLabel']) ?></span>
    <?php if ($p['videos']): ?><span class="badge badge-r"><i class="fa-solid fa-play"></i> Video</span><?php endif; ?>
    <?php if ($p['reserved']): ?><span class="badge badge-warn">Reserved</span><?php endif; ?>
  </a>
  <div class="pcard-body">
    <h3><a href="<?= e($p['url']) ?>"><?= e($p['title']) ?></a></h3>
    <p class="veh"><i class="fa-solid fa-car-side"></i> <?= e($p['make'] . ' ' . $p['model']) ?></p>
    <p class="meta"><?= e($p['grade']) ?><?= $p['warranty'] ? ' · ' . e($p['warranty']) . ' warranty' : '' ?></p>
    <div class="pcard-foot">
      <strong class="price<?= $p['hasPrice'] ? '' : ' ask' ?>"><?= e($p['price']) ?></strong>
      <a class="btn btn-primary btn-sm" href="<?= e($p['url']) ?>#enquire">Get a quote</a>
    </div>
  </div>
</article>
<?php
    return (string) ob_get_clean();
}

const NAV = ['index.php' => 'Home', 'shop.php' => 'Shop', 'about.php' => 'About us', 'contact.php' => 'Contact'];

/** Announcement bar + sticky header. $active = file name of the current page. */
function siteHeader(string $active = ''): void
{
    $phone = get_setting('business_phone', get_setting('whatsapp_number', ''));
    ?>
<a class="sr" href="#main">Skip to content</a>
<div class="announce">Tested engines &amp; gearboxes with warranty · Delivery across South Africa · <a href="<?= e(APP_URL) ?>/contact.php">Get a free quote</a></div>
<header class="top">
  <div class="top-in">
    <a class="brand" href="<?= e(APP_URL) ?>/"><?= brandMark() ?><span><?= e(APP_NAME) ?></span></a>
    <nav class="nav" id="nav" aria-label="Main">
      <?php foreach (NAV as $file => $label): ?><a href="<?= e(APP_URL . '/' . ($file === 'index.php' ? '' : $file)) ?>" class="<?= $active === $file ? 'on' : '' ?>"><?= e($label) ?></a><?php endforeach; ?>
    </nav>
    <div class="top-actions">
      <?php if ($phone): ?><a class="btn btn-ghost btn-sm" href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><i class="fa-solid fa-phone"></i> <span><?= e($phone) ?></span></a><?php endif; ?>
      <a class="btn btn-primary btn-sm" href="<?= e(APP_URL) ?>/contact.php"><i class="fa-solid fa-file-invoice"></i> <span>Get a quote</span></a>
      <button class="icon-btn" data-theme-toggle aria-label="Switch light / dark theme"><i class="fa-solid fa-circle-half-stroke"></i></button>
      <button class="icon-btn menu-btn" data-menu aria-label="Menu" aria-controls="nav" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
    </div>
  </div>
</header>
<?php
}

function siteFooter(): void
{
    $mail = get_setting('contact_email', CONTACT_EMAIL);
    $phone = get_setting('business_phone', get_setting('whatsapp_number', ''));
    $addr = get_setting('business_address', '');
    $wa = whatsappLink('Hi, I need a quote.');
    ?>
<footer class="foot-main">
  <div class="wrap">
    <div class="foot-grid">
      <div><a class="brand" href="<?= e(APP_URL) ?>/"><?= brandMark() ?><span><?= e(APP_NAME) ?></span></a>
        <p>Quality tested engines, gearboxes and spares for every major brand. Fast quotes, honest prices, nationwide delivery.</p></div>
      <div><h3>Shop</h3><ul>
        <?php foreach (CATEGORIES as $k => $l): ?><li><a href="<?= e(APP_URL) ?>/shop.php?category=<?= e($k) ?>"><?= e($l) ?></a></li><?php endforeach; ?></ul></div>
      <div><h3>Company</h3><ul>
        <li><a href="<?= e(APP_URL) ?>/about.php">About us</a></li><li><a href="<?= e(APP_URL) ?>/contact.php">Contact</a></li>
        <li><a href="<?= e(APP_URL) ?>/privacy.php">Privacy</a></li><li><a href="<?= e(APP_URL) ?>/terms.php">Terms</a></li><li><a href="<?= e(APP_URL) ?>/login.php">Staff sign-in</a></li></ul></div>
      <div><h3>Contact</h3><ul>
        <?php if ($phone): ?><li><i class="fa-solid fa-phone"></i> <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= e($phone) ?></a></li><?php endif; ?>
        <li><i class="fa-solid fa-envelope"></i> <a href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a></li>
        <?php if ($addr): ?><li><i class="fa-solid fa-location-dot"></i> <?= e($addr) ?></li><?php endif; ?></ul></div>
    </div>
    <div class="foot-bar"><span>© <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.</span><span>Prices exclude delivery unless quoted. Stock changes daily.</span></div>
  </div>
</footer>
<?php if ($wa): ?><a class="wa-float" href="<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp"><i class="fa-brands fa-whatsapp"></i></a><?php endif; ?>
<?php
}

/** The "Get a quote" enquiry form. Posts to ajax/enquiry.php (see app.js). */
function enquiryForm(?array $product = null, string $title = 'Get a free quote', string $sub = 'Tell us what you need. We reply with a quote on WhatsApp or email, usually within the hour.'): void
{
    ?>
<form class="form" data-enquiry novalidate>
  <h2><?= e($title) ?></h2>
  <p class="sub"><?= e($sub) ?></p>
  <input type="hidden" name="product_id" value="<?= (int) ($product['id'] ?? 0) ?>">
  <div class="row2">
    <div class="field"><label for="q-name">Your name</label><input id="q-name" name="name" required autocomplete="name"></div>
    <div class="field"><label for="q-phone">Cell number</label><input id="q-phone" name="phone" type="tel" inputmode="tel" required autocomplete="tel" placeholder="082 123 4567"></div>
  </div>
  <div class="row2">
    <div class="field"><label for="q-email">Email (optional)</label><input id="q-email" name="email" type="email" autocomplete="email"></div>
    <div class="field"><label for="q-area">Town / suburb</label><input id="q-area" name="area" autocomplete="address-level2"></div>
  </div>
  <div class="field"><label for="q-vehicle">Vehicle (make, model, year)</label><input id="q-vehicle" name="vehicle" placeholder="e.g. Toyota Hilux 2.7 2012"></div>
  <div class="field"><label for="q-message">What do you need?</label><textarea id="q-message" name="message" placeholder="Engine / gearbox / part, engine code if you have it"><?= $product ? e('I would like a quote for: ' . $product['title']) : '' ?></textarea></div>
  <label class="check"><input type="checkbox" name="consent" value="1" required> <span>I agree that <?= e(APP_NAME) ?> may contact me about this quote (<a href="<?= e(APP_URL) ?>/privacy.php" target="_blank">privacy policy</a>).</span></label>
  <p class="err" data-error role="alert" hidden></p>
  <button class="btn btn-primary btn-lg btn-block" type="submit"><i class="fa-solid fa-paper-plane"></i> Send my request</button>
  <div class="ok-box" data-done hidden></div>
</form>
<?php
}

/** Header for text pages (privacy, terms): the normal site header, then <main>. */
function simpleTop(): void
{
    siteHeader();
    echo '<main id="main" class="page">';
}

function simpleFooter(): void
{
    echo '</main>';
    siteFooter();
}
