<?php
define('PUBLIC_PAGE', true);
require __DIR__ . '/includes/bootstrap.php';

try {
    db();
} catch (mysqli_sql_exception $ex) {
    $bare = true; $pageTitle = 'Setup';
    require __DIR__ . '/includes/header.php';
    echo '<div class="card auth-card p-4"><h4 class="text-danger">Database connect nahi hua</h4><p class="small">'
        . e($ex->getMessage()) . '</p><p class="small mb-0">config.php mein DB_HOST, DB_USER, DB_PASS, DB_NAME check karein aur database "'
        . e(DB_NAME) . '" pehle phpMyAdmin mein bana lein.</p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if (is_installed()) {
    redirect('login.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = post('username');
    $email    = post('email');
    $pass     = (string)($_POST['password'] ?? '');
    $pass2    = (string)($_POST['password2'] ?? '');
    $opening  = amount_in('opening_cash');

    if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $username)) $errors[] = 'Username 3-50 characters (letters, numbers, _ .) ho.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Sahi email likhein (OTP isi pr aayega).';
    if (strlen($pass) < 8) $errors[] = 'Password kam az kam 8 characters ka ho.';
    if ($pass !== $pass2) $errors[] = 'Dono passwords match nahi karte.';

    if (!$errors) {
        $sql = file_get_contents(__DIR__ . '/database.sql');
        db()->multi_query($sql);
        do {
            if ($r = db()->store_result()) $r->free();
        } while (db()->more_results() && db()->next_result());

        db_exec('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)', [$username, $email, password_hash($pass, PASSWORD_DEFAULT)]);
        set_setting('opening_cash', $opening);
        set_setting('opening_cash_date', date('Y-m-d'));
        flash('Setup mukammal! Ab login karein. Settings mein ja kar SMTP set karein taake OTP email par aaye.');
        redirect('login.php');
    }
}

$bare = true; $pageTitle = 'Setup';
require __DIR__ . '/includes/header.php';
?>
<div class="card auth-card p-4">
  <h4 class="mb-1"><i class="bi bi-journal-bookmark-fill text-success"></i> <?= e(APP_NAME) ?> Setup</h4>
  <p class="text-muted small">Pehli dafa: apna admin account banayein. Database tables khud ban jayenge.</p>
  <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2 small"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post"><?= csrf_field() ?>
    <div class="mb-2"><label class="form-label">Username</label><input name="username" class="form-control" required value="<?= e(post('username')) ?>"></div>
    <div class="mb-2"><label class="form-label">Email (OTP k liye)</label><input type="email" name="email" class="form-control" required value="<?= e(post('email')) ?>"></div>
    <div class="mb-2"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required minlength="8"></div>
    <div class="mb-2"><label class="form-label">Password dobara</label><input type="password" name="password2" class="form-control" required minlength="8"></div>
    <div class="mb-3"><label class="form-label">Is waqt mere paas cash (opening)</label><input name="opening_cash" type="number" step="0.01" class="form-control" value="<?= e(post('opening_cash', '0')) ?>"></div>
    <button class="btn btn-success w-100">Setup Karein</button>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
