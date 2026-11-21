<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

if ($u = currentUser()) redirect(homeFor($u));
$error = '';
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $error = attemptLogin(postText('email'), (string) ($_POST['password'] ?? ''));
    if ($error === '') {
        $me = currentUser();
        // Only follow a "next" address that stays on this site and inside the area the user may enter.
        $path = parse_url($next, PHP_URL_PATH) ?: '';
        $base = parse_url(APP_URL, PHP_URL_PATH) ?: '';
        $ok = $next !== '' && !str_contains($next, '//') && str_starts_with($path, $base . '/') && (isStaff($me) || !str_contains($path, '/admin/'));
        redirect($ok ? $next : homeFor($me));
    }
}
pageHead('Sign in — ' . APP_NAME, 'Sign in to see your quotes and invoices.', ['noindex' => true, 'canonical' => APP_URL . '/login.php']);
siteHeader();
?>
<main id="main" class="page">
  <form class="auth-card glass" method="post" style="margin:24px auto">
    <h1 style="font-size:1.6rem">Sign in</h1>
    <p class="sub" style="color:var(--text-2)">See your quotes and invoices, accept quotes and download PDFs.</p>
    <?php if ($error): ?><p class="alert alert-err" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php foreach (takeFlashes() as [$type, $msg]): ?><p class="alert alert-<?= $type === 'ok' ? 'ok' : 'err' ?>" role="status"><?= e($msg) ?></p><?php endforeach; ?>
    <?= csrfField() ?><input type="hidden" name="next" value="<?= e($next) ?>">
    <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required autocomplete="username" value="<?= e(postText('email')) ?>"></div>
    <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
    <button class="btn btn-primary btn-block" type="submit">Sign in</button>
    <p class="fine"><a href="<?= e(APP_URL) ?>/forgot-password.php">Forgot your password?</a> · <a href="<?= e(APP_URL) ?>/register.php">Create an account</a></p>
  </form>
</main>
<?php
siteFooter();
pageFoot();
