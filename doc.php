<?php
declare(strict_types=1);

/**
 * Customer-facing quote / invoice. Reached through the secret link in the document
 * (doc.php?t=<token>). Prints cleanly to A4, so "Print → Save as PDF" gives the customer a PDF.
 */
require_once __DIR__ . '/includes/documents.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

startAppSession();
$doc = docByToken((string) ($_GET['t'] ?? ''));
if (!$doc) {
    http_response_code(404);
    pageHead('Document not found — ' . APP_NAME, 'This link is not valid.', ['noindex' => true]);
    echo '<main class="auth"><div class="auth-card glass"><h1 style="font-size:1.5rem">We could not find that document</h1><p>Please check the link we sent you, or contact us.</p><a class="btn btn-primary" href="' . e(APP_URL) . '/">Go to the website</a></div></main>';
    pageFoot(false);
    exit;
}
$id = (int) $doc['id'];
$isStaff = currentAdmin() !== null;
$msg = '';

// Customer accepts / declines a quote.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $doc['doc_type'] === 'quote' && in_array($doc['status'], ['sent', 'draft'], true)) {
    requireCsrf();
    $act = (string) ($_POST['action'] ?? '');
    $expired = $doc['due_date'] && $doc['due_date'] < date('Y-m-d');
    if ($act === 'accept' && !$expired) {
        getDB()->prepare("UPDATE documents SET status = 'accepted' WHERE id = ?")->execute([$id]);
        logDocEvent($id, 'accepted', 'By customer on the website');
        sendMail(business()['email'] ?: CONTACT_EMAIL, "Quote {$doc['doc_number']} accepted — {$doc['cust_name']}", "{$doc['cust_name']} accepted quote {$doc['doc_number']} (" . rands($doc['total']) . ").\nCreate the invoice here: " . APP_URL . '/admin/document.php?id=' . $id);
        $msg = 'Thank you! Your quote is accepted. We will send your invoice with the banking details shortly.';
    } elseif ($act === 'decline') {
        getDB()->prepare("UPDATE documents SET status = 'declined' WHERE id = ?")->execute([$id]);
        logDocEvent($id, 'declined', 'By customer on the website');
        $msg = 'Understood. The quote is marked as declined. Contact us any time if you change your mind.';
    }
    $doc = docById($id);
}

// Count the customer's views, never staff previews.
if (!$isStaff && $_SERVER['REQUEST_METHOD'] === 'GET') {
    getDB()->prepare('UPDATE documents SET view_count = view_count + 1, viewed_at = COALESCE(viewed_at, NOW()) WHERE id = ?')->execute([$id]);
    if (!$doc['viewed_at']) logDocEvent($id, 'viewed');
}

$items = docItems($id);
$b = business();
$isQuote = $doc['doc_type'] === 'quote';
$vatReg = (float) $doc['vat_rate'] > 0;
$title = $isQuote ? 'Quote' : ($vatReg ? 'Tax invoice' : 'Invoice');
$balance = max(0.0, (float) $doc['total'] - (float) $doc['amount_paid']);
$overdue = docIsOverdue($doc);
$tone = ['draft' => '', 'sent' => 'info', 'accepted' => 'ok', 'declined' => 'bad', 'part_paid' => 'warn', 'paid' => 'ok', 'cancelled' => 'bad'][$doc['status']] ?? '';
$statusLabel = $overdue ? 'Overdue' : DOC_STATUSES[$doc['status']];
$expired = $isQuote && $doc['due_date'] && $doc['due_date'] < date('Y-m-d') && $doc['status'] === 'sent';
$wa = whatsappLink("Hi, about {$title} {$doc['doc_number']}.");
$terms = (string) ($doc['terms'] ?: get_setting($isQuote ? 'quote_terms' : 'invoice_terms', $isQuote ? DEFAULT_QUOTE_TERMS : DEFAULT_INVOICE_TERMS));

pageHead("{$title} {$doc['doc_number']} — {$b['name']}", "{$title} {$doc['doc_number']}", ['noindex' => true, 'css' => ['assets/css/doc.css'], 'bodyClass' => 'docpage', 'canonical' => docUrl($doc)]);
?>
<div class="doc-bar no-print">
  <a class="brand" href="<?= e(APP_URL) ?>/"><?= brandMark() ?><span><?= e($b['name']) ?></span></a>
  <div class="doc-actions">
    <?php if ($isStaff): ?><a class="btn btn-ghost btn-sm" href="<?= e(APP_URL) ?>/admin/document.php?id=<?= $id ?>"><i class="fa-solid fa-arrow-left"></i> Back to admin</a><?php endif; ?>
    <button class="btn btn-dark btn-sm" onclick="window.print()"><i class="fa-solid fa-print"></i> Print / Save as PDF</button>
    <button class="icon-btn" data-theme-toggle aria-label="Switch light / dark theme"><i class="fa-solid fa-circle-half-stroke"></i></button>
  </div>
</div>

<main class="doc-wrap">
  <?php if ($msg): ?><p class="alert alert-ok no-print" role="status"><?= e($msg) ?></p><?php endif; ?>
  <?php if ($expired): ?><p class="alert alert-err no-print">This quote expired on <?= e(date('j M Y', strtotime((string) $doc['due_date']))) ?>. Contact us and we will refresh it.</p><?php endif; ?>

  <article class="sheet-a4">
    <header class="doc-top">
      <div class="doc-from">
        <div class="brand"><?= brandMark() ?><span><?= e($b['name']) ?></span></div>
        <p><?= nl2br(e($b['address'])) ?></p>
        <p><?= e($b['phone']) ?><?= $b['phone'] && $b['email'] ? ' · ' : '' ?><?= e($b['email']) ?></p>
        <?php if ($b['vat_no']): ?><p>VAT no. <?= e($b['vat_no']) ?></p><?php endif; ?>
        <?php if ($b['reg_no']): ?><p>Reg. no. <?= e($b['reg_no']) ?></p><?php endif; ?>
      </div>
      <div class="doc-title">
        <h1><?= e(strtoupper($title)) ?></h1>
        <p class="doc-no"><?= e($doc['doc_number']) ?></p>
        <span class="pill <?= $overdue ? 'bad' : e($tone) ?>"><?= e($statusLabel) ?></span>
      </div>
    </header>

    <section class="doc-meta">
      <div><h2>Billed to</h2>
        <p><b><?= e($doc['cust_name']) ?></b></p>
        <?php if ($doc['cust_company']): ?><p><?= e($doc['cust_company']) ?></p><?php endif; ?>
        <?php if ($doc['cust_address']): ?><p><?= nl2br(e($doc['cust_address'])) ?></p><?php endif; ?>
        <?php if ($doc['cust_phone']): ?><p><?= e($doc['cust_phone']) ?></p><?php endif; ?>
        <?php if ($doc['cust_email']): ?><p><?= e($doc['cust_email']) ?></p><?php endif; ?>
        <?php if ($doc['cust_vat_no']): ?><p>VAT no. <?= e($doc['cust_vat_no']) ?></p><?php endif; ?>
      </div>
      <dl>
        <dt>Date</dt><dd><?= e(date('j M Y', strtotime((string) $doc['issue_date']))) ?></dd>
        <?php if ($doc['due_date']): ?><dt><?= $isQuote ? 'Valid until' : 'Due date' ?></dt><dd><?= e(date('j M Y', strtotime((string) $doc['due_date']))) ?></dd><?php endif; ?>
        <?php if (!$isQuote): ?><dt>Amount due</dt><dd class="due"><?= e(rands($balance)) ?></dd><?php endif; ?>
      </dl>
    </section>

    <table class="doc-items">
      <thead><tr><th>Description</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Amount</th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
        <tr><td><?= nl2br(e($it['description'])) ?></td><td class="num"><?= e(rtrim(rtrim(number_format((float) $it['qty'], 2, '.', ''), '0'), '.')) ?></td><td class="num"><?= e(rands($it['unit_price'])) ?></td><td class="num"><?= e(rands((float) $it['qty'] * (float) $it['unit_price'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <section class="doc-sum">
      <div class="doc-notes">
        <?php if ($doc['notes']): ?><h2>Notes</h2><p><?= nl2br(e($doc['notes'])) ?></p><?php endif; ?>
        <?php if ($b['bank']): ?><h2>Banking details (EFT)</h2>
          <dl class="bank"><?php foreach ($b['bank'] as $k => $v): ?><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd><?php endforeach; ?><dt>Reference</dt><dd><?= e($doc['doc_number']) ?></dd></dl><?php endif; ?>
      </div>
      <table class="totals">
        <tr><td>Subtotal</td><td class="num"><?= e(rands($doc['subtotal'])) ?></td></tr>
        <?php if ((float) $doc['discount'] > 0): ?><tr><td>Discount</td><td class="num">− <?= e(rands($doc['discount'])) ?></td></tr><?php endif; ?>
        <?php if ($vatReg): ?><tr><td>VAT <?= e(rtrim(rtrim(number_format((float) $doc['vat_rate'], 2, '.', ''), '0'), '.')) ?>%</td><td class="num"><?= e(rands($doc['vat_amount'])) ?></td></tr><?php endif; ?>
        <tr class="grand"><td>Total</td><td class="num"><?= e(rands($doc['total'])) ?></td></tr>
        <?php if (!$isQuote && (float) $doc['amount_paid'] > 0): ?>
          <tr><td>Paid</td><td class="num">− <?= e(rands($doc['amount_paid'])) ?></td></tr>
          <tr class="grand"><td>Balance due</td><td class="num"><?= e(rands($balance)) ?></td></tr>
        <?php endif; ?>
      </table>
    </section>

    <?php if ($terms): ?><section class="doc-terms"><h2>Terms</h2><p><?= nl2br(e($terms)) ?></p></section><?php endif; ?>
    <footer class="doc-foot">Thank you for choosing <?= e($b['name']) ?>.</footer>
  </article>

  <?php if ($isQuote && $doc['status'] === 'sent' && !$expired): ?>
  <form method="post" class="doc-accept no-print glass">
    <?= csrfField() ?>
    <div><b>Happy with this quote?</b><br><span style="color:var(--text-2)">Accept it and we will send your invoice with the banking details.</span></div>
    <div class="doc-actions">
      <button class="btn btn-primary" name="action" value="accept"><i class="fa-solid fa-circle-check"></i> Accept quote</button>
      <button class="btn btn-ghost" name="action" value="decline" onclick="return confirm('Decline this quote?')">Decline</button>
      <?php if ($wa): ?><a class="btn btn-wa" href="<?= e($wa) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Ask a question</a><?php endif; ?>
    </div>
  </form>
  <?php endif; ?>
</main>
<?php
pageFoot();
