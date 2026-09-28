<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/cash.php';
require __DIR__ . '/includes/loan_types.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'create') {
    $name = post('name');
    if ($name === '') { flash('Naam likhein.', 'danger'); redirect('loans.php'); }
    $pid = db_exec('INSERT INTO loan_people (name, phone, note) VALUES (?,?,?)', [$name, post('phone') ?: null, post('note') ?: null]);
    $amt = amount_in('amount');
    $type = array_key_exists(post('type'), LOAN_TYPES) ? post('type') : 'gave';
    if ($amt > 0) {
        db_exec('INSERT INTO loan_entries (person_id, entry_date, type, amount, note) VALUES (?,?,?,?,?)',
            [$pid, valid_date(post('entry_date')) ?? date('Y-m-d'), $type, $amt, post('entry_note') ?: null]);
    }
    flash('Account ban gaya.');
    redirect('loan.php?id=' . $pid);
}

$filter = $_GET['f'] ?? 'open';
$rows = db_all('SELECT p.*, COALESCE(' . loan_balance_sql() . ', 0) AS bal, MAX(e.entry_date) AS last_date,
                COALESCE(SUM(IF(e.type="gave", e.amount, 0)),0) gave, COALESCE(SUM(IF(e.type="took", e.amount, 0)),0) took
                FROM loan_people p LEFT JOIN loan_entries e ON e.person_id = p.id GROUP BY p.id ORDER BY p.name');
usort($rows, fn($a, $b) => abs($b['bal']) <=> abs($a['bal']));
if ($filter === 'open') $rows = array_filter($rows, fn($r) => abs($r['bal']) > 0.004);
$tot = loan_totals();

$pageTitle = 'Udhar';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <h1><i class="bi bi-people"></i> Udhar ka Hisab</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-sm btn-outline-secondary" href="?f=<?= $filter === 'open' ? 'all' : 'open' ?>"><?= $filter === 'open' ? 'Sab log dikhayein' : 'Sirf baqaya wale' ?></a>
    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#newPerson"><i class="bi bi-person-plus"></i> Naya banda</button>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="card stat-card"><div class="card-body"><div class="label">Logon ne mujhe dena hai</div><div class="value text-in"><?= money($tot['receivable']) ?></div></div></div></div>
  <div class="col-md-4"><div class="card stat-card"><div class="card-body"><div class="label">Maine logon ko dena hai</div><div class="value text-out"><?= money($tot['payable']) ?></div></div></div></div>
  <div class="col-md-4"><div class="card stat-card"><div class="card-body"><div class="label">Net</div><div class="value"><?= money($tot['receivable'] - $tot['payable']) ?></div></div></div></div>
</div>

<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead class="table-light"><tr><th>Naam</th><th>Phone</th><th>Aakhri len-den</th><th class="amt">Status</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr onclick="location='loan.php?id=<?= $r['id'] ?>'" style="cursor:pointer">
      <td><a href="loan.php?id=<?= $r['id'] ?>" class="fw-semibold"><?= e($r['name']) ?></a><?php if ($r['note']): ?><div class="small text-muted"><?= e($r['note']) ?></div><?php endif; ?></td>
      <td><?= e($r['phone']) ?></td>
      <td class="small"><?= fdate($r['last_date']) ?></td>
      <td class="amt">
        <?php if ($r['bal'] > 0.004): ?><span class="text-in fw-semibold"><?= money($r['bal']) ?></span><div class="small text-muted">wo mujhe dega</div>
        <?php elseif ($r['bal'] < -0.004): ?><span class="text-out fw-semibold"><?= money(-$r['bal']) ?></span><div class="small text-muted">mujhe dena hai</div>
        <?php else: ?><span class="badge text-bg-secondary">Clear</span><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="4" class="text-center text-muted p-4">Koi baqaya nahi.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>

<div class="modal fade" id="newPerson" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content"><?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <div class="modal-header"><h5 class="modal-title">Naya banda</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2">
    <div class="col-7"><label class="form-label">Naam</label><input name="name" class="form-control" required></div>
    <div class="col-5"><label class="form-label">Phone</label><input name="phone" class="form-control"></div>
    <div class="col-12"><label class="form-label">Note</label><input name="note" class="form-control" maxlength="255"></div>
    <div class="col-12"><hr class="my-2"><div class="small text-muted mb-1">Pehli entry (optional)</div></div>
    <div class="col-12"><select name="type" class="form-select"><?php foreach (LOAN_TYPES as $k => [$lbl]): ?><option value="<?= $k ?>"><?= $lbl ?></option><?php endforeach; ?></select></div>
    <div class="col-6"><input type="date" name="entry_date" value="<?= date('Y-m-d') ?>" class="form-control"></div>
    <div class="col-6"><input type="number" step="0.01" min="0" name="amount" class="form-control" placeholder="Raqam"></div>
    <div class="col-12"><input name="entry_note" class="form-control" placeholder="Note (e.g. wapsi ka wada)"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-success">Save</button></div>
</form></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
