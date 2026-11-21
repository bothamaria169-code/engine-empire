<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    if (rateLimit('forgot', 5, 3600)) startPasswordReset(postText('email', 255));
    $sent = true;
}
pageHead('Forgot password — ' . APP_NAME, 'Reset your password.', ['noindex' => true]);
siteHeader();
?>
<main id="main" class="page">
  <form class="auth-card glass" method="post" style="margin:24px auto">
    <h1 style="font-size:1.6rem">Forgot your password?</h1>
    <?php if ($sent): ?>
      <p class="ok-box">If that email has an account, we have sent a link to choose a new password. It works for one hour.</p>
      <?php if (IS_LOCAL && !empty($_SESSION['dev_link'])): ?><p class="note">Local test: <a href="<?= e($_SESSION['dev_link']) ?>">open the reset link</a></p><?php endif; ?>
    <?php else: ?>
      <?= csrfField() ?>
      <div class="field"><label for="email">Your email</label><input id="email" name="email" type="email" required autocomplete="username"></div>
      <button class="btn btn-primary btn-block" type="submit">Send reset link</button>
    <?php endif; ?>
    <p class="fine"><a href="<?= e(APP_URL) ?>/login.php">Back to sign in</a></p>
  </form>
</main>
<?php
siteFooter();
pageFoot();
