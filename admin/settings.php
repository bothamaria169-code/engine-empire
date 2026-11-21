<?php
declare(strict_types=1);

require __DIR__ . '/_shell.php';
require_once dirname(__DIR__) . '/includes/ai.php';

const PLAIN = ['whatsapp_number', 'contact_email', 'business_name', 'business_address', 'business_phone', 'business_email', 'vat_number', 'company_reg', 'vat_rate',
    'bank_name', 'bank_account_name', 'bank_account_no', 'bank_account_type', 'bank_branch_code', 'quote_prefix', 'invoice_prefix', 'quote_validity_days', 'payment_terms_days',
    'quote_terms', 'invoice_terms', 'smtp_host', 'smtp_port', 'smtp_user', 'groq_model', 'ai_monthly_limit', 'ga4_id', 'meta_pixel_id', 'google_site_verification'];
const SECRET_KEYS = ['groq_api_key', 'smtp_pass'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'save') {
        foreach (PLAIN as $k) set_setting($k, in_array($k, ['quote_terms', 'invoice_terms', 'business_address'], true) ? postText($k, 3000) : postText($k, 500));
        foreach (SECRET_KEYS as $k) save_secret_setting($k, (string) ($_POST[$k] ?? ''));
        set_setting('ai_enabled', empty($_POST['ai_enabled']) ? '0' : '1');
        set_setting('groq_models_cache', '');
        audit('settings_save');
        flash('ok', 'Settings saved.');
    } elseif ($action === 'test_ai') {
        if (aiKey() === '') flash('err', 'Save a Groq API key first.');
        else {
            $r = groqJson('Answer ONLY with JSON {"ok":true,"word":"engines"}.', 'Reply now.', 60);
            flash($r['ok'] ? 'ok' : 'err', $r['ok'] ? 'Groq works. Model in use: ' . groqModel() : 'Groq said: ' . $r['error']);
        }
    } elseif ($action === 'test_email') {
        $to = get_setting('contact_email', CONTACT_EMAIL);
        $err = sendMail($to, 'Test email from ' . APP_NAME, "This is a test.\nEnquiries and quotes from the website are sent from this mailbox.");
        flash($err === '' ? 'ok' : 'err', $err === '' ? "Test email sent to {$to}. Check the inbox (and spam) in a minute." : "Email not sent: {$err} Enquiries are still saved under Enquiries.");
    } elseif ($action === 'admin_email') {
        $new = strtolower(postText('admin_email', 255));
        $dup = getDB()->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
        $dup->execute([$new, $ADMIN['id']]);
        if (!filter_var($new, FILTER_VALIDATE_EMAIL)) flash('err', 'Enter a valid email address.');
        elseif (!password_verify((string) ($_POST['current_password'] ?? ''), (string) $ADMIN['password_hash'])) flash('err', 'Your current password is not right.');
        elseif ((int) $dup->fetchColumn() > 0) flash('err', 'Another user already has that email.');
        else {
            getDB()->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$new, $ADMIN['id']]);
            if (!empty($_POST['also_contact'])) { set_setting('contact_email', $new); set_setting('business_email', $new); }
            audit('admin_email_change', $ADMIN['email'] . ' → ' . $new);
            flash('ok', 'Your admin email is now ' . $new . '. Use it to sign in from now on.');
        }
    } elseif ($action === 'password') {
        $pw = (string) ($_POST['new_password'] ?? '');
        if (strlen($pw) < 10) flash('err', 'Use at least 10 characters.');
        elseif (!password_verify((string) ($_POST['current_password'] ?? ''), (string) $ADMIN['password_hash'])) flash('err', 'Your current password is not right.');
        else {
            getDB()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $ADMIN['id']]);
            audit('password_change');
            flash('ok', 'Password changed.');
        }
    }
    redirect('admin/settings.php');
}

$g = fn(string $k, string $d = '') => e(get_setting($k, $d));
$models = aiKey() !== '' ? groqModels() : [];
adminHead('Settings', 'settings.php');
?>
<form method="post" class="card glass">
  <?= csrfField() ?>
  <h2>Business details (printed on quotes &amp; invoices)</h2>
  <div class="fgrid">
    <div class="field"><label for="business_name">Business name</label><input id="business_name" name="business_name" value="<?= $g('business_name', APP_NAME) ?>"></div>
    <div class="field"><label for="business_phone">Phone</label><input id="business_phone" name="business_phone" inputmode="tel" value="<?= $g('business_phone') ?>"></div>
    <div class="field"><label for="business_email">Business email</label><input id="business_email" name="business_email" type="email" value="<?= $g('business_email', CONTACT_EMAIL) ?>"></div>
    <div class="field"><label for="company_reg">Company registration no. (optional)</label><input id="company_reg" name="company_reg" value="<?= $g('company_reg') ?>"></div>
    <div class="field"><label for="vat_number">VAT number</label><input id="vat_number" name="vat_number" value="<?= $g('vat_number') ?>"><small>Leave blank if you are not VAT registered. New documents then get 0% VAT and say "Invoice" instead of "Tax invoice".</small></div>
    <div class="field"><label for="vat_rate">VAT rate %</label><input id="vat_rate" name="vat_rate" inputmode="decimal" value="<?= $g('vat_rate', '15') ?>"></div>
    <div class="field wide"><label for="business_address">Address</label><textarea id="business_address" name="business_address" rows="3"><?= $g('business_address') ?></textarea></div>
  </div>

  <h2 style="margin-top:28px">Banking details (EFT)</h2>
  <div class="fgrid">
    <div class="field"><label for="bank_name">Bank</label><input id="bank_name" name="bank_name" value="<?= $g('bank_name') ?>" placeholder="e.g. FNB"></div>
    <div class="field"><label for="bank_account_name">Account name</label><input id="bank_account_name" name="bank_account_name" value="<?= $g('bank_account_name') ?>"></div>
    <div class="field"><label for="bank_account_no">Account number</label><input id="bank_account_no" name="bank_account_no" inputmode="numeric" value="<?= $g('bank_account_no') ?>"></div>
    <div class="field"><label for="bank_account_type">Account type</label><input id="bank_account_type" name="bank_account_type" value="<?= $g('bank_account_type') ?>" placeholder="Business cheque"></div>
    <div class="field"><label for="bank_branch_code">Branch code</label><input id="bank_branch_code" name="bank_branch_code" value="<?= $g('bank_branch_code') ?>"></div>
  </div>

  <h2 style="margin-top:28px">Quote &amp; invoice defaults</h2>
  <div class="fgrid">
    <div class="field"><label for="quote_prefix">Quote number prefix</label><input id="quote_prefix" name="quote_prefix" maxlength="6" value="<?= $g('quote_prefix', 'QT') ?>"><small>Numbers look like QT-<?= date('Y') ?>-0001.</small></div>
    <div class="field"><label for="invoice_prefix">Invoice number prefix</label><input id="invoice_prefix" name="invoice_prefix" maxlength="6" value="<?= $g('invoice_prefix', 'INV') ?>"></div>
    <div class="field"><label for="quote_validity_days">Quote valid for (days)</label><input id="quote_validity_days" name="quote_validity_days" type="number" min="1" value="<?= $g('quote_validity_days', '7') ?>"></div>
    <div class="field"><label for="payment_terms_days">Invoice due after (days)</label><input id="payment_terms_days" name="payment_terms_days" type="number" min="0" value="<?= $g('payment_terms_days', '7') ?>"></div>
    <div class="field wide"><label for="quote_terms">Default quote terms</label><textarea id="quote_terms" name="quote_terms" rows="3"><?= $g('quote_terms', DEFAULT_QUOTE_TERMS) ?></textarea></div>
    <div class="field wide"><label for="invoice_terms">Default invoice terms</label><textarea id="invoice_terms" name="invoice_terms" rows="3"><?= $g('invoice_terms', DEFAULT_INVOICE_TERMS) ?></textarea></div>
  </div>

  <h2 style="margin-top:28px">Contact</h2>
  <div class="fgrid">
    <div class="field"><label for="whatsapp_number">WhatsApp number</label><input id="whatsapp_number" name="whatsapp_number" inputmode="tel" value="<?= $g('whatsapp_number') ?>" placeholder="e.g. 082 123 4567"><small>Shows the green WhatsApp buttons. Leave blank to hide them.</small></div>
    <div class="field"><label for="contact_email">Enquiries go to this email</label><input id="contact_email" name="contact_email" type="email" value="<?= $g('contact_email', CONTACT_EMAIL) ?>"></div>
  </div>

  <h2 style="margin-top:28px">Email (the mailbox that sends quotes &amp; invoices)</h2>
  <div class="fgrid">
    <div class="field"><label for="smtp_host">Mail server</label><input id="smtp_host" name="smtp_host" value="<?= $g('smtp_host', SMTP_HOST) ?>" placeholder="mail.yourdomain.co.za"><small>cPanel → Email Accounts → Connect Devices → "Outgoing Server".</small></div>
    <div class="field"><label for="smtp_port">Port</label><input id="smtp_port" name="smtp_port" type="number" value="<?= $g('smtp_port', (string) (SMTP_PORT ?: 465)) ?>"><small>465 (SSL) unless your host says 587.</small></div>
    <div class="field"><label for="smtp_user">Mailbox</label><input id="smtp_user" name="smtp_user" type="email" autocomplete="off" value="<?= $g('smtp_user', SMTP_USER) ?>"></div>
    <div class="field"><label for="smtp_pass">Mailbox password</label><input id="smtp_pass" name="smtp_pass" type="password" autocomplete="new-password" placeholder="<?= get_setting('smtp_pass', SMTP_PASS) !== '' ? 'Saved — type to change' : 'Password of the mailbox' ?>"><small>Leave blank to keep the saved password.</small></div>
  </div>

  <h2 style="margin-top:28px">AI (Groq)</h2>
  <div class="fgrid">
    <div class="field"><label for="groq_api_key">Groq API key</label><input id="groq_api_key" name="groq_api_key" autocomplete="off" value="<?= e(masked(aiKey())) ?>" placeholder="gsk_…"><small>From console.groq.com → API Keys.</small></div>
    <div class="field"><label for="groq_model">Model</label><select id="groq_model" name="groq_model"><option value="">Automatic (recommended)</option><?php foreach ($models as $m): ?><option<?= $m === get_setting('groq_model') ? ' selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="ai_monthly_limit">Monthly AI call limit</label><input id="ai_monthly_limit" name="ai_monthly_limit" type="number" min="0" value="<?= $g('ai_monthly_limit', '3000') ?>"><small>Used this month: <?= aiUsedThisMonth() ?></small></div>
    <label class="check"><input type="checkbox" name="ai_enabled" value="1"<?= get_setting('ai_enabled', '1') === '1' ? ' checked' : '' ?>> <span>Let AI write product descriptions ("✨ Write with AI" on the product page).</span></label>
  </div>

  <h2 style="margin-top:28px">Google &amp; Meta tracking</h2>
  <div class="fgrid">
    <div class="field"><label for="ga4_id">Google Analytics 4 ID</label><input id="ga4_id" name="ga4_id" value="<?= $g('ga4_id') ?>" placeholder="G-XXXXXXXXXX"></div>
    <div class="field"><label for="google_site_verification">Search Console verification code</label><input id="google_site_verification" name="google_site_verification" value="<?= $g('google_site_verification') ?>"></div>
    <div class="field"><label for="meta_pixel_id">Meta Pixel ID</label><input id="meta_pixel_id" name="meta_pixel_id" value="<?= $g('meta_pixel_id') ?>"></div>
  </div>
  <p style="margin-top:20px" class="inline-form">
    <button class="btn btn-primary" name="action" value="save"><i class="fa-solid fa-floppy-disk"></i> Save settings</button>
    <button class="btn btn-ghost" name="action" value="test_ai">Test AI</button>
    <button class="btn btn-ghost" name="action" value="test_email">Send test email</button>
  </p>
</form>

<form method="post" class="card glass">
  <?= csrfField() ?><input type="hidden" name="action" value="admin_email">
  <h2>Admin email (my sign-in address)</h2>
  <div class="fgrid">
    <div class="field"><label for="admin_email">Admin email</label><input id="admin_email" name="admin_email" type="email" required value="<?= e($ADMIN['email']) ?>"></div>
    <div class="field"><label for="ae_pw">Current password (to confirm)</label><input id="ae_pw" name="current_password" type="password" required autocomplete="current-password"></div>
    <label class="check wide"><input type="checkbox" name="also_contact" value="1"> <span>Also send enquiries to this address and print it on quotes and invoices.</span></label>
  </div>
  <p style="margin-top:16px"><button class="btn btn-dark">Change admin email</button></p>
</form>

<form method="post" class="card glass">
  <?= csrfField() ?><input type="hidden" name="action" value="password">
  <h2>Change my password</h2>
  <div class="fgrid">
    <div class="field"><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password"></div>
    <div class="field"><label for="new_password">New password (10+ characters)</label><input id="new_password" name="new_password" type="password" required minlength="10" autocomplete="new-password"></div>
  </div>
  <p style="margin-top:16px"><button class="btn btn-dark">Change password</button></p>
</form>
<?php adminFoot();
