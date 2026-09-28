<?php
require __DIR__ . '/includes/bootstrap.php';

$types = ['Salary', 'Bonus', 'Overtime', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    if ($action === 'save') {
        $id = (int)post('id');
        $data = [
            month_start(post('income_month')),
            valid_date(post('received_on')),
            in_array(post('type'), $types, true) ? post('type') : 'Salary',
            amount_in('amount'),
            post('note') ?: null,
        ];
        if (!$data[0] || !$data[1] || $data[3] <= 0) {
            flash('Maheena, tareekh aur raqam sahi likhein.', 'danger');
        } elseif ($id) {
            db_exec('UPDATE incomes SET income_month=?, received_on=?, type=?, amount=?, note=? WHERE id=?', [...$data, $id]);
            flash('Income update ho gayi.');
        } else {
            db_exec('INSERT INTO incomes (income_month, received_on, type, amount, note) VALUES (?,?,?,?,?)', $data);
            flash('Income add ho gayi. Cash mein jama ho gayi.');
        }
    } elseif ($action === 'delete') {
        db_exec('DELETE FROM incomes WHERE id = ?', [(int)post('id')]);
        flash('Entry delete ho gayi.', 'warning');
    }
    redirect('income.php?year=' . urlencode($_GET['year'] ?? date('Y')));
}

$year = (int)($_GET['year'] ?? date('Y'));
$rows = db_all('SELECT * FROM incomes WHERE YEAR(income_month) = ? ORDER BY income_month DESC, received_on DESC', [$year]);
$total = array_sum(array_column($rows, 'amount'));
$years = db_all('SELECT DISTINCT YEAR(income_month) y FROM incomes UNION SELECT YEAR(CURDATE()) ORDER BY y DESC');
$lastSalary = db_val("SELECT amount FROM incomes WHERE type='Salary' ORDER BY income_month DESC LIMIT 1");

$pageTitle = 'Salary / Income';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <h1><i class="bi bi-cash-coin"></i> Salary / Income</h1>
  <div class="d-flex gap-2">
    <form class="d-flex gap-2"><select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach ($years as $y): ?><option <?= $y['y'] == $year ? 'selected' : '' ?>><?= $y['y'] ?></option><?php endforeach; ?>
    </select></form>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#incModal" data-fill="#incForm"
      data-values='<?= e(json_encode(['income_month' => date('Y-m'), 'received_on' => date('Y-m-d'), 'type' => 'Salary', 'amount' => $lastSalary])) ?>'><i class="bi bi-plus-lg"></i> Add</button>
  </div>
</div>

<div class="card">
  <div class="table-responsive"><table class="table table-hover mb-0">
    <thead class="table-light"><tr><th>Maheena</th><th>Type</th><th>Mili</th><th>Note</th><th class="amt">Raqam</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= fmonth($r['income_month']) ?></td><td><?= e($r['type']) ?></td><td><?= fdate($r['received_on']) ?></td>
        <td class="small text-muted"><?= e($r['note']) ?></td><td class="amt text-in"><?= money($r['amount']) ?></td>
        <td class="text-end text-nowrap">
          <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#incModal" data-fill="#incForm"
            data-values='<?= e(json_encode(['id' => $r['id'], 'income_month' => substr($r['income_month'], 0, 7), 'received_on' => $r['received_on'], 'type' => $r['type'], 'amount' => $r['amount'], 'note' => $r['note']])) ?>'><i class="bi bi-pencil"></i></button>
          <form method="post" class="d-inline" data-confirm="Delete karein?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="text-muted text-center p-4">Is saal koi income nahi likhi.</td></tr><?php endif; ?>
    </tbody>
    <tfoot class="table-light"><tr><th colspan="4">Total <?= $year ?></th><th class="amt"><?= money($total) ?></th><th></th></tr></tfoot>
  </table></div>
</div>

<div class="modal fade" id="incModal" tabindex="-1"><div class="modal-dialog"><form method="post" id="incForm" class="modal-content"><?= csrf_field() ?>
  <input type="hidden" name="action" value="save"><input type="hidden" name="id">
  <div class="modal-header"><h5 class="modal-title">Salary / Income</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2">
    <div class="col-6"><label class="form-label">Kis maheenay ki</label><input type="month" name="income_month" class="form-control" required></div>
    <div class="col-6"><label class="form-label">Kab mili</label><input type="date" name="received_on" class="form-control" required></div>
    <div class="col-6"><label class="form-label">Type</label><select name="type" class="form-select"><?php foreach ($types as $t): ?><option><?= $t ?></option><?php endforeach; ?></select></div>
    <div class="col-6"><label class="form-label">Raqam</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div>
    <div class="col-12"><label class="form-label">Note</label><input name="note" class="form-control" maxlength="255"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-success">Save</button></div>
</form></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
