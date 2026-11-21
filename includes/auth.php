<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Accounts. Roles: admin and staff (the /admin area) and customer (sees only their own quotes and invoices).
 * A customer's documents appear only after their email address is verified, so nobody can read
 * someone else's quote by registering with that person's email.
 */

function currentUser(): ?array
{
    startAppSession();
    if (empty($_SESSION['uid'])) return null;
    static $u = false;
    if ($u === false) {
        $s = getDB()->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
        $s->execute([$_SESSION['uid']]);
        $u = $s->fetch() ?: null;
    }
    return $u;
}

function isStaff(?array $u): bool
{
    return $u !== null && in_array($u['role'], ['admin', 'staff'], true);
}

/** The signed-in admin or staff member, or null (customers are not admins). */
function currentAdmin(): ?array
{
    $u = currentUser();
    return isStaff($u) ? $u : null;
}

function requireUser(): array
{
    $u = currentUser();
    if (!$u) redirect('login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? ''));
    return $u;
}

function requireAdmin(): array
{
    $u = currentUser();
    if (!$u) redirect('login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? ''));
    if (!isStaff($u)) redirect('account.php');
    return $u;
}

/** Where to send someone after sign-in. */
function homeFor(array $u): string
{
    return isStaff($u) ? 'admin/' : 'account.php';
}

/** @return string error message, or '' on success */
function attemptLogin(string $email, string $password): string
{
    $email = strtolower(trim($email));
    if (tooManyAttempts($email)) return 'Too many tries. Wait 15 minutes and try again.';
    $s = getDB()->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1');
    $s->execute([$email]);
    $u = $s->fetch();
    if (!$u || !$u['password_hash'] || !password_verify($password, $u['password_hash'])) {
        recordAttempt($email, false);
        return 'That email and password don\'t match.';
    }
    recordAttempt($email, true);
    startAppSession();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $u['id'];
    getDB()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$u['id']]);
    if ($u['email_verified_at']) claimDocuments((int) $u['id'], $u['email']);
    audit('login', $email);
    return '';
}

/** Random token for an email link. Only its hash is stored, and it works once. */
function newToken(): array
{
    $raw = bin2hex(random_bytes(24));
    return [$raw, hash('sha256', $raw)];
}

/** @return string error message, or '' and the new user id in $id */
function registerCustomer(string $name, string $email, string $phone, string $password, ?int &$id = null): string
{
    $email = strtolower(trim($email));
    if ($name === '') return 'Please enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'Please enter a valid email address.';
    if (strlen($password) < 8) return 'Use a password of at least 8 characters.';
    $db = getDB();
    $s = $db->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
    $s->execute([$email]);
    if ((int) $s->fetchColumn() > 0) return 'There is already an account with that email. Sign in, or use "Forgot password".';
    [$raw, $hash] = newToken();
    $db->prepare("INSERT INTO users (email, full_name, phone, password_hash, role, verify_hash, consent_at) VALUES (?, ?, ?, ?, 'customer', ?, NOW())")
       ->execute([$email, mb_substr($name, 0, 120), mb_substr($phone, 0, 40) ?: null, password_hash($password, PASSWORD_DEFAULT), $hash]);
    $id = (int) $db->lastInsertId();
    sendVerification($id, $email, $name, $raw);
    audit('register', $email);
    return '';
}

/** Emails the "confirm your email" link. On localhost the link is shown on screen instead. */
function sendVerification(int $userId, string $email, string $name, string $raw): void
{
    $link = APP_URL . '/verify.php?u=' . $userId . '&t=' . $raw;
    $body = "Hi " . (trim(explode(' ', trim($name))[0] ?? '') ?: 'there') . ",\n\nPlease confirm your email address so you can see your quotes and invoices:\n{$link}\n\nIf you did not create this account, ignore this email.\n\n" . APP_NAME;
    $err = sendMail($email, 'Confirm your email — ' . APP_NAME, $body);
    if ($err !== '') error_log('Verification email failed: ' . $err);
    if (IS_LOCAL) {
        startAppSession();
        $_SESSION['dev_link'] = $link;
    }
}

function resendVerification(array $u): void
{
    [$raw, $hash] = newToken();
    getDB()->prepare('UPDATE users SET verify_hash = ? WHERE id = ?')->execute([$hash, $u['id']]);
    sendVerification((int) $u['id'], $u['email'], (string) $u['full_name'], $raw);
}

function verifyEmail(int $userId, string $raw): bool
{
    $db = getDB();
    $s = $db->prepare('SELECT * FROM users WHERE id = ? AND verify_hash IS NOT NULL');
    $s->execute([$userId]);
    $u = $s->fetch();
    if (!$u || !hash_equals((string) $u['verify_hash'], hash('sha256', $raw))) return false;
    $db->prepare('UPDATE users SET email_verified_at = NOW(), verify_hash = NULL WHERE id = ?')->execute([$userId]);
    claimDocuments($userId, $u['email']);
    return true;
}

/** Links quotes and invoices addressed to this (verified) email to the account. Drafts stay hidden from customers. */
function claimDocuments(int $userId, string $email): void
{
    getDB()->prepare('UPDATE documents SET user_id = ? WHERE user_id IS NULL AND cust_email = ?')->execute([$userId, strtolower($email)]);
}

/** Sends a password-reset link. Always behaves the same, so nobody can probe which emails have accounts. */
function startPasswordReset(string $email): void
{
    $email = strtolower(trim($email));
    $db = getDB();
    $s = $db->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1');
    $s->execute([$email]);
    if (!$u = $s->fetch()) return;
    [$raw, $hash] = newToken();
    $db->prepare('UPDATE users SET reset_hash = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?')->execute([$hash, $u['id']]);
    $link = APP_URL . '/reset-password.php?u=' . $u['id'] . '&t=' . $raw;
    sendMail($email, 'Reset your password — ' . APP_NAME, "Use this link within one hour to choose a new password:\n{$link}\n\nIf you did not ask for this, ignore this email.\n\n" . APP_NAME);
    if (IS_LOCAL) { startAppSession(); $_SESSION['dev_link'] = $link; }
}

function resetTokenValid(int $userId, string $raw): bool
{
    $s = getDB()->prepare('SELECT reset_hash FROM users WHERE id = ? AND reset_hash IS NOT NULL AND reset_expires > NOW()');
    $s->execute([$userId]);
    $h = $s->fetchColumn();
    return $h !== false && hash_equals((string) $h, hash('sha256', $raw));
}

function finishPasswordReset(int $userId, string $password): void
{
    getDB()->prepare('UPDATE users SET password_hash = ?, reset_hash = NULL, reset_expires = NULL, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?')
        ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
}

/** The documents a customer may see: theirs, and never drafts. */
function customerDocuments(int $userId): array
{
    $s = getDB()->prepare("SELECT * FROM documents WHERE user_id = ? AND status <> 'draft' ORDER BY id DESC LIMIT 200");
    $s->execute([$userId]);
    return $s->fetchAll();
}
