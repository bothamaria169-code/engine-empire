<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

pageHead('About us — ' . APP_NAME, 'Engine Empire supplies tested engines, gearboxes and car parts to workshops, fleets and drivers across South Africa.', ['canonical' => APP_URL . '/about.php']);
siteHeader('about.php');
?>
<main id="main" class="page">
  <div class="prose glass">
    <h1>About <?= e(APP_NAME) ?></h1>
    <p>We supply tested engines, gearboxes and car parts to workshops, fleet owners and drivers across South Africa. Our job is simple: help you find the right unit, put the price in writing, and deliver it with a warranty.</p>
    <h2>What we do</h2>
    <p>We stock petrol and diesel engines, manual and automatic gearboxes, sub assemblies, turbos and general spares for all the major brands. If we do not have it, we source it and quote you before you commit.</p>
    <h2>How we work</h2>
    <p>Every sale starts with a conversation about your vehicle. We confirm the engine code and year, send a written quote, and issue a tax invoice with the warranty period once you accept and pay. No guessing and no hidden costs.</p>
    <p style="margin-top:20px"><a class="btn btn-primary" href="<?= e(APP_URL) ?>/contact.php">Get a free quote</a></p>
  </div>
</main>
<?php
siteFooter();
pageFoot();
