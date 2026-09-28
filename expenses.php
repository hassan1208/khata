<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/cash.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    if ($action === 'save') {
        $id = (int)post('id');
        $cat = (int)post('category_id') ?: null;
        $data = [valid_date(post('exp_date')), $cat, amount_in('amount'), post('note') ?: null];
        if (!$data[0] || $data[2] <= 0) {
            flash('Tareekh aur raqam sahi likhein.', 'danger');
        } elseif ($id) {
            db_exec('UPDATE expenses SET exp_date=?, category_id=?, amount=?, note=? WHERE id=?', [...$data, $id]);
            flash('Kharcha update ho gaya.');
        } else {
            db_exec('INSERT INTO expenses (exp_date, category_id, amount, note) VALUES (?,?,?,?)', $data);
            flash('Kharcha add ho gaya. Cash se minus ho gaya.');
        }
    } elseif ($action === 'delete') {
        db_exec('DELETE FROM expenses WHERE id = ?', [(int)post('id')]);
        flash('Kharcha delete ho gaya.', 'warning');
    }
    redirect('expenses.php?month=' . urlencode($_GET['month'] ?? date('Y-m')) . '&cat=' . urlencode($_GET['cat'] ?? ''));
}

$month = month_start($_GET['month'] ?? date('Y-m')) ?? date('Y-m-01');
$catFilter = (int)($_GET['cat'] ?? 0);
$cats = db_all('SELECT * FROM expense_categories ORDER BY name');

$sql = 'SELECT e.*, c.name AS cat_name FROM expenses e LEFT JOIN expense_categories c ON c.id = e.category_id WHERE e.exp_date BETWEEN ? AND ?';
$params = [$month, date('Y-m-t', strtotime($month))];
if ($catFilter) { $sql .= ' AND e.category_id = ?'; $params[] = $catFilter; }
$rows = db_all($sql . ' ORDER BY e.exp_date DESC, e.id DESC', $params);
$total = array_sum(array_column($rows, 'amount'));
$mt = month_totals($month);

// Pichle 6 maheenay ka comparison
$trend = db_all("SELECT DATE_FORMAT(exp_date, '%Y-%m-01') m, SUM(amount) total FROM expenses
                 WHERE exp_date >= ? GROUP BY m ORDER BY m", [date('Y-m-01', strtotime("$month -5 month"))]);

$pageTitle = 'Expenses';
require __DIR__ . '/includes/header.php';
$prev = date('Y-m', strtotime("$month -1 month")); $next = date('Y-m', strtotime("$month +1 month"));
?>
<div class="page-head">
  <h1><i class="bi bi-cart"></i> Mahana Kharcha</h1>
  <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#expModal" data-fill="#expForm"
    data-values='<?= e(json_encode(['exp_date' => date('Y-m-d')])) ?>'><i class="bi bi-plus-lg"></i> Kharcha Add</button>
</div>

<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
  <a class="btn btn-sm btn-outline-secondary" href="?month=<?= $prev ?>&cat=<?= $catFilter ?>"><i class="bi bi-chevron-left"></i></a>
  <form class="d-flex gap-2 flex-wrap">
    <input type="month" name="month" value="<?= substr($month, 0, 7) ?>" class="form-control form-control-sm" onchange="this.form.submit()">
    <select name="cat" class="form-select form-select-sm" onchange="this.form.submit()"><option value="0">Sab categories</option>
      <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $c['id'] == $catFilter ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
  </form>
  <a class="btn btn-sm btn-outline-secondary" href="?month=<?= $next ?>&cat=<?= $catFilter ?>"><i class="bi bi-chevron-right"></i></a>
  <div class="ms-auto small">
    Income: <strong class="text-in"><?= money($mt['income']) ?></strong> ·
    Kharcha: <strong class="text-out"><?= money($mt['expense']) ?></strong> ·
    Bachat: <strong><?= money($mt['income'] - $mt['expense']) ?></strong>
  </div>
</div></div>

<div class="row g-3">
<div class="col-lg-8">
<div class="card">
  <div class="table-responsive"><table class="table table-hover mb-0">
    <thead class="table-light"><tr><th>Tareekh</th><th>Category</th><th>Note</th><th class="amt">Raqam</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="text-nowrap"><?= fdate($r['exp_date']) ?></td><td><?= e($r['cat_name'] ?? 'Other') ?></td>
        <td class="small text-muted"><?= e($r['note']) ?></td><td class="amt text-out"><?= money($r['amount']) ?></td>
        <td class="text-end text-nowrap">
          <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#expModal" data-fill="#expForm"
            data-values='<?= e(json_encode(['id' => $r['id'], 'exp_date' => $r['exp_date'], 'category_id' => $r['category_id'], 'amount' => $r['amount'], 'note' => $r['note']])) ?>'><i class="bi bi-pencil"></i></button>
          <form method="post" class="d-inline" data-confirm="Delete karein?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="text-muted text-center p-4">Is maheenay koi kharcha nahi.</td></tr><?php endif; ?>
    </tbody>
    <tfoot class="table-light"><tr><th colspan="3">Total <?= fmonth($month) ?></th><th class="amt"><?= money($total) ?></th><th></th></tr></tfoot>
  </table></div>
</div>
</div>
<div class="col-lg-4">
  <div class="card"><div class="card-header bg-white fw-semibold">Pichle 6 maheenay</div><div class="card-body">
    <?php $max = $trend ? max(array_column($trend, 'total')) : 1; ?>
    <?php foreach ($trend as $t): ?>
      <div class="d-flex justify-content-between small"><a href="?month=<?= substr($t['m'], 0, 7) ?>"><?= fmonth($t['m']) ?></a><span><?= money($t['total']) ?></span></div>
      <div class="progress mb-2" style="height:6px"><div class="progress-bar bg-danger" style="width:<?= round($t['total'] / $max * 100) ?>%"></div></div>
    <?php endforeach; ?>
    <?php if (!$trend): ?><p class="text-muted mb-0 small">Koi data nahi.</p><?php endif; ?>
    <a href="settings.php?tab=categories" class="small">Categories manage karein</a>
  </div></div>
</div>
</div>

<div class="modal fade" id="expModal" tabindex="-1"><div class="modal-dialog"><form method="post" id="expForm" class="modal-content"><?= csrf_field() ?>
  <input type="hidden" name="action" value="save"><input type="hidden" name="id">
  <div class="modal-header"><h5 class="modal-title">Kharcha</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2">
    <div class="col-6"><label class="form-label">Tareekh</label><input type="date" name="exp_date" class="form-control" required></div>
    <div class="col-6"><label class="form-label">Raqam</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div>
    <div class="col-12"><label class="form-label">Category</label><select name="category_id" class="form-select"><option value="">-- Other --</option>
      <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-12"><label class="form-label">Note</label><input name="note" class="form-control" maxlength="255"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-danger">Save</button></div>
</form></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
