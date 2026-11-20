<?php
declare(strict_types=1);

require __DIR__ . '/_shell.php';

$db = getDB();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$doc = $id ? docById($id) : null;
if ($id && !$doc) { flash('err', 'That document no longer exists.'); redirect('admin/documents.php'); }

// ── Actions ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save');
    try {
        if ($action === 'save') {
            $newId = saveDocument($_POST, $doc ? (int) $doc['id'] : null, (int) $ADMIN['id']);
            if (!$doc && ($eid = (int) ($_POST['enquiry_id'] ?? 0))) {
                $db->prepare("UPDATE enquiries SET status = 'quoted' WHERE id = ? AND status IN ('new','contacted')")->execute([$eid]);
            }
            audit('document_save', "#{$newId}");
            flash('ok', 'Saved.');
            redirect("admin/document.php?id={$newId}");
        }
        if (!$doc) redirect('admin/documents.php');
        $did = (int) $doc['id'];

        switch ($action) {
            case 'email':
                $err = emailDocument($doc);
                flash($err === '' ? 'ok' : 'err', $err === '' ? 'Emailed to ' . $doc['cust_email'] . '.' : 'Email not sent: ' . $err);
                break;
            case 'whatsapp':
                $db->prepare("UPDATE documents SET whatsapped_at = NOW(), status = IF(status = 'draft', 'sent', status) WHERE id = ?")->execute([$did]);
                logDocEvent($did, 'whatsapp', (string) $doc['cust_phone']);
                if (waDigits($doc['cust_phone']) === '') { flash('err', 'This customer has no cell number.'); break; }
                redirect(docWhatsappUrl($doc));
            case 'mark_sent':
                $db->prepare("UPDATE documents SET status = 'sent' WHERE id = ? AND status = 'draft'")->execute([$did]);
                logDocEvent($did, 'status', 'Marked as sent');
                flash('ok', 'Marked as sent.');
                break;
            case 'accept':
            case 'decline':
                $st = $action === 'accept' ? 'accepted' : 'declined';
                $db->prepare('UPDATE documents SET status = ? WHERE id = ? AND doc_type = \'quote\'')->execute([$st, $did]);
                logDocEvent($did, $st, 'Recorded by staff');
                flash('ok', 'Quote ' . $st . '.');
                break;
            case 'cancel':
                $db->prepare("UPDATE documents SET status = 'cancelled' WHERE id = ?")->execute([$did]);
                logDocEvent($did, 'status', 'Cancelled');
                flash('ok', 'Cancelled.');
                break;
            case 'convert':
                $nid = convertToInvoice($did, (int) $ADMIN['id']);
                audit('quote_to_invoice', "#{$did} → #{$nid}");
                flash('ok', 'Invoice created from the quote. Check the dates and send it.');
                redirect("admin/document.php?id={$nid}");
            case 'payment':
                addPayment($did, parseMoney($_POST['amount'] ?? 0), postText('method', 20), postText('reference', 120), postText('paid_on', 10));
                audit('payment', "#{$did}");
                flash('ok', 'Payment recorded.');
                break;
            case 'delete_payment':
                $db->prepare('DELETE FROM payments WHERE id = ? AND document_id = ?')->execute([(int) ($_POST['payment_id'] ?? 0), $did]);
                refreshPaymentStatus($did);
                flash('ok', 'Payment removed.');
                break;
            case 'delete':
                if ($doc['status'] === 'draft') {
                    $db->prepare('DELETE FROM documents WHERE id = ?')->execute([$did]);
                    audit('document_delete', $doc['doc_number']);
                    flash('ok', 'Draft deleted.');
                    redirect('admin/documents.php');
                }
                flash('err', 'Only drafts can be deleted. Cancel it instead so the number stays on record.');
                break;
        }
    } catch (Throwable $e) {
        error_log('document action failed: ' . $e->getMessage());
        flash('err', 'Something went wrong: ' . $e->getMessage());
    }
    redirect('admin/document.php?id=' . ($doc['id'] ?? ''));
}

// ── Build the form data ──────────────────────────────────
$b = business();
$newType = in_array($_GET['new'] ?? '', ['quote', 'invoice'], true) ? (string) $_GET['new'] : '';
if (!$doc && $newType === '') redirect('admin/documents.php');
$type = $doc['doc_type'] ?? $newType;
$items = $doc ? docItems((int) $doc['id']) : [];
$d = $doc ?: [
    'doc_type' => $type, 'doc_number' => '(assigned on save)', 'customer_id' => null, 'enquiry_id' => null, 'cust_name' => '', 'cust_company' => '', 'cust_phone' => '', 'cust_email' => '', 'cust_address' => '', 'cust_vat_no' => '',
    'issue_date' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime('+' . (int) get_setting($type === 'quote' ? 'quote_validity_days' : 'payment_terms_days', '7') . ' days')),
    'vat_rate' => $b['vat_no'] !== '' ? $b['vat_rate'] : 0, 'discount' => 0, 'notes' => '', 'terms' => get_setting($type === 'quote' ? 'quote_terms' : 'invoice_terms', $type === 'quote' ? DEFAULT_QUOTE_TERMS : DEFAULT_INVOICE_TERMS), 'status' => 'draft',
    'subtotal' => 0, 'vat_amount' => 0, 'total' => 0, 'amount_paid' => 0,
];
$fromEnquiry = null;
if (!$doc && ($eid = (int) ($_GET['enquiry'] ?? 0))) {
    $s = $db->prepare('SELECT e.*, p.title, p.price, p.id AS pid, p.warranty FROM enquiries e LEFT JOIN products p ON p.id = e.product_id WHERE e.id = ?');
    $s->execute([$eid]);
    if ($fromEnquiry = $s->fetch()) {
        $d['enquiry_id'] = $eid; $d['cust_name'] = $fromEnquiry['customer_name']; $d['cust_phone'] = $fromEnquiry['phone']; $d['cust_email'] = (string) $fromEnquiry['email'];
        $d['cust_address'] = (string) $fromEnquiry['area'];
        $d['notes'] = $fromEnquiry['vehicle'] ? 'Vehicle: ' . $fromEnquiry['vehicle'] : '';
        if ($fromEnquiry['title']) $items[] = ['product_id' => $fromEnquiry['pid'], 'description' => $fromEnquiry['title'] . ($fromEnquiry['warranty'] ? ' (' . $fromEnquiry['warranty'] . ' warranty)' : ''), 'qty' => 1, 'unit_price' => (float) $fromEnquiry['price']];
    }
}
if (!$doc && ($cid = (int) ($_GET['customer'] ?? 0))) {
    $c = $db->prepare('SELECT * FROM customers WHERE id = ?'); $c->execute([$cid]);
    if ($cu = $c->fetch()) { $d['customer_id'] = $cid; $d['cust_name'] = $cu['name']; $d['cust_company'] = (string) $cu['company']; $d['cust_phone'] = (string) $cu['phone']; $d['cust_email'] = (string) $cu['email']; $d['cust_address'] = (string) $cu['address']; $d['cust_vat_no'] = (string) $cu['vat_no']; }
}
if (!$items) $items[] = ['product_id' => null, 'description' => '', 'qty' => 1, 'unit_price' => ''];

$customers = $db->query('SELECT id, name, company, phone, email, address, vat_no FROM customers ORDER BY name LIMIT 500')->fetchAll();
$products = $db->query("SELECT id, title, price, warranty FROM products WHERE stock_status IN ('available','reserved') ORDER BY make, title LIMIT 500")->fetchAll();
$isQuote = $type === 'quote';
$locked = $doc && in_array($doc['status'], ['paid', 'cancelled'], true);
$word = $isQuote ? 'quote' : 'invoice';
$balance = (float) $d['total'] - (float) $d['amount_paid'];
$payments = $doc ? docPayments((int) $doc['id']) : [];
$events = $doc ? docEvents((int) $doc['id']) : [];
$existingInvoice = null;
if ($doc && $isQuote) {
    $x = $db->prepare("SELECT id, doc_number FROM documents WHERE converted_from = ? AND doc_type='invoice' AND status <> 'cancelled' LIMIT 1");
    $x->execute([$doc['id']]);
    $existingInvoice = $x->fetch() ?: null;
}

$head = '<a class="btn btn-ghost btn-sm" href="documents.php">← All documents</a>';
if ($doc) $head .= '<a class="btn btn-ghost btn-sm" target="_blank" href="' . e(docUrl($doc)) . '"><i class="fa-solid fa-eye"></i> Customer view / PDF</a>';
adminHead(($doc ? DOC_TYPES[$type] . ' ' . $doc['doc_number'] : 'New ' . $word), 'documents.php', $head);
$v = fn(string $k) => e((string) ($d[$k] ?? ''));
?>
<div class="doc-edit">
<form method="post" id="docform" class="doc-main">
  <?= csrfField() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $doc ? (int) $doc['id'] : 0 ?>">
  <input type="hidden" name="doc_type" value="<?= e($type) ?>"><input type="hidden" name="enquiry_id" value="<?= (int) ($d['enquiry_id'] ?? 0) ?>"><input type="hidden" name="customer_id" id="customer_id" value="<?= (int) ($d['customer_id'] ?? 0) ?>">

  <section class="card glass">
    <h2>Customer</h2>
    <?php if ($customers): ?><div class="field" style="margin-bottom:14px"><label for="pick">Pick a saved customer</label><select id="pick"><option value="">— new customer —</option><?php foreach ($customers as $c): ?><option value="<?= (int) $c['id'] ?>" data-c="<?= e(json_encode($c)) ?>"<?= (int) $c['id'] === (int) ($d['customer_id'] ?? 0) ? ' selected' : '' ?>><?= e($c['name'] . ($c['company'] ? ' — ' . $c['company'] : '') . ($c['phone'] ? ' · ' . $c['phone'] : '')) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <div class="fgrid">
      <div class="field"><label for="cust_name">Name</label><input id="cust_name" name="cust_name" required maxlength="160" value="<?= $v('cust_name') ?>"></div>
      <div class="field"><label for="cust_company">Company (optional)</label><input id="cust_company" name="cust_company" maxlength="160" value="<?= $v('cust_company') ?>"></div>
      <div class="field"><label for="cust_phone">Cell number</label><input id="cust_phone" name="cust_phone" inputmode="tel" maxlength="40" value="<?= $v('cust_phone') ?>"></div>
      <div class="field"><label for="cust_email">Email</label><input id="cust_email" name="cust_email" type="email" maxlength="255" value="<?= $v('cust_email') ?>"></div>
      <div class="field"><label for="cust_vat_no">Customer VAT no. (optional)</label><input id="cust_vat_no" name="cust_vat_no" maxlength="30" value="<?= $v('cust_vat_no') ?>"></div>
      <div class="field wide"><label for="cust_address">Address</label><textarea id="cust_address" name="cust_address" rows="2" maxlength="400"><?= $v('cust_address') ?></textarea></div>
    </div>
  </section>

  <section class="card glass">
    <h2>Details</h2>
    <div class="fgrid">
      <div class="field"><label for="issue_date">Date</label><input id="issue_date" name="issue_date" type="date" value="<?= $v('issue_date') ?>"></div>
      <div class="field"><label for="due_date"><?= $isQuote ? 'Valid until' : 'Due date' ?></label><input id="due_date" name="due_date" type="date" value="<?= $v('due_date') ?>"></div>
      <div class="field"><label for="vat_rate">VAT rate %</label><input id="vat_rate" name="vat_rate" inputmode="decimal" value="<?= e(rtrim(rtrim(number_format((float) $d['vat_rate'], 2, '.', ''), '0'), '.') ?: '0') ?>"><small>0 = no VAT. Prices below are entered <b>excluding</b> VAT.</small></div>
      <div class="field"><label for="discount">Discount (R)</label><input id="discount" name="discount" inputmode="decimal" value="<?= (float) $d['discount'] > 0 ? e((string) $d['discount']) : '' ?>"></div>
    </div>
  </section>

  <section class="card glass">
    <h2>Items</h2>
    <div class="items" id="items">
      <div class="items-head"><span>Description</span><span>Qty</span><span>Unit price (excl. VAT)</span><span>Amount</span><span></span></div>
      <?php foreach ($items as $it): ?>
      <div class="item-row">
        <input type="hidden" name="item_product[]" value="<?= (int) ($it['product_id'] ?? 0) ?>">
        <textarea name="item_desc[]" rows="2" maxlength="500" placeholder="e.g. Toyota Hilux 2.7 petrol engine (2TR), 3 months warranty" aria-label="Description"><?= e((string) $it['description']) ?></textarea>
        <input name="item_qty[]" inputmode="decimal" value="<?= e(rtrim(rtrim(number_format((float) $it['qty'], 2, '.', ''), '0'), '.')) ?>" aria-label="Quantity">
        <input name="item_price[]" inputmode="decimal" value="<?= $it['unit_price'] !== '' && (float) $it['unit_price'] > 0 ? e(number_format((float) $it['unit_price'], 2, '.', '')) : '' ?>" placeholder="0.00" aria-label="Unit price">
        <output class="line-total">R 0.00</output>
        <button type="button" class="icon-btn" data-remove aria-label="Remove line"><i class="fa-solid fa-trash"></i></button>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="inline-form" style="margin-top:12px">
      <button type="button" class="btn btn-ghost btn-sm" id="add-row"><i class="fa-solid fa-plus"></i> Add a line</button>
      <?php if ($products): ?><select id="add-product" aria-label="Add a product from stock"><option value="">Add from stock…</option><?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>" data-t="<?= e($p['title'] . ($p['warranty'] ? ' (' . $p['warranty'] . ' warranty)' : '')) ?>" data-p="<?= e((string) $p['price']) ?>"><?= e($p['title']) ?></option><?php endforeach; ?></select><?php endif; ?>
    </p>
    <table class="totals-box"><tr><td>Subtotal</td><td class="num" id="t-sub">R 0.00</td></tr><tr><td>Discount</td><td class="num" id="t-disc">R 0.00</td></tr><tr><td>VAT</td><td class="num" id="t-vat">R 0.00</td></tr><tr class="grand"><td>Total</td><td class="num" id="t-total">R 0.00</td></tr></table>
  </section>

  <section class="card glass">
    <h2>Notes &amp; terms</h2>
    <div class="fgrid">
      <div class="field wide"><label for="notes">Notes (shown on the document)</label><textarea id="notes" name="notes" rows="3" maxlength="2000" placeholder="Vehicle, engine code, delivery details…"><?= $v('notes') ?></textarea></div>
      <div class="field wide"><label for="terms">Terms</label><textarea id="terms" name="terms" rows="4" maxlength="3000"><?= $v('terms') ?></textarea><small>Default terms come from Settings → Business.</small></div>
    </div>
  </section>

  <p class="inline-form"><button class="btn btn-primary btn-lg" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save <?= e($word) ?></button></p>
</form>

<?php if ($doc): ?>
<aside class="doc-side">
  <section class="card glass">
    <h2>Status</h2>
    <p><?= pill(docStatusLabel($doc), docTone($doc)) ?> <small><?= (int) $doc['view_count'] ?> view<?= (int) $doc['view_count'] === 1 ? '' : 's' ?><?= $doc['viewed_at'] ? ' · first ' . e(date('j M H:i', strtotime((string) $doc['viewed_at']))) : '' ?></small></p>
    <p style="margin:10px 0 4px"><b><?= e(rands($doc['total'])) ?></b><?php if (!$isQuote): ?> · balance <b><?= e(rands($balance)) ?></b><?php endif; ?></p>
    <form method="post" class="stack">
      <?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $doc['id'] ?>">
      <button class="btn btn-primary btn-block" name="action" value="email"<?= $doc['cust_email'] ? '' : ' disabled' ?>><i class="fa-solid fa-envelope"></i> Email to customer</button>
      <button class="btn btn-wa btn-block" name="action" value="whatsapp"<?= waDigits($doc['cust_phone']) ? '' : ' disabled' ?>><i class="fa-brands fa-whatsapp"></i> Send on WhatsApp</button>
      <a class="btn btn-ghost btn-block" target="_blank" href="<?= e(docUrl($doc)) ?>"><i class="fa-solid fa-file-pdf"></i> Open PDF view</a>
      <?php if ($doc['status'] === 'draft'): ?><button class="btn btn-ghost btn-block" name="action" value="mark_sent">Mark as sent (sent another way)</button><?php endif; ?>
      <?php if ($isQuote && in_array($doc['status'], ['draft', 'sent'], true)): ?>
        <button class="btn btn-ghost btn-block" name="action" value="accept">Customer said yes</button>
        <button class="btn btn-ghost btn-block" name="action" value="decline" onclick="return confirm('Mark this quote as declined?')">Customer said no</button>
      <?php endif; ?>
      <?php if ($isQuote && !in_array($doc['status'], ['declined', 'cancelled'], true)): ?>
        <?php if ($existingInvoice): ?><a class="btn btn-dark btn-block" href="document.php?id=<?= (int) $existingInvoice['id'] ?>">Invoice <?= e($existingInvoice['doc_number']) ?> →</a>
        <?php else: ?><button class="btn btn-dark btn-block" name="action" value="convert"><i class="fa-solid fa-file-invoice-dollar"></i> Convert to invoice</button><?php endif; ?>
      <?php endif; ?>
      <?php if (!in_array($doc['status'], ['cancelled', 'paid'], true)): ?><button class="btn btn-ghost btn-block" name="action" value="cancel" onclick="return confirm('Cancel this document? The number stays on record.')">Cancel document</button><?php endif; ?>
      <?php if ($doc['status'] === 'draft'): ?><button class="btn btn-danger btn-block" name="action" value="delete" onclick="return confirm('Delete this draft?')">Delete draft</button><?php endif; ?>
    </form>
  </section>

  <?php if (!$isQuote): ?>
  <section class="card glass">
    <h2>Payments</h2>
    <?php if ($payments): ?><table class="tbl" style="margin-bottom:12px"><tbody><?php foreach ($payments as $p): ?><tr>
      <td><?= e(date('j M', strtotime((string) $p['paid_on']))) ?><small><?= e(PAY_METHODS[$p['method']] ?? $p['method']) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?></small></td>
      <td class="num"><?= e(rands($p['amount'])) ?></td>
      <td><form method="post"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $doc['id'] ?>"><input type="hidden" name="action" value="delete_payment"><input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>"><button class="icon-btn" style="width:36px;height:36px" aria-label="Remove payment" onclick="return confirm('Remove this payment?')"><i class="fa-solid fa-xmark"></i></button></form></td>
    </tr><?php endforeach; ?></tbody></table><?php endif; ?>
    <?php if ($doc['status'] !== 'cancelled' && $balance > 0.004): ?>
    <form method="post" class="stack"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $doc['id'] ?>"><input type="hidden" name="action" value="payment">
      <div class="field"><label for="amount">Amount received (R)</label><input id="amount" name="amount" inputmode="decimal" required value="<?= e(number_format($balance, 2, '.', '')) ?>"></div>
      <div class="row2"><div class="field"><label for="method">Method</label><select id="method" name="method"><?php foreach (PAY_METHODS as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="paid_on">Date</label><input id="paid_on" name="paid_on" type="date" value="<?= date('Y-m-d') ?>"></div></div>
      <div class="field"><label for="reference">Reference (optional)</label><input id="reference" name="reference" maxlength="120"></div>
      <button class="btn btn-dark btn-block">Record payment</button>
    </form>
    <?php elseif ($balance <= 0.004 && (float) $doc['total'] > 0): ?><p class="ok-box">Fully paid.</p><?php endif; ?>
  </section>
  <?php endif; ?>

  <section class="card glass">
    <h2>History</h2>
    <ul class="timeline"><?php foreach ($events as $ev): [$lab, $ico] = DOC_EVENT_LABELS[$ev['kind']] ?? [$ev['kind'], 'fa-circle']; ?>
      <li><i class="fa-solid <?= e($ico) ?>"></i><span><b><?= e($lab) ?></b><?= $ev['detail'] ? '<small>' . e($ev['detail']) . '</small>' : '' ?><small><?= e(date('j M Y H:i', strtotime($ev['created_at']))) ?></small></span></li><?php endforeach; ?></ul>
  </section>
</aside>
<?php endif; ?>
</div>

<script>
(() => {
  const money = n => 'R ' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  const num = v => { const n = parseFloat(String(v).replace(/[ R]/gi, '').replace(',', '.')); return isNaN(n) ? 0 : n; };
  const box = document.getElementById('items'), f = document.getElementById('docform');
  const calc = () => {
    let sub = 0;
    box.querySelectorAll('.item-row').forEach(r => {
      const t = num(r.querySelector('[name="item_qty[]"]').value || 1) * num(r.querySelector('[name="item_price[]"]').value);
      r.querySelector('.line-total').textContent = money(t); sub += Math.round(t * 100) / 100;
    });
    const disc = Math.min(num(f.discount.value), sub), vat = Math.round((sub - disc) * num(f.vat_rate.value)) / 100;
    document.getElementById('t-sub').textContent = money(sub);
    document.getElementById('t-disc').textContent = disc ? '− ' + money(disc) : money(0);
    document.getElementById('t-vat').textContent = money(vat);
    document.getElementById('t-total').textContent = money(sub - disc + vat);
  };
  const rowHtml = (desc = '', price = '', pid = 0) => {
    const d = document.createElement('div'); d.className = 'item-row';
    d.innerHTML = `<input type="hidden" name="item_product[]" value="${pid}"><textarea name="item_desc[]" rows="2" maxlength="500" aria-label="Description"></textarea><input name="item_qty[]" inputmode="decimal" value="1" aria-label="Quantity"><input name="item_price[]" inputmode="decimal" placeholder="0.00" aria-label="Unit price"><output class="line-total">R 0.00</output><button type="button" class="icon-btn" data-remove aria-label="Remove line"><i class="fa-solid fa-trash"></i></button>`;
    d.querySelector('textarea').value = desc; d.querySelector('[name="item_price[]"]').value = price; return d;
  };
  document.getElementById('add-row').onclick = () => { box.appendChild(rowHtml()); calc(); };
  document.getElementById('add-product')?.addEventListener('change', e => {
    const o = e.target.selectedOptions[0]; if (!o.value) return;
    const first = box.querySelector('.item-row'), blank = box.querySelectorAll('.item-row').length === 1 && !first.querySelector('textarea').value.trim();
    const row = rowHtml(o.dataset.t, parseFloat(o.dataset.p) > 0 ? o.dataset.p : '', o.value);
    if (blank) first.replaceWith(row); else box.appendChild(row);
    e.target.value = ''; calc();
  });
  box.addEventListener('click', e => { if (e.target.closest('[data-remove]') && box.querySelectorAll('.item-row').length > 1) { e.target.closest('.item-row').remove(); calc(); } });
  f.addEventListener('input', calc); calc();
  document.getElementById('pick')?.addEventListener('change', e => {
    const o = e.target.selectedOptions[0], c = o.dataset.c ? JSON.parse(o.dataset.c) : null;
    document.getElementById('customer_id').value = c ? c.id : 0;
    if (!c) return;
    f.cust_name.value = c.name || ''; f.cust_company.value = c.company || ''; f.cust_phone.value = c.phone || ''; f.cust_email.value = c.email || ''; f.cust_address.value = c.address || ''; f.cust_vat_no.value = c.vat_no || '';
  });
  <?php if ($locked): ?>f.querySelectorAll('input,textarea,select,button').forEach(x => { if (x.type !== 'hidden') x.disabled = true; });<?php endif; ?>
})();
</script>
<?php adminFoot();
