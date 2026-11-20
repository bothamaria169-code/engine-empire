<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
$mail = get_setting('contact_email', CONTACT_EMAIL);
pageHead('Terms of sale — ' . APP_NAME, 'Terms for buying engines, gearboxes and car parts from Engine Empire: fitment, prices, delivery, returns.', ['canonical' => APP_URL . '/terms.php']);
simpleTop();
?>
<div class="prose glass">
  <h1>Terms of sale</h1>
  <p>Last updated: <?= date('j F Y', filemtime(__FILE__)) ?></p>
  <h2>1. How ordering works</h2>
  <p>Sending an enquiry is not yet an order. We first confirm with you that the part is still in stock, that it fits your vehicle, the price and the delivery cost. Your order is confirmed once you accept that quote and pay.</p>
  <h2>2. Fitment and warranty</h2>
  <p>Engines and gearboxes differ between model years and trim levels. Give us your vehicle's make, model, year and engine code (or a photo of the engine plate) so we can confirm fitment before you pay. Warranty periods are shown on each quote and invoice and cover the unit itself, not fitting labour, consumables or damage from poor installation.</p>
  <h2>3. Quotes, invoices and condition</h2>
  <p>A quote is valid for the period printed on it (normally 7 days) and is not a reservation until you accept it and pay the deposit or full amount. An invoice is payable by the due date shown. Where a unit is used we describe its condition.</p>
  <h2>4. Prices</h2>
  <p>Prices are in South African rand. Where the site says "Get a quote", we send the price with your quote. Delivery is quoted separately.</p>
  <h2>5. Delivery</h2>
  <p>We deliver across South Africa by courier. Delivery times are estimates from the courier. Check the parcel when it arrives and tell us about any damage within 48 hours, with photos.</p>
  <h2>6. Returns and refunds</h2>
  <p>If a part arrives damaged, is not as described, or does not fit the vehicle details you gave us, tell us within 7 days and we will exchange or refund it. Units must come back uninstalled and in the condition you received them. Nothing here limits your rights under the Consumer Protection Act or the Electronic Communications and Transactions Act.</p>
  <h2>7. Contact</h2>
  <p>Questions or complaints: <a href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a>.</p>
</div>
<?php
simpleFooter();
pageFoot();
