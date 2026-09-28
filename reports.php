<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/cash.php';
require __DIR__ . '/includes/rent.php';

$year = (int)($_GET['year'] ?? date('Y'));
$from = "$year-01-01";
$to = "$year-12-31";

// Maheena wise personal cash
$byMonth = function (string $sql) use ($from, $to): array {
    $out = array_fill(1, 12, 0.0);
    foreach (db_all($sql, [$from, $to]) as $r) $out[(int)$r['m']] = (float)$r['v'];
    return $out;
};
$income   = $byMonth('SELECT MONTH(received_on) m, SUM(amount) v FROM incomes WHERE received_on BETWEEN ? AND ? GROUP BY m');
$expense  = $byMonth('SELECT MONTH(exp_date) m, SUM(amount) v FROM expenses WHERE exp_date BETWEEN ? AND ? GROUP BY m');
$invested = $byMonth("SELECT MONTH(entry_date) m, SUM(amount) v FROM investment_entries WHERE type='invest' AND entry_date BETWEEN ? AND ? GROUP BY m");
$profit   = $byMonth("SELECT MONTH(entry_date) m, SUM(amount) v FROM investment_entries WHERE type='profit' AND entry_date BETWEEN ? AND ? GROUP BY m");

$closing = [];
for ($m = 1; $m <= 12; $m++) {
    $end = date('Y-m-t', strtotime(sprintf('%d-%02d-01', $year, $m)));
    $closing[$m] = $end <= date('Y-m-t') ? cash_balance($end) : null;
}

// Category x maheena
$catRows = db_all('SELECT COALESCE(c.name, "Other") name, MONTH(e.exp_date) m, SUM(e.amount) v FROM expenses e
                   LEFT JOIN expense_categories c ON c.id = e.category_id WHERE e.exp_date BETWEEN ? AND ? GROUP BY name, m', [$from, $to]);
$cats = [];
foreach ($catRows as $r) {
    $cats[$r['name']] ??= array_fill(1, 12, 0.0);
    $cats[$r['name']][(int)$r['m']] = (float)$r['v'];
}
uasort($cats, fn($a, $b) => array_sum($b) <=> array_sum($a));

// Rent: kitna banta tha vs kitna wusool hua (advance adjust shamil nahi)
$rentDue = array_fill(1, 12, 0.0);
foreach (db_all('SELECT * FROM tenancies') as $t) {
    foreach (rent_ledger($t)['months'] as $row) {
        if (substr($row['month'], 0, 4) == $year) $rentDue[(int)substr($row['month'], 5, 2)] += $row['due'];
    }
}
$rentGot = array_fill(1, 12, 0.0);
foreach (db_all('SELECT MONTH(pay_date) m, SUM(amount) v FROM rent_payments WHERE method <> ? AND pay_date BETWEEN ? AND ? GROUP BY m', ['Advance adjust', $from, $to]) as $r) {
    $rentGot[(int)$r['m']] = (float)$r['v'];
}
$propExp = $byMonth('SELECT MONTH(exp_date) m, SUM(amount) v FROM property_expenses WHERE exp_date BETWEEN ? AND ? GROUP BY m');

$years = db_all('SELECT y FROM (SELECT YEAR(received_on) y FROM incomes UNION SELECT YEAR(exp_date) FROM expenses
                 UNION SELECT YEAR(pay_date) FROM rent_payments UNION SELECT YEAR(CURDATE())) t ORDER BY y DESC');
$mNames = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

$pageTitle = 'Reports';
require __DIR__ . '/includes/header.php';

function row(string $label, array $vals, string $cls = '', bool $total = true): void
{
    echo '<tr><th class="text-nowrap">' . e($label) . '</th>';
    foreach ($vals as $v) echo '<td class="amt ' . $cls . '">' . ($v === null ? '' : ($v == 0 ? '<span class="text-muted">-</span>' : money($v, false))) . '</td>';
    $sum = array_sum(array_filter($vals, 'is_numeric'));
    if ($total) echo '<td class="amt fw-semibold ' . $cls . '">' . ($sum == 0 ? '<span class="text-muted">-</span>' : money($sum, false)) . '</td>';
    else echo '<td></td>';
    echo '</tr>';
}
$savings = array_map(fn($a, $b) => $a - $b, $income, $expense);
$savings = array_combine(range(1, 12), $savings);
$rentNet = array_combine(range(1, 12), array_map(fn($a, $b) => $a - $b, $rentGot, $propExp));
?>
<div class="page-head">
  <h1><i class="bi bi-bar-chart"></i> Reports <?= $year ?></h1>
  <div class="d-flex gap-2">
    <form><select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach ($years as $y): ?><option <?= $y['y'] == $year ? 'selected' : '' ?>><?= $y['y'] ?></option><?php endforeach; ?>
    </select></form>
    <button class="btn btn-sm btn-outline-secondary" onclick="print()"><i class="bi bi-printer"></i></button>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body"><div class="label">Saal ki income</div><div class="value text-in"><?= money(array_sum($income)) ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body"><div class="label">Saal ka kharcha</div><div class="value text-out"><?= money(array_sum($expense)) ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body"><div class="label">Bachat</div><div class="value"><?= money(array_sum($savings)) ?></div>
    <div class="small text-muted"><?= array_sum($income) > 0 ? number_format(array_sum($savings) / array_sum($income) * 100, 1) . '% income ka' : '' ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body"><div class="label">Rent wusool (net)</div><div class="value"><?= money(array_sum($rentNet)) ?></div></div></div></div>
</div>

<div class="card mb-3"><div class="card-header bg-white fw-semibold">Personal cash — maheena wise</div>
<div class="table-responsive"><table class="table table-sm table-hover mb-0 small">
  <thead class="table-light"><tr><th></th><?php foreach ($mNames as $n): ?><th class="amt"><?= $n ?></th><?php endforeach; ?><th class="amt">Total</th></tr></thead>
  <?php row('Income', $income, 'text-in'); row('Kharcha', $expense, 'text-out'); row('Bachat', $savings);
        row('Investment lagai', $invested); row('Investment profit', $profit, 'text-in'); row('Maheenay k aakhir cash', $closing, '', false); ?>
</table></div></div>

<div class="card mb-3"><div class="card-header bg-white fw-semibold">Kharcha — category wise</div>
<div class="table-responsive"><table class="table table-sm table-hover mb-0 small">
  <thead class="table-light"><tr><th></th><?php foreach ($mNames as $n): ?><th class="amt"><?= $n ?></th><?php endforeach; ?><th class="amt">Total</th></tr></thead>
  <?php foreach ($cats as $name => $vals) row($name, $vals); ?>
  <?php if (!$cats): ?><tr><td colspan="14" class="text-muted p-3">Is saal koi kharcha nahi.</td></tr><?php endif; ?>
</table></div></div>

<div class="card mb-3"><div class="card-header bg-white fw-semibold">Rent — maheena wise (alag hisab)</div>
<div class="table-responsive"><table class="table table-sm table-hover mb-0 small">
  <thead class="table-light"><tr><th></th><?php foreach ($mNames as $n): ?><th class="amt"><?= $n ?></th><?php endforeach; ?><th class="amt">Total</th></tr></thead>
  <?php row('Kiraya banta tha', $rentDue); row('Wusool hua', $rentGot, 'text-in'); row('Makan ka kharcha', $propExp, 'text-out'); row('Net', $rentNet); ?>
</table></div>
<div class="card-footer small text-muted">"Wusool hua" payment ki tareekh k hisab se hai; advance se kaata gaya kiraya is mein shamil nahi.</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
