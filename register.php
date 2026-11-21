<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

if ($u = currentUser()) redirect(homeFor($u));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    if (!rateLimit('register', 5, 3600)) $error = 'Too many sign-ups from this connection. Please try again later.';
    elseif (empty($_POST['consent'])) $error = 'Please tick the box to agree to the privacy policy.';
    elseif (($_POST['pw2'] ?? '') !== ($_POST['password'] ?? '')) $error = 'The two passwords do not match.';
    else {
        $id = null;
        $error = registerCustomer(postText('name', 120), postText('email', 255), postText('phone', 40), (string) ($_POST['password'] ?? ''), $id);
        if ($error === '' && $id) {
            session_regenerate_id(true);
            $_SESSION['uid'] = $id;
            flash('ok', 'Account created. We emailed you a link to confirm your email address.');
            redirect('account.php');
        }
    }
}
pageHead('Create an account — ' . APP_NAME, 'Create an account to see your quotes and invoices online.', ['canonical' => APP_URL . '/register.php']);
siteHeader();
?>
<main id="main" class="page">
  <form class="auth-card glass" method="post" style="margin:24px auto;width:min(480px,100%)">
    <h1 style="font-size:1.6rem">Create your account</h1>
    <p class="sub" style="color:var(--text-2)">Keep all your quotes and invoices in one place.</p>
    <?php if ($error): ?><p class="alert alert-err" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?= csrfField() ?>
    <div class="field"><label for="name">Full name</label><input id="name" name="name" required autocomplete="name" value="<?= e(postText('name', 120)) ?>"></div>
    <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required autocomplete="email" value="<?= e(postText('email')) ?>"><small>Use the email address we send your quotes to.</small></div>
    <div class="field"><label for="phone">Cell number (optional)</label><input id="phone" name="phone" type="tel" autocomplete="tel" value="<?= e(postText('phone', 40)) ?>"></div>
    <div class="row2">
      <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
      <div class="field"><label for="pw2">Repeat password</label><input id="pw2" name="pw2" type="password" required minlength="8" autocomplete="new-password"></div>
    </div>
    <label class="check"><input type="checkbox" name="consent" value="1" required> <span>I agree to the <a href="<?= e(APP_URL) ?>/privacy.php" target="_blank">privacy policy</a> and <a href="<?= e(APP_URL) ?>/terms.php" target="_blank">terms</a>.</span></label>
    <button class="btn btn-primary btn-block" type="submit">Create account</button>
    <p class="fine">Already registered? <a href="<?= e(APP_URL) ?>/login.php">Sign in</a></p>
  </form>
</main>
<?php
siteFooter();
pageFoot();
