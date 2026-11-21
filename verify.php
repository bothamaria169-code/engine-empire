<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

$ok = verifyEmail((int) ($_GET['u'] ?? 0), (string) ($_GET['t'] ?? ''));
pageHead('Confirm your email — ' . APP_NAME, 'Email confirmation.', ['noindex' => true]);
siteHeader();
?>
<main id="main" class="page">
  <div class="auth-card glass" style="margin:24px auto">
    <?php if ($ok): ?>
      <h1 style="font-size:1.6rem">Email confirmed</h1>
      <p>Thank you. Your quotes and invoices now show in your account.</p>
      <a class="btn btn-primary" href="<?= e(APP_URL) ?>/account.php">Go to my account</a>
    <?php else: ?>
      <h1 style="font-size:1.6rem">That link has expired</h1>
      <p>Sign in and press <b>Send the confirmation email again</b> on your account page.</p>
      <a class="btn btn-primary" href="<?= e(APP_URL) ?>/login.php">Sign in</a>
    <?php endif; ?>
  </div>
</main>
<?php
siteFooter();
pageFoot();
