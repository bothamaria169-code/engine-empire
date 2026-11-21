<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/documents.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

$me = requireUser();
$db = getDB();
$verified = (bool) $me['email_verified_at'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'profile') {
        $db->prepare('UPDATE users SET full_name = ?, phone = ? WHERE id = ?')->execute([postText('name', 120) ?: $me['full_name'], postText('phone', 40) ?: null, $me['id']]);
        flash('ok', 'Details saved.');
    } elseif ($action === 'password') {
        $pw = (string) ($_POST['new_password'] ?? '');
        if (strlen($pw) < 8) flash('err', 'Use at least 8 characters.');
        elseif (!password_verify((string) ($_POST['current_password'] ?? ''), (string) $me['password_hash'])) flash('err', 'Your current password is not right.');
        else { $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $me['id']]); flash('ok', 'Password changed.'); }
    } elseif ($action === 'resend' && !$verified) {
        if (rateLimit('resend', 3, 3600)) { resendVerification($me); flash('ok', 'We sent the confirmation email again.'); }
        else flash('err', 'Please wait a while before asking again.');
    } elseif ($action === 'export') {
        $docs = $db->prepare('SELECT doc_number, doc_type, status, issue_date, due_date, total, amount_paid, created_at FROM documents WHERE user_id = ? AND status <> \'draft\' ORDER BY id');
        $docs->execute([$me['id']]);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="my-data.json"');
        echo json_encode(['account' => ['name' => $me['full_name'], 'email' => $me['email'], 'phone' => $me['phone'], 'created_at' => $me['created_at']], 'documents' => $docs->fetchAll()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    } elseif ($action === 'delete') {
        if (!password_verify((string) ($_POST['current_password'] ?? ''), (string) $me['password_hash'])) {
            flash('err', 'Type your password to delete your account.');
        } elseif (isStaff($me)) {
            flash('err', 'Staff accounts are removed by an administrator.');
        } else {
            // Quotes and invoices stay on record for our books; the login and personal profile are removed.
            $db->prepare('UPDATE documents SET user_id = NULL WHERE user_id = ?')->execute([$me['id']]);
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$me['id']]);
            audit('account_delete', $me['email']);
            $_SESSION = [];
            session_destroy();
            startAppSession();
            flash('ok', 'Your account was deleted.');
            redirect('login.php');
        }
    }
    redirect('account.php');
}

$docs = $verified ? customerDocuments((int) $me['id']) : [];
$openQuotes = count(array_filter($docs, fn($d) => $d['doc_type'] === 'quote' && $d['status'] === 'sent'));
$due = array_sum(array_map(fn($d) => $d['doc_type'] === 'invoice' && in_array($d['status'], ['sent', 'part_paid'], true) ? (float) $d['total'] - (float) $d['amount_paid'] : 0, $docs));
$tone = fn(array $d) => docIsOverdue($d) ? 'bad' : (['sent' => 'info', 'accepted' => 'ok', 'declined' => 'bad', 'invoiced' => 'ok', 'part_paid' => 'warn', 'paid' => 'ok', 'cancelled' => 'bad'][$d['status']] ?? '');

pageHead('My account — ' . APP_NAME, 'Your quotes and invoices.', ['noindex' => true, 'canonical' => APP_URL . '/account.php', 'css' => ['assets/css/doc.css']]);
siteHeader('account.php');
?>
<main id="main" class="page">
  <div class="page-head" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between">
    <div><h1>Hello, <?= e(trim(explode(' ', (string) $me['full_name'])[0] ?? '') ?: 'there') ?></h1><p><?= e($me['email']) ?></p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php if (isStaff($me)): ?><a class="btn btn-dark" href="<?= e(APP_URL) ?>/admin/"><i class="fa-solid fa-gauge-high"></i> Admin</a><?php endif; ?>
      <a class="btn btn-primary" href="<?= e(APP_URL) ?>/contact.php"><i class="fa-solid fa-file-invoice"></i> Request a quote</a>
      <a class="btn btn-ghost" href="<?= e(APP_URL) ?>/logout.php">Sign out</a>
    </div>
  </div>

  <?php foreach (takeFlashes() as [$type, $msg]): ?><p class="alert alert-<?= $type === 'ok' ? 'ok' : 'err' ?>" role="status"><?= e($msg) ?></p><?php endforeach; ?>
  <?php if (IS_LOCAL && !empty($_SESSION['dev_link'])): ?><p class="note">Local test: <a href="<?= e($_SESSION['dev_link']) ?>">open the confirmation link</a></p><?php endif; ?>
  <?php if (!$verified): ?>
    <form method="post" class="alert alert-err" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center"><?= csrfField() ?><input type="hidden" name="action" value="resend">
      <span>Please confirm your email address. We sent you a link. Your quotes and invoices show here once it is confirmed.</span>
      <button class="btn btn-ghost btn-sm">Send the confirmation email again</button></form>
  <?php endif; ?>

  <div class="cats" style="margin-bottom:24px">
    <div class="cat glass"><b><?= $openQuotes ?></b><span>quote<?= $openQuotes === 1 ? '' : 's' ?> waiting for your answer</span></div>
    <div class="cat glass"><b><?= e(rands($due)) ?></b><span>due on invoices</span></div>
    <div class="cat glass"><b><?= count($docs) ?></b><span>documents in total</span></div>
  </div>

  <section class="glass" style="border-radius:var(--r-lg);padding:20px;margin-bottom:24px">
    <h2 style="font-size:1.3rem;margin-bottom:12px">My quotes &amp; invoices</h2>
    <?php if (!$docs): ?><p style="color:var(--text-2)">Nothing here yet. When we send you a quote or invoice it shows up here, ready to view, accept and save as PDF.</p>
    <?php else: ?>
    <div style="overflow-x:auto"><table class="doc-items" style="width:100%"><thead><tr><th>Number</th><th>Date</th><th class="num">Total</th><th class="num">Balance</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($docs as $d): $bal = (float) $d['total'] - (float) $d['amount_paid']; ?>
      <tr>
        <td><b><?= e($d['doc_number']) ?></b><br><small style="color:var(--text-3)"><?= e(DOC_TYPES[$d['doc_type']]) ?></small></td>
        <td><?= e(date('j M Y', strtotime((string) $d['issue_date']))) ?></td>
        <td class="num"><?= e(rands($d['total'])) ?></td>
        <td class="num"><?= $d['doc_type'] === 'invoice' ? e(rands($bal)) : '—' ?></td>
        <td><span class="pill <?= e($tone($d)) ?>"><?= e(docIsOverdue($d) ? 'Overdue' : DOC_STATUSES[$d['status']]) ?></span></td>
        <td><a class="btn btn-ghost btn-sm" href="<?= e(docUrl($d)) ?>"><i class="fa-solid fa-file-pdf"></i> View / PDF</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
  </section>

  <div class="split">
    <form method="post" class="form glass" style="border-radius:var(--r-lg)"><?= csrfField() ?><input type="hidden" name="action" value="profile">
      <h3>My details</h3>
      <div class="field"><label for="name">Name</label><input id="name" name="name" value="<?= e($me['full_name']) ?>" required></div>
      <div class="field"><label for="phone">Cell number</label><input id="phone" name="phone" value="<?= e((string) $me['phone']) ?>"></div>
      <button class="btn btn-primary">Save details</button>
    </form>
    <form method="post" class="form glass" style="border-radius:var(--r-lg)"><?= csrfField() ?><input type="hidden" name="action" value="password">
      <h3>Change password</h3>
      <div class="field"><label for="cp">Current password</label><input id="cp" name="current_password" type="password" required autocomplete="current-password"></div>
      <div class="field"><label for="np">New password (8+ characters)</label><input id="np" name="new_password" type="password" required minlength="8" autocomplete="new-password"></div>
      <button class="btn btn-dark">Change password</button>
    </form>
    <?php if (!isStaff($me)): ?>
    <form method="post" class="form glass" style="border-radius:var(--r-lg)"><?= csrfField() ?>
      <h3>My data</h3>
      <p class="sub">Download what we hold about you, or delete your account. Quotes and invoices stay on our books as the law requires, but are no longer linked to a login.</p>
      <button class="btn btn-ghost" name="action" value="export">Download my data</button>
      <div class="field"><label for="dp">Password (to delete)</label><input id="dp" name="current_password" type="password" autocomplete="current-password"></div>
      <button class="btn btn-danger" name="action" value="delete" onclick="return confirm('Delete your account? This cannot be undone.')">Delete my account</button>
    </form>
    <?php endif; ?>
  </div>
</main>
<?php
siteFooter();
pageFoot();
