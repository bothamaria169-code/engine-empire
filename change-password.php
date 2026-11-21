<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

$me = currentUser();
if (!$me) redirect('login.php');
if (empty($me['must_change_password'])) redirect(homeFor($me));

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $pw = (string) ($_POST['password'] ?? '');
    if (!password_verify((string) ($_POST['current_password'] ?? ''), (string) $me['password_hash'])) $error = 'The one-time password is not right.';
    elseif (strlen($pw) < 10) $error = 'Use at least 10 characters.';
    elseif ($pw !== ($_POST['pw2'] ?? '')) $error = 'The two new passwords do not match.';
    elseif (password_verify($pw, (string) $me['password_hash'])) $error = 'Choose a different password from the one-time one.';
    else {
        getDB()->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $me['id']]);
        audit('password_set_after_one_time', $me['email']);
        flash('ok', 'Your new password is saved. Welcome!');
        redirect(isStaff($me) ? 'admin/' : 'account.php');
    }
}
pageHead('Choose your password — ' . APP_NAME, 'Choose a new password.', ['noindex' => true]);
siteHeader();
?>
<main id="main" class="page">
  <form class="auth-card glass" method="post" style="margin:24px auto">
    <h1 style="font-size:1.6rem">Choose your own password</h1>
    <p class="sub" style="color:var(--text-2)">You signed in with a one-time password. It cannot be used again once you set your own.</p>
    <?php if ($error): ?><p class="alert alert-err" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?= csrfField() ?>
    <div class="field"><label for="cp">One-time password</label><input id="cp" name="current_password" type="password" required autocomplete="current-password"></div>
    <div class="field"><label for="np">New password (10+ characters)</label><input id="np" name="password" type="password" required minlength="10" autocomplete="new-password"></div>
    <div class="field"><label for="np2">Repeat new password</label><input id="np2" name="pw2" type="password" required minlength="10" autocomplete="new-password"></div>
    <button class="btn btn-primary btn-block" type="submit">Save and continue</button>
    <p class="fine"><a href="<?= e(APP_URL) ?>/logout.php">Sign out</a></p>
  </form>
</main>
<?php
siteFooter();
pageFoot();
