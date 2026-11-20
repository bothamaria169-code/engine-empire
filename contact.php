<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$mail = get_setting('contact_email', CONTACT_EMAIL);
$phone = get_setting('business_phone', get_setting('whatsapp_number', ''));
$addr = get_setting('business_address', '');
$wa = whatsappLink('Hi, I need a quote.');

pageHead('Contact & free quote — ' . APP_NAME, 'Request a quote for an engine, gearbox or car part. We reply within the hour on working days.', ['canonical' => APP_URL . '/contact.php']);
siteHeader('contact.php');
?>
<main id="main" class="page">
  <div class="page-head"><h1>Contact us &amp; get a quote</h1><p>Fill in the form, or reach us directly. We confirm fitment before you pay.</p></div>
  <div class="cta-in" style="grid-template-columns:minmax(0,.8fr) minmax(0,1.2fr)">
    <div class="glass feat" style="align-self:start">
      <h3>Reach us directly</h3>
      <ul style="list-style:none;padding:0;margin:16px 0 0;display:grid;gap:14px">
        <?php if ($phone): ?><li><i class="fa-solid fa-phone"></i> <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= e($phone) ?></a></li><?php endif; ?>
        <?php if ($wa): ?><li><i class="fa-brands fa-whatsapp"></i> <a href="<?= e($wa) ?>" target="_blank" rel="noopener">Chat on WhatsApp</a></li><?php endif; ?>
        <li><i class="fa-solid fa-envelope"></i> <a href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a></li>
        <?php if ($addr): ?><li><i class="fa-solid fa-location-dot"></i> <?= e($addr) ?></li><?php endif; ?>
        <li><i class="fa-solid fa-clock"></i> Mon–Fri 08:00–17:00 · Sat 08:00–13:00</li>
      </ul>
    </div>
    <div class="glass" style="border-radius:var(--r-xl)"><?php enquiryForm(); ?></div>
  </div>
</main>
<?php
siteFooter();
pageFoot();
