<?php
/*
 * Khata - configuration
 * Apni database ki details yahan likhein. Agar aap chahte hain k ye file git mein
 * change na ho to same settings config.local.php mein likh dein (wo pehle load hogi).
 */
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
    return;
}

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'khata');
define('DB_PORT', 3306);

// SMTP password ko encrypt karne k liye secret key. Setup k waqt aik lambi random string likh dein.
define('APP_KEY', 'change-this-to-a-long-random-string');

define('APP_NAME', 'Khata');
define('CURRENCY', 'Rs');
define('APP_TIMEZONE', 'Asia/Karachi');
