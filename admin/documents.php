<?php
declare(strict_types=1);

require __DIR__ . '/_shell.php';

$db = getDB();
$type = in_array($_GET['type'] ?? '', ['quote', 'invoice'], true) ? (string) $_GET['type'] : '';
$status = isset(DOC_STATUSES[$_GET['status'] ?? '']) ? (string) $_GET['status'] : '';
$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);

$where = ['1=1']; $args = [];
if ($type) { $where[] = 'doc_type = ?'; $args[] = $type; }
if ($status) { $where[] = 'status = ?'; $args[] = $status; }
if ($q !== '') { $where[] = "CONCAT_WS(' ', doc_number, cust_name, cust_company, cust_phone) LIKE ?"; $args[] = '%' . $q . '%'; }
$s = $db->prepare('SELECT * FROM documents WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 300');
$s->execute($args);
$rows = $s->fetchAll();

$one = fn(string $sql) => (float) $db->query($sql)->fetchColumn();
$outstanding = $one("SELECT COALESCE(SUM(total - amount_paid),0) FROM documents WHERE doc_type='invoice' AND status IN ('sent','part_paid')");
$overdue = $one("SELECT COALESCE(SUM(total - amount_paid),0) FROM documents WHERE doc_type='invoice' AND status IN ('sent','part_paid') AND due_date < CURDATE()");
$paidMonth = $one("SELECT COALESCE(SUM(amount),0) FROM payments WHERE paid_on >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$openQuotes = (int) $one("SELECT COUNT(*) FROM documents WHERE doc_type='quote' AND status IN ('draft','sent')");
$openQuotesValue = $one("SELECT COALESCE(SUM(total),0) FROM documents WHERE doc_type='quote' AND status IN ('draft','sent')");

$tab = fn(string $label, string $t) => '<a class="btn btn-sm ' . ($type === $t ? 'btn-dark' : 'btn-ghost') . '" href="documents.php' . ($t ? '?type=' . $t : '') . '">' . $label . '</a>';
adminHead('Quotes & invoices', 'documents.php',
    $tab('All', '') . $tab('Quotes', 'quote') . $tab('Invoices', 'invoice') .
    '<a class="btn btn-primary btn-sm" href="document.php?new=quote"><i class="fa-solid fa-plus"></i> New quote</a>' .
    '<a class="btn btn-ghost btn-sm" href="document.php?new=invoice"><i class="fa-solid fa-plus"></i> New invoice</a>');
?>
<div class="kpis">
  <div class="kpi glass"><span>Open quotes</span><strong><?= $openQuotes ?></strong><small><?= e(rands($openQuotesValue)) ?> waiting for a yes</small></div>
  <div class="kpi glass"><span>Outstanding</span><strong><?= e(rands($outstanding)) ?></strong><small>unpaid invoices</small></div>
  <div class="kpi glass"><span>Overdue</span><strong style="color:<?= $overdue > 0 ? 'var(--coral)' : 'inherit' ?>"><?= e(rands($overdue)) ?></strong><small>past the due date</small></div>
  <div class="kpi glass"><span>Paid this month</span><strong><?= e(rands($paidMonth)) ?></strong><small>payments received</small></div>
</div>

<section class="card glass">
  <form class="inline-form" method="get" style="margin-bottom:16px">
    <?php if ($type): ?><input type="hidden" name="type" value="<?= e($type) ?>"><?php endif; ?>
    <input name="q" value="<?= e($q) ?>" placeholder="Search number, customer, phone" aria-label="Search" size="28">
    <select name="status" aria-label="Status"><option value="">Any status</option><?php foreach (DOC_STATUSES as $k => $l): ?><option value="<?= $k ?>"<?= $k === $status ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <button class="btn btn-ghost btn-sm">Filter</button>
  </form>
<?php if (!$rows): ?><p>Nothing here yet. Click <b>New quote</b>, or open an enquiry and press <b>Create quote</b>.</p><?php else: ?>
<table class="tbl"><thead><tr><th>Number</th><th>Customer</th><th>Date</th><th class="num">Total</th><th class="num">Balance</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): $bal = (float) $r['total'] - (float) $r['amount_paid']; ?>
<tr>
  <td data-label="Number"><span><a href="document.php?id=<?= (int) $r['id'] ?>"><b><?= e($r['doc_number']) ?></b></a><small><?= e(DOC_TYPES[$r['doc_type']]) ?></small></span></td>
  <td data-label="Customer"><span><b><?= e($r['cust_name']) ?></b><small><?= e(trim(($r['cust_company'] ?? '') . ' ' . ($r['cust_phone'] ?? ''))) ?></small></span></td>
  <td data-label="Date"><span><?= e(date('j M Y', strtotime((string) $r['issue_date']))) ?><?php if ($r['due_date']): ?><small><?= $r['doc_type'] === 'quote' ? 'Valid to ' : 'Due ' ?><?= e(date('j M', strtotime((string) $r['due_date']))) ?></small><?php endif; ?></span></td>
  <td data-label="Total" class="num"><?= e(rands($r['total'])) ?></td>
  <td data-label="Balance" class="num"><?= $r['doc_type'] === 'invoice' ? e(rands($bal)) : '—' ?></td>
  <td data-label="Status"><?= pill(docStatusLabel($r), docTone($r)) ?><?php if ($r['viewed_at']): ?><small title="Opened by the customer"><i class="fa-solid fa-eye"></i> <?= (int) $r['view_count'] ?></small><?php endif; ?></td>
  <td><a class="btn btn-ghost btn-sm" href="document.php?id=<?= (int) $r['id'] ?>">Open</a></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</section>
<?php adminFoot();
