<?php
declare(strict_types=1);

require __DIR__ . '/_shell.php';

$db = getDB();
$one = fn(string $sql) => (float) $db->query($sql)->fetchColumn();
$inStock   = (int) $one("SELECT COUNT(*) FROM products WHERE stock_status = 'available'");
$noPrice   = (int) $one("SELECT COUNT(*) FROM products WHERE stock_status IN ('available','reserved') AND (price IS NULL OR price <= 0)");
$newEnq    = (int) $one("SELECT COUNT(*) FROM enquiries WHERE status = 'new'");
$enq30     = (int) $one('SELECT COUNT(*) FROM enquiries WHERE created_at > NOW() - INTERVAL 30 DAY');
$openQuotes = (int) $one("SELECT COUNT(*) FROM documents WHERE doc_type='quote' AND status IN ('draft','sent')");
$outstanding = $one("SELECT COALESCE(SUM(total - amount_paid),0) FROM documents WHERE doc_type='invoice' AND status IN ('sent','part_paid')");
$overdueN  = (int) $one("SELECT COUNT(*) FROM documents WHERE doc_type='invoice' AND status IN ('sent','part_paid') AND due_date < CURDATE()");
$paidMonth = $one("SELECT COALESCE(SUM(amount),0) FROM payments WHERE paid_on >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");

$days = [];
for ($i = 29; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-{$i} day"))] = 0;
foreach ($db->query('SELECT DATE(created_at) d, COUNT(*) n FROM enquiries WHERE created_at > CURDATE() - INTERVAL 30 DAY GROUP BY d') as $r) $days[$r['d']] = (int) $r['n'];
$max = max(1, ...array_values($days));

$recent = $db->query('SELECT e.*, p.title FROM enquiries e LEFT JOIN products p ON p.id = e.product_id ORDER BY e.id DESC LIMIT 6')->fetchAll();
$docs = $db->query('SELECT * FROM documents ORDER BY id DESC LIMIT 6')->fetchAll();

adminHead('Dashboard', 'index.php', '<a class="btn btn-primary btn-sm" href="document.php?new=quote"><i class="fa-solid fa-plus"></i> New quote</a><a class="btn btn-ghost btn-sm" href="products.php?new=1"><i class="fa-solid fa-plus"></i> Add a product</a>');
?>
<div class="kpis">
  <div class="kpi glass"><span>New enquiries</span><strong><?= $newEnq ?></strong><small><?= $enq30 ?> in the last 30 days</small></div>
  <div class="kpi glass"><span>Open quotes</span><strong><?= $openQuotes ?></strong><small>waiting to be sent or accepted</small></div>
  <div class="kpi glass"><span>Outstanding</span><strong><?= e(rands($outstanding)) ?></strong><small><?= $overdueN ?> overdue invoice<?= $overdueN === 1 ? '' : 's' ?></small></div>
  <div class="kpi glass"><span>Paid this month</span><strong><?= e(rands($paidMonth)) ?></strong><small><?= $inStock ?> units in stock</small></div>
</div>

<?php if ($overdueN): ?><p class="alert alert-err"><?= $overdueN ?> invoice<?= $overdueN === 1 ? ' is' : 's are' ?> overdue. <a href="documents.php?type=invoice&amp;status=sent">Review them</a>.</p><?php endif; ?>
<?php if ($noPrice): ?><p class="alert alert-err"><?= $noPrice ?> product<?= $noPrice === 1 ? ' has' : 's have' ?> no price yet and show "Get a quote". <a href="products.php">Add prices</a>.</p><?php endif; ?>
<?php if (get_setting('business_address') === '' || get_setting('bank_account_no') === ''): ?><p class="alert alert-err">Your business address and banking details are missing, so quotes and invoices print without them. <a href="settings.php">Complete Settings → Business</a>.</p><?php endif; ?>
<?php if (whatsappLink() === ''): ?><p class="alert alert-err">No WhatsApp number is set, so the WhatsApp buttons are hidden. <a href="settings.php">Add it in Settings</a>.</p><?php endif; ?>

<section class="card glass">
  <h2>Enquiries per day, last 30 days</h2>
  <div class="bars" role="img" aria-label="Enquiries per day for the last 30 days"><?php foreach ($days as $d => $n): ?><i style="height:<?= round($n / $max * 100) ?>%" title="<?= e(date('j M', strtotime($d))) ?>: <?= $n ?>"></i><?php endforeach; ?></div>
</section>

<section class="card glass">
  <h2>Latest enquiries</h2>
  <?php if (!$recent): ?><p>No enquiries yet. They arrive from the website's quote forms.</p><?php else: ?>
  <table class="tbl"><thead><tr><th>When</th><th>Customer</th><th>Wants</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($recent as $r): ?><tr>
      <td data-label="When"><?= e(date('j M H:i', strtotime($r['created_at']))) ?></td>
      <td data-label="Customer"><?= e($r['customer_name']) ?> · <?= e($r['phone']) ?></td>
      <td data-label="Wants"><?= e($r['title'] ?? ($r['vehicle'] ?: 'General enquiry')) ?></td>
      <td data-label="Status"><?= pill(ENQUIRY_STATUSES[$r['status']], $r['status'] === 'new' ? 'warn' : 'info') ?></td>
      <td><a class="btn btn-ghost btn-sm" href="document.php?new=quote&amp;enquiry=<?= (int) $r['id'] ?>">Create quote</a></td>
    </tr><?php endforeach; ?>
  </tbody></table>
  <p style="margin-top:12px"><a href="enquiries.php">All enquiries →</a></p>
  <?php endif; ?>
</section>

<section class="card glass">
  <h2>Latest quotes &amp; invoices</h2>
  <?php if (!$docs): ?><p>None yet. Create your first quote from an enquiry or with <b>New quote</b>.</p><?php else: ?>
  <table class="tbl"><thead><tr><th>Number</th><th>Customer</th><th class="num">Total</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($docs as $r): ?><tr>
      <td data-label="Number"><a href="document.php?id=<?= (int) $r['id'] ?>"><b><?= e($r['doc_number']) ?></b></a></td>
      <td data-label="Customer"><?= e($r['cust_name']) ?></td>
      <td data-label="Total" class="num"><?= e(rands($r['total'])) ?></td>
      <td data-label="Status"><?= pill(docStatusLabel($r), docTone($r)) ?></td>
    </tr><?php endforeach; ?>
  </tbody></table>
  <p style="margin-top:12px"><a href="documents.php">All quotes &amp; invoices →</a></p>
  <?php endif; ?>
</section>
<?php adminFoot();
