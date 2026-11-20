<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
$mail = get_setting('contact_email', CONTACT_EMAIL);
pageHead('Privacy policy — ' . APP_NAME, 'How Engine Empire collects, uses and protects your personal information (POPIA).', ['canonical' => APP_URL . '/privacy.php']);
simpleTop();
?>
<div class="prose glass">
  <h1>Privacy policy</h1>
  <p>Last updated: <?= date('j F Y', filemtime(__FILE__)) ?></p>
  <p><?= e(APP_NAME) ?> ("we") sells engines, gearboxes and car parts in South Africa through engineempire.co.za. This page explains what personal information we collect and what we do with it, in line with the Protection of Personal Information Act (POPIA).</p>
  <h2>What we collect</h2>
  <ul>
    <li><strong>Enquiries and orders:</strong> your name, cell number, optional email, town or suburb, your vehicle details and your message.</li>
    <li><strong>Quotes and invoices:</strong> the details on a quote or invoice we send you (name, contact details, items, amounts and payments).</li>
    <li><strong>Technical data:</strong> your IP address and a session cookie that keeps our forms working.</li>
  </ul>
  <h2>Why we use it</h2>
  <p>To answer your enquiry, confirm that a part fits your vehicle, quote a price and delivery cost, deliver your order, and improve our stock. We do not sell your information.</p>
  <h2>Who we share it with</h2>
  <p>Only the parties needed to help you: the courier that delivers your order, our hosting provider,.</p>
  <h2>Cookies and analytics</h2>
  <p>We use one essential session cookie. If we switch on Google Analytics or the Meta Pixel, they measure visits and advertising results.</p>
  <h2>How long we keep it</h2>
  <p>Enquiries and orders are kept for as long as needed for the sale and for our tax and record-keeping duties. </p>
  <h2>Your rights</h2>
  <p>You may ask us for a copy of your information, to correct it, or to delete it. Email <a href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a> and we will respond within 30 days. You may also complain to the Information Regulator (South Africa).</p>
</div>
<?php
simpleFooter();
pageFoot();
