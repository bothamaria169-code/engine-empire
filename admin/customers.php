<?php
declare(strict_types=1);

require __DIR__ . '/_shell.php';

$db = getDB();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'delete' && $id) {
        $db->prepare('DELETE FROM customers WHERE id = ?')->execute([$id]); // documents keep their own copy of the details
        audit('customer_delete', "#{$id}");
        flash('ok', 'Customer deleted. Their quotes and invoices stay on record.');
        redirect('admin/customers.php');
    }
    $name = postText('name', 160);
    if ($name === '') { flash('err', 'A name is required.'); redirect('admin/customers.php?' . ($id ? "id={$id}" : 'new=1')); }
    $vals = [$name, postText('company', 160) ?: null, postText('phone', 40) ?: null, postText('email', 255) ?: null, postText('address', 400) ?: null, postText('vat_no', 30) ?: null, postText('notes', 2000) ?: null];
    if ($id) $db->prepare('UPDATE customers SET name=?, company=?, phone=?, email=?, address=?, vat_no=?, notes=? WHERE id=?')->execute([...$vals, $id]);
    else { $db->prepare('INSERT INTO customers (name, company, phone, email, address, vat_no, notes) VALUES (?,?,?,?,?,?,?)')->execute($vals); $id = (int) $db->lastInsertId(); }
    audit('customer_save', "#{$id} {$name}");
    flash('ok', 'Customer saved.');
    redirect("admin/customers.php?id={$id}");
}

if ($id || isset($_GET['new'])) {
    $c = [];
    if ($id) { $s = $db->prepare('SELECT * FROM customers WHERE id = ?'); $s->execute([$id]); $c = $s->fetch() ?: []; if (!$c) { flash('err', 'Customer not found.'); redirect('admin/customers.php'); } }
    $v = fn(string $k) => e((string) ($c[$k] ?? ''));
    adminHead($c ? 'Edit customer' : 'New customer', 'customers.php', '<a class="btn btn-ghost btn-sm" href="customers.php">← All customers</a>' . ($c ? '<a class="btn btn-primary btn-sm" href="document.php?new=quote&amp;customer=' . $id . '">New quote</a>' : ''));
    $docs = [];
    if ($c) { $s = $db->prepare('SELECT * FROM documents WHERE customer_id = ? ORDER BY id DESC LIMIT 50'); $s->execute([$id]); $docs = $s->fetchAll(); }
    ?>
<form method="post" class="card glass"><?= csrfField() ?><input type="hidden" name="id" value="<?= $id ?>">
  <div class="fgrid">
    <div class="field"><label for="name">Name</label><input id="name" name="name" required maxlength="160" value="<?= $v('name') ?>"></div>
    <div class="field"><label for="company">Company</label><input id="company" name="company" maxlength="160" value="<?= $v('company') ?>"></div>
    <div class="field"><label for="phone">Cell number</label><input id="phone" name="phone" inputmode="tel" maxlength="40" value="<?= $v('phone') ?>"></div>
    <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" maxlength="255" value="<?= $v('email') ?>"></div>
    <div class="field"><label for="vat_no">VAT no.</label><input id="vat_no" name="vat_no" maxlength="30" value="<?= $v('vat_no') ?>"></div>
    <div class="field wide"><label for="address">Address</label><textarea id="address" name="address" rows="2" maxlength="400"><?= $v('address') ?></textarea></div>
    <div class="field wide"><label for="notes">Private notes</label><textarea id="notes" name="notes" rows="3" maxlength="2000"><?= $v('notes') ?></textarea></div>
  </div>
  <p class="inline-form" style="margin-top:20px"><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save customer</button>
  <?php if ($c): ?><button class="btn btn-danger" name="action" value="delete" onclick="return confirm('Delete this customer? Their quotes and invoices stay on record.')">Delete</button><?php endif; ?></p>
</form>
<?php if ($docs): ?><section class="card glass"><h2>Quotes &amp; invoices</h2><table class="tbl"><tbody><?php foreach ($docs as $r): ?><tr>
  <td data-label="Number"><a href="document.php?id=<?= (int) $r['id'] ?>"><b><?= e($r['doc_number']) ?></b></a></td>
  <td data-label="Date"><?= e(date('j M Y', strtotime((string) $r['issue_date']))) ?></td>
  <td data-label="Total" class="num"><?= e(rands($r['total'])) ?></td>
  <td data-label="Status"><?= pill(docStatusLabel($r), docTone($r)) ?></td></tr><?php endforeach; ?></tbody></table></section><?php endif; ?>
<?php
    adminFoot();
    exit;
}

$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$s = $db->prepare("SELECT c.*, (SELECT COUNT(*) FROM documents d WHERE d.customer_id = c.id) AS docs,
                   (SELECT COALESCE(SUM(d.total - d.amount_paid),0) FROM documents d WHERE d.customer_id = c.id AND d.doc_type='invoice' AND d.status IN ('sent','part_paid')) AS owing
                   FROM customers c " . ($q !== '' ? "WHERE CONCAT_WS(' ', c.name, c.company, c.phone, c.email) LIKE ? " : '') . 'ORDER BY c.name LIMIT 300');
$s->execute($q !== '' ? ['%' . $q . '%'] : []);
$rows = $s->fetchAll();
adminHead('Customers', 'customers.php', '<a class="btn btn-primary btn-sm" href="customers.php?new=1"><i class="fa-solid fa-plus"></i> Add a customer</a>');
?>
<section class="card glass">
  <form class="inline-form" method="get" style="margin-bottom:16px"><input name="q" value="<?= e($q) ?>" placeholder="Search name, company, phone, email" aria-label="Search" size="30"><button class="btn btn-ghost btn-sm">Search</button></form>
<?php if (!$rows): ?><p>No customers yet. They are saved automatically when you make a quote or invoice.</p><?php else: ?>
<table class="tbl"><thead><tr><th>Name</th><th>Contact</th><th class="num">Documents</th><th class="num">Owing</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr>
  <td data-label="Name"><span><a href="customers.php?id=<?= (int) $r['id'] ?>"><b><?= e($r['name']) ?></b></a><?php if ($r['company']): ?><small><?= e($r['company']) ?></small><?php endif; ?></span></td>
  <td data-label="Contact"><span><?= e((string) $r['phone']) ?><small><?= e((string) $r['email']) ?></small></span></td>
  <td data-label="Documents" class="num"><?= (int) $r['docs'] ?></td>
  <td data-label="Owing" class="num"><?= (float) $r['owing'] > 0 ? e(rands($r['owing'])) : '—' ?></td>
  <td><a class="btn btn-ghost btn-sm" href="document.php?new=quote&amp;customer=<?= (int) $r['id'] ?>">New quote</a></td>
</tr><?php endforeach; ?></tbody></table>
<?php endif; ?>
</section>
<?php adminFoot();
