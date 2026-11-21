<?php
declare(strict_types=1);

require __DIR__ . '/_shell.php';

$db = getDB();
$ROLES = ['admin' => 'Admin (full access)', 'staff' => 'Staff (admin area)', 'customer' => 'Customer (own documents only)'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $uid = (int) ($_POST['uid'] ?? 0);
    $admins = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
    $t = null;
    if ($uid) { $s = $db->prepare('SELECT * FROM users WHERE id = ?'); $s->execute([$uid]); $t = $s->fetch() ?: null; }

    if ($action === 'add') {
        $email = strtolower(postText('email', 255)); $name = postText('name', 120); $pw = (string) ($_POST['password'] ?? '');
        $role = isset($ROLES[postText('role', 10)]) ? postText('role', 10) : 'staff';
        $dup = $db->prepare('SELECT COUNT(*) FROM users WHERE email = ?'); $dup->execute([$email]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') flash('err', 'Enter a name and a valid email.');
        elseif (strlen($pw) < 10) flash('err', 'Use a password of at least 10 characters.');
        elseif ((int) $dup->fetchColumn() > 0) flash('err', 'There is already a user with that email.');
        else {
            $db->prepare('INSERT INTO users (email, full_name, password_hash, role, email_verified_at) VALUES (?, ?, ?, ?, NOW())')->execute([$email, $name, password_hash($pw, PASSWORD_DEFAULT), $role]);
            if ($role === 'customer') claimDocuments((int) $db->lastInsertId(), $email);
            audit('user_add', "{$email} ({$role})");
            flash('ok', "User {$email} created. Tell them the password in person or on WhatsApp, and ask them to change it.");
        }
    } elseif ($t) {
        $self = (int) $t['id'] === (int) $ADMIN['id'];
        if ($action === 'role') {
            $role = postText('role', 10);
            if (!isset($ROLES[$role])) flash('err', 'Unknown role.');
            elseif ($t['role'] === 'admin' && $role !== 'admin' && $admins <= 1) flash('err', 'There must always be at least one active admin.');
            else {
                $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $uid]);
                audit('user_role', "{$t['email']} → {$role}");
                flash('ok', 'Role changed.');
            }
        } elseif ($action === 'toggle') {
            if ($self) flash('err', 'You cannot deactivate yourself.');
            elseif ($t['role'] === 'admin' && $t['is_active'] && $admins <= 1) flash('err', 'There must always be at least one active admin.');
            else { $db->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$uid]); audit('user_toggle', $t['email']); flash('ok', 'Saved.'); }
        } elseif ($action === 'verify') {
            $db->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()), verify_hash = NULL WHERE id = ?')->execute([$uid]);
            claimDocuments($uid, $t['email']);
            audit('user_verify', $t['email']);
            flash('ok', 'Email marked as confirmed. Their documents now show in their account.');
        } elseif ($action === 'reset') {
            startPasswordReset($t['email']);
            flash('ok', 'A password-reset link was emailed to ' . $t['email'] . '.');
        } elseif ($action === 'setpw') {
            $pw = (string) ($_POST['password'] ?? '');
            if (strlen($pw) < 10) flash('err', 'Use at least 10 characters.');
            else { $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $uid]); audit('user_setpw', $t['email']); flash('ok', 'New password set.'); }
        } elseif ($action === 'delete') {
            if ($self) flash('err', 'You cannot delete yourself.');
            elseif ($t['role'] === 'admin' && $admins <= 1) flash('err', 'There must always be at least one active admin.');
            else {
                $db->prepare('UPDATE documents SET user_id = NULL WHERE user_id = ?')->execute([$uid]);
                $db->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
                audit('user_delete', $t['email']);
                flash('ok', 'User deleted. Their quotes and invoices stay on record.');
            }
        }
    }
    redirect('admin/users.php' . (!empty($_GET['role']) ? '?role=' . rawurlencode((string) $_GET['role']) : ''));
}

$filter = isset($ROLES[$_GET['role'] ?? '']) ? (string) $_GET['role'] : '';
$s = $db->prepare("SELECT u.*, (SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id) AS docs FROM users u " . ($filter ? 'WHERE u.role = ? ' : '') . 'ORDER BY FIELD(u.role, \'admin\', \'staff\', \'customer\'), u.id DESC LIMIT 300');
$s->execute($filter ? [$filter] : []);
$rows = $s->fetchAll();
$tabs = '<a class="btn btn-sm ' . ($filter === '' ? 'btn-dark' : 'btn-ghost') . '" href="users.php">All</a>';
foreach (['admin' => 'Admins', 'staff' => 'Staff', 'customer' => 'Customers'] as $k => $l) $tabs .= '<a class="btn btn-sm ' . ($filter === $k ? 'btn-dark' : 'btn-ghost') . '" href="users.php?role=' . $k . '">' . $l . '</a>';
adminHead('Users & logins', 'users.php', $tabs);
?>
<section class="card glass">
  <h2>Add a user</h2>
  <form method="post" class="fgrid"><?= csrfField() ?><input type="hidden" name="action" value="add">
    <div class="field"><label for="n">Name</label><input id="n" name="name" required maxlength="120"></div>
    <div class="field"><label for="e">Email (their login)</label><input id="e" name="email" type="email" required></div>
    <div class="field"><label for="r">Role</label><select id="r" name="role"><?php foreach ($ROLES as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="p">Temporary password (10+ characters)</label><input id="p" name="password" required minlength="10" autocomplete="new-password"></div>
    <div class="field wide"><button class="btn btn-primary">Create user</button></div>
  </form>
</section>

<section class="card glass">
  <h2>Everyone with a login</h2>
  <p style="color:var(--text-2);margin-bottom:14px">Customers can register themselves at <a href="<?= e(APP_URL) ?>/register.php" target="_blank"><?= e(APP_URL) ?>/register.php</a>. Each customer sees only their own quotes and invoices.</p>
<table class="tbl"><thead><tr><th>User</th><th>Role</th><th>Status</th><th>Docs</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($rows as $r): $self = (int) $r['id'] === (int) $ADMIN['id']; ?>
<tr>
  <td data-label="User"><span><b><?= e($r['full_name'] ?: '—') ?></b><?= $self ? ' (you)' : '' ?><small><?= e($r['email']) ?></small><small><?= $r['last_login_at'] ? 'Last in ' . e(date('j M Y', strtotime($r['last_login_at']))) : 'Never signed in' ?></small></span></td>
  <td data-label="Role"><form method="post" class="inline-form"><?= csrfField() ?><input type="hidden" name="action" value="role"><input type="hidden" name="uid" value="<?= (int) $r['id'] ?>">
    <select name="role" aria-label="Role"><?php foreach ($ROLES as $k => $l): ?><option value="<?= $k ?>"<?= $k === $r['role'] ? ' selected' : '' ?>><?= e(explode(' (', $l)[0]) ?></option><?php endforeach; ?></select><button class="btn btn-ghost btn-sm">Set</button></form></td>
  <td data-label="Status"><?= pill($r['is_active'] ? 'Active' : 'Disabled', $r['is_active'] ? 'ok' : 'bad') ?> <?= $r['email_verified_at'] ? '' : pill('Email not confirmed', 'warn') ?></td>
  <td data-label="Docs"><?= (int) $r['docs'] ?></td>
  <td data-label="Actions"><form method="post" class="inline-form"><?= csrfField() ?><input type="hidden" name="uid" value="<?= (int) $r['id'] ?>">
    <?php if (!$r['email_verified_at']): ?><button class="btn btn-ghost btn-sm" name="action" value="verify">Confirm email</button><?php endif; ?>
    <button class="btn btn-ghost btn-sm" name="action" value="reset">Email reset link</button>
    <?php if (!$self): ?><button class="btn btn-ghost btn-sm" name="action" value="toggle"><?= $r['is_active'] ? 'Disable' : 'Enable' ?></button>
    <button class="btn btn-danger btn-sm" name="action" value="delete" onclick="return confirm('Delete this user? Their quotes and invoices stay on record.')">Delete</button><?php endif; ?></form>
    <form method="post" class="inline-form" style="margin-top:6px"><?= csrfField() ?><input type="hidden" name="action" value="setpw"><input type="hidden" name="uid" value="<?= (int) $r['id'] ?>">
      <input name="password" type="text" placeholder="Set new password" aria-label="New password" minlength="10" size="16" autocomplete="off"><button class="btn btn-ghost btn-sm">Set</button></form></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</section>
<?php adminFoot();
