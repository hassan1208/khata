<?php
define('PUBLIC_PAGE', true);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/mailer.php';

if (!is_installed()) redirect('setup.php');
if (current_user()) redirect('index.php');

const MAX_FAILS = 5;       // itni ghalat koshishon k baad
const LOCK_MINUTES = 15;   // itni der k liye block

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = client_ip();
    $fails = (int)db_val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > NOW() - INTERVAL ' . LOCK_MINUTES . ' MINUTE', [$ip]);
    if ($fails >= MAX_FAILS) {
        $error = 'Bohat zyada ghalat koshishein. ' . LOCK_MINUTES . ' minute baad dobara try karein.';
    } else {
        $user = db_one('SELECT * FROM users WHERE username = ? OR email = ?', [post('username'), post('username')]);
        if ($user && password_verify((string)($_POST['password'] ?? ''), $user['password_hash'])) {
            db_exec('DELETE FROM login_attempts WHERE ip = ?', [$ip]);

            if (setting('otp_enabled', '1') === '1' && smtp_configured()) {
                session_regenerate_id(true);
                $_SESSION['pending_user'] = (int)$user['id'];
                require __DIR__ . '/includes/otp_send.php';
                try {
                    send_login_otp($user);
                    redirect('otp.php');
                } catch (RuntimeException $ex) {
                    unset($_SESSION['pending_user']);
                    $error = 'OTP email nahi ja saki: ' . $ex->getMessage();
                }
            } else {
                login_user((int)$user['id']);
                if (!smtp_configured()) {
                    flash('SMTP abhi set nahi hai, is liye OTP k baghair login hua. Settings > Email (SMTP) set kar lein.', 'warning');
                }
                redirect('index.php');
            }
        } else {
            db_exec('INSERT INTO login_attempts (ip) VALUES (?)', [$ip]);
            usleep(400000);
            $error = 'Username ya password ghalat hai.';
        }
    }
}

$bare = true; $pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>
<div class="card auth-card p-4">
  <h4 class="mb-3 text-center"><i class="bi bi-journal-bookmark-fill text-success"></i> <?= e(APP_NAME) ?></h4>
  <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>
  <form method="post"><?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Username ya Email</label><input name="username" class="form-control" required autofocus value="<?= e(post('username')) ?>"></div>
    <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
    <button class="btn btn-success w-100"><i class="bi bi-box-arrow-in-right"></i> Login</button>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
