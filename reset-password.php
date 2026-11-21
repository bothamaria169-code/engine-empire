<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

$uid = (int) ($_GET['u'] ?? $_POST['u'] ?? 0);
$tok = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$valid = resetTokenValid($uid, $tok);
$error = '';
if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $pw = (string) ($_POST['password'] ?? '');
    if (strlen($pw) < 8) $error = 'Use a password of at least 8 characters.';
    elseif ($pw !== ($_POST['pw2'] ?? '')) $error = 'The two passwords do not match.';
    else {
        finishPasswordReset($uid, $pw);
        audit('password_reset', "#{$uid}");
        flash('ok', 'Password changed. You can sign in now.');
        redirect('login.php');
    }
}
pageHead('Choose a new password — ' . APP_NAME, 'Choose a new password.', ['noindex' => true]);
siteHeader();
?>
<main id="main" class="page">
  <form class="auth-card glass" method="post" style="margin:24px auto">
    <h1 style="font-size:1.6rem">Choose a new password</h1>
    <?php if (!$valid): ?>
      <p class="alert alert-err">This link has expired or was already used.</p>
      <a class="btn btn-primary" href="<?= e(APP_URL) ?>/forgot-password.php">Send a new link</a>
    <?php else: ?>
      <?php if ($error): ?><p class="alert alert-err" role="alert"><?= e($error) ?></p><?php endif; ?>
      <?= csrfField() ?><input type="hidden" name="u" value="<?= $uid ?>"><input type="hidden" name="t" value="<?= e($tok) ?>">
      <div class="field"><label for="password">New password</label><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
      <div class="field"><label for="pw2">Repeat it</label><input id="pw2" name="pw2" type="password" required minlength="8" autocomplete="new-password"></div>
      <button class="btn btn-primary btn-block" type="submit">Save password</button>
    <?php endif; ?>
  </form>
</main>
<?php
siteFooter();
pageFoot();
