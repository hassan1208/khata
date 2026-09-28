<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/cash.php';
require __DIR__ . '/includes/rent.php';

$thisMonth = date('Y-m-01');
$cash = cash_balance();
$mt = month_totals($thisMonth);
$inv = investment_totals();
$loans = loan_totals();

$rentRows = [];
$rentMonthly = 0; $rentPending = 0;
foreach (active_tenancies() as $t) {
    $l = rent_ledger($t);
    $rentMonthly += $l['current_rent'];
    $rentPending += max(0, $l['balance']);
    $rentRows[] = ['t' => $t, 'l' => $l];
}

$recent = db_all('SELECT * FROM (' . cashbook_sql() . ') t ORDER BY d DESC, created_at DESC LIMIT 8');
$catBreak = db_all('SELECT COALESCE(c.name, "Other") AS name, SUM(e.amount) AS total FROM expenses e
                    LEFT JOIN expense_categories c ON c.id = e.category_id
                    WHERE e.exp_date BETWEEN ? AND ? GROUP BY c.name ORDER BY total DESC',
                    [$thisMonth, date('Y-m-t')]);

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';

function stat_card(string $label, string $value, string $icon, string $cls = '', string $href = ''): void
{
    echo '<div class="col-6 col-lg-3"><a class="text-decoration-none text-reset" href="' . e($href ?: '#') . '"><div class="card stat-card h-100"><div class="card-body">'
        . '<div class="label"><i class="bi ' . e($icon) . '"></i> ' . e($label) . '</div>'
        . '<div class="value ' . e($cls) . '">' . $value . '</div></div></div></a></div>';
}
?>
<div class="page-head"><h1>Assalam o Alaikum, <?= e(current_user()['username']) ?></h1><span class="text-muted"><?= date('l, d M Y') ?></span></div>

<h6 class="text-muted text-uppercase small mb-2">Personal Cash</h6>
<div class="row g-3 mb-4">
  <?php stat_card('Mere paas cash', money($cash), 'bi-wallet2', $cash < 0 ? 'text-out' : 'text-in', 'cashbook.php'); ?>
  <?php stat_card('Is maheenay income', money($mt['income']), 'bi-cash-coin', '', 'income.php'); ?>
  <?php stat_card('Is maheenay kharcha', money($mt['expense']), 'bi-cart', 'text-out', 'expenses.php'); ?>
  <?php stat_card('Is maheenay bachat', money($mt['income'] - $mt['expense']), 'bi-piggy-bank', ($mt['income'] - $mt['expense']) < 0 ? 'text-out' : 'text-in'); ?>
  <?php stat_card('Investment laga hua', money($inv['outstanding']), 'bi-graph-up-arrow', '', 'investments.php'); ?>
  <?php stat_card('Investment profit', money($inv['profit']), 'bi-currency-exchange', 'text-in', 'investments.php'); ?>
  <?php stat_card('Logon ne dena hai', money($loans['receivable']), 'bi-arrow-down-left-circle', 'text-in', 'loans.php'); ?>
  <?php stat_card('Maine dena hai', money($loans['payable']), 'bi-arrow-up-right-circle', 'text-out', 'loans.php'); ?>
</div>

<h6 class="text-muted text-uppercase small mb-2">Rent (alag hisab)</h6>
<div class="row g-3 mb-3">
  <?php stat_card('Active kirayedar', (string)count($rentRows), 'bi-people', '', 'properties.php'); ?>
  <?php stat_card('Mahana kiraya', money($rentMonthly), 'bi-house-door', '', 'properties.php'); ?>
  <?php stat_card('Kiraya baqi', money($rentPending), 'bi-exclamation-triangle', $rentPending > 0 ? 'text-out' : '', 'properties.php'); ?>
  <?php stat_card('Increment due', (string)count(array_filter($rentRows, fn($r) => $r['l']['increment_due'])), 'bi-arrow-up-circle', '', 'properties.php'); ?>
</div>

<?php $alerts = array_filter($rentRows, fn($r) => $r['l']['balance'] > 0.5 || $r['l']['increment_due']); ?>
<?php if ($alerts): ?>
<div class="card mb-4"><div class="card-header bg-white fw-semibold"><i class="bi bi-bell text-warning"></i> Rent alerts</div>
  <div class="list-group list-group-flush">
  <?php foreach ($alerts as $r): $t = $r['t']; $l = $r['l']; ?>
    <a href="tenancy.php?id=<?= $t['id'] ?>" class="list-group-item list-group-item-action d-flex flex-wrap justify-content-between gap-2">
      <span><strong><?= e($t['property_name']) ?></strong> · <?= e($t['tenant_name']) ?></span>
      <span>
        <?php if ($l['balance'] > 0.5): ?><span class="badge text-bg-danger"><?= money($l['balance']) ?> baqi (~<?= $l['pending_months'] ?> maheenay)</span><?php endif; ?>
        <?php if ($l['increment_due']): ?><span class="badge text-bg-info">Increment due: <?= fmonth($l['next_increment']) ?> → <?= money($l['suggested_rent']) ?></span><?php endif; ?>
      </span>
    </a>
  <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card h-100"><div class="card-header bg-white fw-semibold d-flex justify-content-between"><span>Haaliya len-den</span><a href="cashbook.php" class="small">Sab dekhein</a></div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <?php foreach ($recent as $r): ?>
          <tr><td class="text-muted small"><?= fdate($r['d']) ?></td><td><?= e($r['descr']) ?><?php if ($r['note']): ?><div class="small text-muted"><?= e($r['note']) ?></div><?php endif; ?></td>
            <td class="amt <?= $r['amt'] < 0 ? 'text-out' : 'text-in' ?>"><?= money($r['amt']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?><tr><td class="text-muted p-3">Abhi koi entry nahi. <a href="income.php">Salary</a> ya <a href="expenses.php">kharcha</a> add karein.</td></tr><?php endif; ?>
      </table></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100"><div class="card-header bg-white fw-semibold">Is maheenay kharcha (category)</div>
      <div class="card-body">
        <?php $max = $catBreak ? max(array_column($catBreak, 'total')) : 1; ?>
        <?php foreach ($catBreak as $c): ?>
          <div class="d-flex justify-content-between small"><span><?= e($c['name']) ?></span><span><?= money($c['total']) ?></span></div>
          <div class="progress mb-2" style="height:6px"><div class="progress-bar bg-danger" style="width:<?= round($c['total'] / $max * 100) ?>%"></div></div>
        <?php endforeach; ?>
        <?php if (!$catBreak): ?><p class="text-muted mb-0">Is maheenay koi kharcha nahi.</p><?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
