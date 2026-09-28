<?php
/*
 * Poore database ka SQL backup download (phpMyAdmin mein import kar k wapas la sakte hain).
 * ?files=1 par uploaded documents ki ZIP.
 */
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('settings.php?tab=backup');

if (post('what') === 'files') {
    if (!class_exists('ZipArchive')) { flash('Server par PHP zip extension nahi hai.', 'danger'); redirect('settings.php?tab=backup'); }
    $tmp = tempnam(sys_get_temp_dir(), 'khata');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    foreach (glob(__DIR__ . '/uploads/tenancy/*') as $f) {
        if (is_file($f) && basename($f) !== '.gitkeep') $zip->addFile($f, 'uploads/tenancy/' . basename($f));
    }
    if ($zip->numFiles === 0) $zip->addFromString('README.txt', 'Koi document upload nahi hua.');
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="khata-documents-' . date('Y-m-d') . '.zip"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    unlink($tmp);
    exit;
}

// Parent tables pehle, taake import mein foreign keys ka masla na ho
$tables = ['users', 'settings', 'expense_categories', 'incomes', 'expenses', 'investments', 'investment_entries',
           'loan_people', 'loan_entries', 'cash_adjustments', 'properties', 'tenancies', 'rent_revisions',
           'rent_payments', 'tenancy_files', 'property_expenses'];

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="khata-backup-' . date('Y-m-d-His') . '.sql"');

$db = db();
echo "-- Khata backup " . date('Y-m-d H:i:s') . "\n-- phpMyAdmin > Import se wapas layein\n\n";
echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
foreach ($tables as $table) {
    $create = $db->query("SHOW CREATE TABLE `$table`")->fetch_row()[1];
    echo "DROP TABLE IF EXISTS `$table`;\n$create;\n\n";
    $res = $db->query("SELECT * FROM `$table`", MYSQLI_USE_RESULT);
    $batch = [];
    while ($row = $res->fetch_row()) {
        $batch[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : "'" . $db->real_escape_string($v) . "'", $row)) . ')';
        if (count($batch) >= 200) { echo "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n"; $batch = []; }
    }
    $res->free();
    if ($batch) echo "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n";
    echo "\n";
}
echo "SET FOREIGN_KEY_CHECKS=1;\n";
